<?php

namespace App\Support;

use App\Models\Contract;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BillReport
{
    private const LABEL_STATUS = [
        'pending' => 'Menunggu Verifikasi',
        'paid' => 'Lunas',
        'rejected' => 'Ditolak',
    ];

    public static function months(string $from, string $until): int
    {
        $start = Carbon::parse($from);
        $end = Carbon::parse($until);
        $diff = ($end->year - $start->year) * 12 + ($end->month - $start->month);

        return max(1, $diff + ($end->day > $start->day ? 1 : 0));
    }

    public static function monthly(float $total, int $months): int
    {
        return $months > 0 ? (int) round($total / $months) : (int) round($total);
    }

    /**
     * @return list<array{month: string, amount: int, status: string, proof: string, uploaded: string}>
     */
    public static function rows(Contract $contract, CarbonInterface $now): array
    {
        $months = min(
            self::months($contract->start_date->toDateString(), $contract->end_date->toDateString()),
            120,
        );
        $fee = self::monthly((float) $contract->total_price, $months);

        $latest = [];
        foreach ($contract->payments as $payment) {
            $key = substr($payment->month, 0, 7);
            if (! isset($latest[$key]) || $payment->id > $latest[$key]->id) {
                $latest[$key] = $payment;
            }
        }

        $rows = [];
        $start = $contract->start_date->copy()->startOfMonth();
        $limit = Carbon::parse($now)->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonthsNoOverflow($i);
            if ($month->gt($limit)) {
                break;
            }

            $key = $month->format('Y-m');
            $payment = $latest[$key] ?? null;

            $rows[] = [
                'month' => $key,
                'amount' => $payment
                    ? (int) round((float) $payment->amount)
                    : $fee,
                'status' => $payment
                    ? (self::LABEL_STATUS[$payment->status] ?? 'Belum Bayar')
                    : 'Belum Bayar',
                'proof' => $payment && $payment->proof_url
                    ? basename($payment->proof_url)
                    : '',
                'uploaded' => $payment && $payment->created_at
                    ? $payment->created_at->format('Y-m-d H:i')
                    : '',
            ];
        }

        return $rows;
    }
}