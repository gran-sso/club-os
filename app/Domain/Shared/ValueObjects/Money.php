<?php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

final class Money
{
    private function __construct(
        private readonly int $paise
    ) {}

    public static function fromPaise(int $paise): self
    {
        return new self($paise);
    }

    public static function fromRupees(float|int|string $rupees): self
    {
        // Exact decimal conversion without floating-point drift
        $normalized = (string) $rupees;
        if (!is_numeric($normalized)) {
            throw new InvalidArgumentException("Invalid rupee amount: {$rupees}");
        }

        $paise = (int) round(((float) $normalized) * 100);
        return new self($paise);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function paise(): int
    {
        return $this->paise;
    }

    public function rupees(): float
    {
        return $this->paise / 100.0;
    }

    public function add(self $other): self
    {
        return new self($this->paise + $other->paise);
    }

    public function subtract(self $other): self
    {
        return new self($this->paise - $other->paise);
    }

    public function isZero(): bool
    {
        return $this->paise === 0;
    }

    public function isPositive(): bool
    {
        return $this->paise > 0;
    }

    public function isNegative(): bool
    {
        return $this->paise < 0;
    }

    public function format(): string
    {
        $sign = $this->paise < 0 ? '-' : '';
        $absolute = abs($this->paise);
        $rupees = intdiv($absolute, 100);
        $remainder = $absolute % 100;

        return sprintf('%s₹%s.%02d', $sign, number_format($rupees), $remainder);
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
