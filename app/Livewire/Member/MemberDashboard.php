<?php

namespace App\Livewire\Member;

use App\Domain\Billing\Models\Bill;
use App\Domain\Membership\Models\Member;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.member')]
class MemberDashboard extends Component
{
    public string $activeTab = 'card'; // 'card', 'wallet', 'plans', 'bookings', 'bills', 'family'

    public bool $showBillModal = false;
    public ?Bill $selectedBill = null;

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['card', 'wallet', 'plans', 'bookings', 'bills', 'family'])) {
            $this->activeTab = $tab;
        }
    }

    public function openBillModal(int $billId): void
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user();

        $this->selectedBill = Bill::with(['lines'])
            ->where('customer_type', Member::class)
            ->where('customer_id', $member->id)
            ->where('id', $billId)
            ->first();

        if ($this->selectedBill) {
            $this->showBillModal = true;
        } else {
            $this->addError('bill', 'Invoice not found or unauthorized.');
        }
    }

    public function closeBillModal(): void
    {
        $this->showBillModal = false;
        $this->selectedBill = null;
    }

    public function render()
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user()->load([
            'person',
            'category',
            'activeCard',
            'wallet',
            'dependents.person',
        ]);

        $wallet = $member->wallet;
        $balanceRupees = ($wallet?->balance_paise ?? 0) / 100;

        $recentTransactions = $wallet
            ? $wallet->transactions()->take(25)->get()
            : collect();

        $activeSubscriptions = $member->subscriptions()
            ->with(['tariff'])
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()->toDateString());
            })
            ->get();

        $upcomingCourtBookings = $member->courtBookings()
            ->with('facility')
            ->where('booking_date', '>=', now()->toDateString())
            ->where('status', 'confirmed')
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->take(10)
            ->get();

        $roomReservations = $member->roomReservations()
            ->with('facility')
            ->orderBy('check_in_date', 'desc')
            ->take(10)
            ->get();

        $bills = $member->bills()
            ->with('items')
            ->where('status', 'paid')
            ->orderBy('created_at', 'desc')
            ->take(25)
            ->get();

        return view('livewire.member.member-dashboard', [
            'member' => $member,
            'wallet' => $wallet,
            'balanceRupees' => $balanceRupees,
            'recentTransactions' => $recentTransactions,
            'activeSubscriptions' => $activeSubscriptions,
            'upcomingCourtBookings' => $upcomingCourtBookings,
            'roomReservations' => $roomReservations,
            'bills' => $bills,
            'dependents' => $member->dependents,
        ]);
    }
}
