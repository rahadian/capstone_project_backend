<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Payment;
use App\Support\BillReport;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class PaymentController extends Controller
{
    #[OA\Get(
        path: '/api/payments',
        summary: 'Get Payment List',
        description: 'Get list of payments. Tenant sees their own payments, Owner sees payments for their units.',
        security: [['bearerAuth' => []]],
        tags: ['Payments'],
        responses: [
            new OA\Response(response: 200, description: 'Payment List Fetched'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        $limit = $request->input('limit', 10);

        if ($user->role === 'tenant') {
            $tenant = $user->tenant;
            if (! $tenant) {
                return response()->json(['message' => 'Profil tenant tidak ditemukan.'], 404);
            }

            $payments = Payment::with(['contract.unit'])
                ->whereHas('contract', function ($query) use ($tenant) {
                    $query->where('tenant_id', $tenant->id);
                })
                ->latest()
                ->paginate($limit);

        } elseif ($user->role === 'owner') {
            $payments = Payment::with(['contract.unit', 'contract.tenant.user:id,email'])
                ->whereHas('contract.unit', function ($query) use ($user) {
                    $query->where('owner_id', $user->id);
                })
                ->latest()
                ->paginate($limit);

        } else {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        return response()->json($payments, 200);
    }

    #[OA\Get(
        path: '/api/payments/export',
        summary: 'Export Bill Report as CSV',
        description: 'Download monthly bill report as CSV. Tenant receives active contracts of their own, Owner receives active contracts on their units, including unpaid months.',
        security: [['bearerAuth' => []]],
        tags: ['Payments'],
        responses: [
            new OA\Response(response: 200, description: 'CSV downloaded', content: [
                new OA\MediaType(mediaType: 'text/csv'),
            ]),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized access'),
        ]
    )]
    public function export(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'tenant') {
            $tenant = $user->tenant;
            if (! $tenant) {
                return response()->json(['message' => 'Profil tenant tidak ditemukan.'], 404);
            }

            $contracts = Contract::with(['unit.owner:id,name', 'payments'])
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->orderBy('id')
                ->get();

            $columns = ['Bulan', 'Unit', 'Pemilik', 'Jumlah', 'Status', 'Bukti', 'Diunggah'];
            $counterpart = fn (Contract $contract) => $contract->unit?->owner?->name ?? '';
        } elseif ($user->role === 'owner') {
            $contracts = Contract::with(['unit', 'tenant', 'payments'])
                ->whereHas('unit', fn ($q) => $q->where('owner_id', $user->id))
                ->where('status', 'active')
                ->orderBy('id')
                ->get();

            $columns = ['Bulan', 'Unit', 'Penyewa', 'Jumlah', 'Status', 'Bukti', 'Diunggah'];
            $counterpart = fn (Contract $contract) => $contract->tenant?->name ?? '';
        } else {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $rows = (function () use ($contracts, $counterpart) {
            foreach ($contracts as $contract) {
                foreach (BillReport::rows($contract, now()) as $row) {
                    yield [
                        $row['month'],
                        $contract->unit?->name,
                        $counterpart($contract),
                        $row['amount'],
                        $row['status'],
                        $row['proof'],
                        $row['uploaded'],
                    ];
                }
            }
        })();

        return Csv::download('laporan-tagihan-'.now()->format('Ymd').'.csv', $columns, $rows);
    }

    #[OA\Post(
        path: '/api/payments',
        summary: 'Submit Payment Proof',
        description: 'Tenant uploads payment proof for a contract month.',
        security: [['bearerAuth' => []]],
        tags: ['Payments'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['contract_id', 'month', 'amount', 'proof'],
                    properties: [
                        new OA\Property(property: 'contract_id', type: 'integer', example: 1),
                        new OA\Property(property: 'month', type: 'string', example: '2026-10', description: 'Format YYYY-MM'),
                        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 1500000),
                        new OA\Property(
                            property: 'proof',
                            description: 'File bukti bayar (JPG, PNG, PDF max 2MB)',
                            type: 'string',
                            format: 'binary'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Payment Submitted'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation Error'),
        ]
    )]
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'tenant') {
            return response()->json(['message' => 'Hanya tenant yang dapat mengunggah bukti pembayaran.'], 403);
        }

        $tenant = $user->tenant;
        if (! $tenant) {
            return response()->json(['message' => 'Profil tenant belum lengkap.'], 400);
        }

        $validated = $request->validate([
            'contract_id' => 'required|exists:contracts,id',
            'month' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'amount' => 'required|numeric|min:0',
            'proof' => 'required|file|mimes:jpeg,png,jpg,pdf|max:2048',
        ]);

        $contract = Contract::findOrFail($validated['contract_id']);

        if ($contract->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke kontrak ini.'], 403);
        }

        $payment = DB::transaction(function () use ($request, $validated) {
            $path = $request->file('proof')->store('proofs', 'public');
            $proofUrl = Storage::disk('public')->url($path);

            return Payment::create([
                'contract_id' => $validated['contract_id'],
                'month' => $validated['month'],
                'amount' => $validated['amount'],
                'status' => 'pending',
                'proof_url' => $proofUrl,
            ]);
        });

        return response()->json([
            'message' => 'Bukti pembayaran berhasil diunggah.',
            'payment' => $payment->load('contract.unit'),
        ], 201);
    }

    #[OA\Get(
        path: '/api/payments/{id}',
        summary: 'Get Detail Payment',
        description: 'Get detail payment by ID.',
        security: [['bearerAuth' => []]],
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payment Detail Fetched'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Payment Not Found'),
        ]
    )]
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $payment = Payment::with(['contract.unit.owner', 'contract.tenant.user'])->find($id);

        if (! $payment) {
            return response()->json(['message' => 'Data pembayaran tidak ditemukan.'], 404);
        }

        $isTenantOwner = ($user->role === 'tenant' && $user->tenant && $payment->contract->tenant_id === $user->tenant->id);
        $isUnitOwner = ($user->role === 'owner' && $payment->contract->unit->owner_id === $user->id);

        if (! $isTenantOwner && ! $isUnitOwner) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        return response()->json($payment, 200);
    }

    #[OA\Put(
        path: '/api/payments/{id}',
        summary: 'Verify Payment',
        description: 'Owner verifies or rejects a payment.',
        security: [['bearerAuth' => []]],
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'paid', 'rejected'], example: 'paid'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Payment Status Updated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Payment Not Found'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $user = $request->user();

        if ($user->role !== 'owner') {
            return response()->json(['message' => 'Hanya owner yang dapat memverifikasi pembayaran.'], 403);
        }

        $payment = Payment::with('contract.unit')->find($id);
        if (! $payment) {
            return response()->json(['message' => 'Data pembayaran tidak ditemukan.'], 404);
        }

        if ($payment->contract->unit->owner_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:pending,paid,rejected',
        ]);

        $payment->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Status pembayaran berhasil diperbarui.',
            'payment' => $payment->fresh(['contract.unit', 'contract.tenant']),
        ], 200);
    }

    public function destroy(string $id)
    {
        return response()->json(['message' => 'Method not allowed.'], 405);
    }
}
