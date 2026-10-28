<?php

namespace Tests\Unit;

use App\Models\Contract;
use App\Models\Payment;
use App\Support\BillReport;
use Carbon\Carbon;
use Tests\TestCase;

class BillReportTest extends TestCase
{
    private function contract(string $from, string $until, float $total = 15000000): Contract
    {
        $contract = new Contract([
            'start_date' => $from,
            'end_date' => $until,
            'total_price' => $total,
        ]);
        $contract->setRelation('payments', collect());

        return $contract;
    }

    private function payment(int $id, string $month, int $amount, string $status, string $proof = ''): Payment
    {
        $payment = new Payment([
            'month' => $month,
            'amount' => $amount,
            'status' => $status,
            'proof_url' => $proof,
        ]);
        $payment->id = $id;
        $payment->created_at = Carbon::parse('2026-09-05 09:00:00');

        return $payment;
    }

    public function test_counts_rent_months_like_frontend(): void
    {
        $this->assertSame(6, BillReport::months('2026-09-01', '2027-02-28'));
        $this->assertSame(3, BillReport::months('2026-11-15', '2027-02-14'));
        $this->assertSame(4, BillReport::months('2026-01-31', '2026-05-31'));
        $this->assertSame(1, BillReport::months('2026-09-01', '2026-09-01'));
    }

    public function test_monthly_fee_is_rounded(): void
    {
        $this->assertSame(2500000, BillReport::monthly(15000000, 6));
        $this->assertSame(15000000, BillReport::monthly(15000000, 0));
    }

    public function test_month_end_dates_do_not_overflow(): void
    {
        $contract = $this->contract('2026-01-31', '2026-05-31');
        $months = array_column(BillReport::rows($contract, Carbon::parse('2026-10-28')), 'month');

        $this->assertSame(['2026-01', '2026-02', '2026-03', '2026-04'], $months);
    }

    public function test_latest_upload_wins_and_empty_month_is_unpaid(): void
    {
        $contract = $this->contract('2026-09-01', '2027-02-28');
        $contract->setRelation('payments', collect([
            $this->payment(1, '2026-09', 2500000, 'paid', '/storage/proofs/bukti-lama.jpg'),
            $this->payment(2, '2026-09', 2500000, 'rejected'),
        ]));

        $rows = BillReport::rows($contract, Carbon::parse('2026-10-28'));

        $this->assertSame('Ditolak', $rows[0]['status']);
        $this->assertSame('', $rows[0]['proof']);
        $this->assertSame('Belum Bayar', $rows[1]['status']);
        $this->assertSame(2500000, $rows[1]['amount']);
        $this->assertSame('', $rows[1]['uploaded']);
    }

    public function test_payments_outside_rent_period_are_ignored(): void
    {
        $contract = $this->contract('2026-09-01', '2027-02-28');
        $contract->setRelation('payments', collect([
            $this->payment(1, '2026-03', 2500000, 'paid', '/storage/proofs/maret.jpg'),
        ]));

        $months = array_column(BillReport::rows($contract, Carbon::parse('2027-04-10')), 'month');

        $this->assertSame(['2026-09', '2026-10', '2026-11', '2026-12', '2027-01', '2027-02'], $months);
        $this->assertNotContains('2026-03', $months);
    }

    public function test_stops_at_current_month(): void
    {
        $contract = $this->contract('2026-09-01', '2027-02-28');

        $months = array_column(BillReport::rows($contract, Carbon::parse('2026-10-28')), 'month');

        $this->assertSame(['2026-09', '2026-10'], $months);
    }

    public function test_month_keys_stop_at_tail_month(): void
    {
        $this->assertSame(
            ['2026-11', '2026-12', '2027-01'],
            BillReport::monthKeys('2026-11-15', '2027-02-14'),
        );
    }

    public function test_month_keys_follow_month_end_dates(): void
    {
        $this->assertSame(
            ['2026-01', '2026-02', '2026-03', '2026-04'],
            BillReport::monthKeys('2026-01-31', '2026-05-31'),
        );
    }

    public function test_filename_and_upload_time_are_formatted(): void
    {
        $contract = $this->contract('2026-09-01', '2027-02-28');
        $contract->setRelation('payments', collect([
            $this->payment(1, '2026-09', 2500000, 'paid', '/storage/proofs/bukti-sep.jpg'),
        ]));

        $rows = BillReport::rows($contract, Carbon::parse('2026-10-28'));

        $this->assertSame('bukti-sep.jpg', $rows[0]['proof']);
        $this->assertSame('2026-09-05 09:00', $rows[0]['uploaded']);
        $this->assertSame('Lunas', $rows[0]['status']);
    }
}