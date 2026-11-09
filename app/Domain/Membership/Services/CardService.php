<?php

namespace App\Domain\Membership\Services;

use App\Domain\Integrations\Contracts\CardReaderInterface;
use App\Domain\Membership\Models\Card;
use App\Domain\Shared\Enums\CardStatus;
use App\Domain\Shared\Exceptions\CardNotActiveException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CardService
{
    public function __construct(
        protected CardReaderInterface $reader
    ) {}

    /**
     * Find card by raw reader input or manual text.
     */
    public function findCard(string $rawInput): ?Card
    {
        $parsed = $this->reader->parseInput($rawInput);
        if (!$parsed) {
            return null;
        }

        return Card::where('card_number', $parsed)->first();
    }

    /**
     * Issue a new card to a member or guest.
     */
    public function issueCard(Model $cardable, string $cardNumber, ?string $notes = null): Card
    {
        $parsed = $this->reader->parseInput($cardNumber);
        if (!$parsed) {
            throw new InvalidArgumentException("Invalid card number format.");
        }

        // Deactivate any currently active cards for this cardable
        Card::where('cardable_type', get_class($cardable))
            ->where('cardable_id', $cardable->getKey())
            ->where('status', CardStatus::ACTIVE)
            ->update([
                'status' => CardStatus::BLOCKED,
                'blocked_at' => now(),
                'notes' => 'Superseded by new card issuance',
            ]);

        return Card::create([
            'card_number' => $parsed,
            'cardable_type' => get_class($cardable),
            'cardable_id' => $cardable->getKey(),
            'status' => CardStatus::ACTIVE,
            'issued_at' => now(),
            'notes' => $notes ?: 'Initial card issuance',
        ]);
    }

    /**
     * Block a lost or compromised card.
     */
    public function blockCard(Card $card, string $reason): Card
    {
        $card->update([
            'status' => CardStatus::BLOCKED,
            'blocked_at' => now(),
            'notes' => $reason,
        ]);

        return $card->fresh();
    }

    /**
     * Replace a lost or damaged card.
     * The old card is marked replaced and rejected for future use.
     * The new card inherits the exact same cardable identity and history.
     */
    public function replaceCard(Card $oldCard, string $newCardNumber, string $reason): Card
    {
        $parsedNew = $this->reader->parseInput($newCardNumber);
        if (!$parsedNew) {
            throw new InvalidArgumentException("Invalid replacement card number.");
        }

        if (Card::where('card_number', $parsedNew)->exists()) {
            throw new InvalidArgumentException("Card number {$parsedNew} is already registered.");
        }

        return DB::transaction(function () use ($oldCard, $parsedNew, $reason) {
            $newCard = Card::create([
                'card_number' => $parsedNew,
                'cardable_type' => $oldCard->cardable_type,
                'cardable_id' => $oldCard->cardable_id,
                'status' => CardStatus::ACTIVE,
                'issued_at' => now(),
                'notes' => "Replacement card. Reason: {$reason}",
            ]);

            $oldCard->update([
                'status' => CardStatus::REPLACED,
                'blocked_at' => now(),
                'replaced_by_card_id' => $newCard->id,
                'notes' => "Replaced by {$parsedNew}. Reason: {$reason}",
            ]);

            return $newCard;
        });
    }

    /**
     * Ensure card is active and returns the owner.
     *
     * @throws CardNotActiveException
     */
    public function validateActiveCard(Card $card): Model
    {
        if (!$card->isActive()) {
            throw new CardNotActiveException("Card {$card->card_number} is {$card->status->value} and cannot be used.");
        }

        return $card->cardable;
    }
}
