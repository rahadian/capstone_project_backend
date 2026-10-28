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
     * @return list<string>
     */
    public static function monthKeys(string $from, string $until): array
    {
        $count = min(self::months($from, $until), 120);
        $start = Carbon::parse($from)->startOfMonth();

        return array_map(
            fn (int $offset) => $start->copy()->addMonthsNoOverflow($offset)->format('Y-m'),
            range(0, $count - 1),
        );
    }

    /**
     * @return list<array{month: string, amount: int, status: string, proof: string, uploaded: string}>
     */
    public static function rows(Contract $contract, CarbonInterface $now): array
    {
        $fee = self::monthly(
            (float) $contract->total_price,
            self::months($contract->start_date->toDateString(), $contract->end_date->toDateString()),
        );

        $latest = [];
        foreach ($contract->payments as $payment) {
            $key = substr($payment->month, 0, 7);
            if (! isset($latest[$key]) || $payment->id > $latest[$key]->id) {
                $latest[$key] = $payment;
            }
        }

        $rows = [];
        $limit = Carbon::parse($now)->startOfMonth();

        foreach (self::monthKeys($contract->start_date->toDateString(), $contract->end_date->toDateString()) as $key) {
            if (Carbon::parse("{$key}-01")->gt($limit)) {
                break;
            }

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