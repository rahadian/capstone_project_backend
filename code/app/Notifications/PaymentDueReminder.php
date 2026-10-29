<?php

namespace App\Notifications;

use App\Models\Contract;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Notification;

class PaymentDueReminder extends Notification
{
    public function __construct(
        private string $type,
        private string $month,
        private int $amount,
        private CarbonInterface $due,
        private Contract $contract,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_due',
            'message' => $this->message(),
            'month' => $this->month,
            'amount' => $this->amount,
            'contract_id' => $this->contract->id,
        ];
    }

    private function message(): string
    {
        $phrase = match ($this->type) {
            'h_minus_7' => 'jatuh tempo 7 hari lagi',
            'h_minus_1' => 'jatuh tempo besok',
            'h_plus_1' => 'lewat jatuh tempo 1 hari',
            'h_plus_30' => 'lewat jatuh tempo 30 hari',
            default => 'perlu dibayar',
        };

        $month = Carbon::parse($this->month.'-01');

        return sprintf(
            'Tagihan %s sebesar Rp%s %s (%s).',
            $month->locale('id')->translatedFormat('F Y'),
            number_format($this->amount, 0, ',', '.'),
            $phrase,
            $this->due->locale('id')->translatedFormat('j F Y'),
        );
    }
}
