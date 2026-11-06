<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Notifications\Notification;

/* Peristiwa siklus pengajuan kontrak: submitted (penyewa → pemilik),
   approved/rejected (pemilik → penyewa). Kanal database saja. */
class ContractStatusNotification extends Notification
{
    public function __construct(
        private string $event,
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
            'type' => 'contract_status',
            'message' => $this->message(),
            'contract_id' => $this->contract->id,
        ];
    }

    private function message(): string
    {
        $unit = $this->contract->unit?->name ?? 'unit';

        return match ($this->event) {
            'submitted' => sprintf('Penyewa %s mengajukan sewa unit %s.', $this->contract->tenant?->name ?? 'penyewa', $unit),
            'approved' => sprintf('Pengajuan sewa unit %s disetujui.', $unit),
            'rejected' => sprintf('Pengajuan sewa unit %s ditolak.', $unit),
            default => sprintf('Status sewa unit %s diperbarui.', $unit),
        };
    }
}
