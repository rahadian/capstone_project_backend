<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Reminder;
use App\Notifications\PaymentDueReminder;
use App\Support\BillReport;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DispatchPaymentReminders extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Kirim pengingat tagihan bulanan ke notifikasi in-app';

    public function handle(): int
    {
        $now = now('Asia/Jakarta')->startOfDay();
        $current = $now->format('Y-m');
        $previous = $now->copy()->subMonthNoOverflow()->format('Y-m');

        $contracts = Contract::with(['unit', 'tenant.user', 'payments'])
            ->where('status', 'active')
            ->get();

        foreach ($contracts as $contract) {
            $start = $contract->start_date->toDateString();
            $until = $contract->end_date->toDateString();
            $months = BillReport::monthKeys($start, $until);
            $amount = BillReport::monthly((float) $contract->total_price, BillReport::months($start, $until));

            foreach ($months as $month) {
                if ($month > $current) {
                    continue;
                }

                $due = Carbon::parse("{$month}-10 23:59:59", 'Asia/Jakarta');

                if ($contract->start_date->gt($due)) {
                    continue;
                }

                $types = [];
                if ($month === $current) {
                    $types = ['h_minus_7' => 7, 'h_minus_1' => 1, 'h_plus_1' => -1];
                } elseif ($month === $previous) {
                    $types = ['h_plus_30' => -30];
                }

                if ($types === []) {
                    continue;
                }

                $latest = $contract->payments
                    ->where('month', $month)
                    ->sortByDesc('id')
                    ->first();

                if ($latest && $latest->status === 'paid') {
                    continue;
                }

                foreach ($types as $type => $offset) {
                    $tanggal = $due->copy()->subDays($offset)->startOfDay();

                    if (! $tanggal->equalTo($now)) {
                        continue;
                    }

                    $reminder = Reminder::firstOrCreate(
                        ['contract_id' => $contract->id, 'month' => $month, 'type' => $type],
                        ['sent_at' => now()],
                    );

                    if ($reminder->wasRecentlyCreated && $contract->tenant?->user) {
                        $contract->tenant->user->notify(
                            new PaymentDueReminder($type, $month, $amount, $due, $contract)
                        );
                    }
                }
            }
        }

        return self::SUCCESS;
    }
}
