<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\Unit;
use App\Notifications\ContractStatusNotification;
use App\Support\BillReport;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ContractController extends Controller
{
    /* Alasan penghentian yang diterima. Kontrak lama tidak punya nilainya, jadi
       kolom laporan dibiarkan kosong untuk baris seperti itu. */
    private const END_REASONS = [
        'selesai_normal' => 'Selesai normal',
        'berhenti_penyewa' => 'Berhenti penyewa',
        'pelanggaran' => 'Pelanggaran',
        'lainnya' => 'Lainnya',
    ];

    #[OA\Get(
        path: '/api/contracts',
        summary: 'Get Contract List',
        description: 'Get list of contracts. Tenant sees their own contracts, Owner sees contracts for their units.',
        security: [['bearerAuth' => []]],
        tags: ['Contracts'],
        responses: [
            new OA\Response(response: 200, description: 'Contract List Fetched'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        $limit = $request->input('limit', 10);


        if ($user->role === 'tenant') {
            $tenant = $user->tenant;
            if (!$tenant) {
                return response()->json(['message' => 'Profil tenant tidak ditemukan.'], 404);
            }

            $query = Contract::with(['unit.owner:id,name,email', 'tenant:id,name,user_id,ktp_status', 'payments'])
                ->where('tenant_id', $tenant->id)
                ->latest()
                ->orderByDesc('id');
                // ->paginate($limit);

        } else if ($user->role === 'owner') {
            $query = Contract::with(['unit', 'tenant:id,name,user_id,ktp_status', 'tenant.user:id,email', 'payments'])
                ->whereHas('unit', function ($query) use ($user) {
                    $query->where('owner_id', $user->id);
                })
                ->latest()
                ->orderByDesc('id');
                // ->paginate($limit);

        } else {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $status = $request->input('status');

        if ($status==='all'){

        }else if($status){
            $query->where('status',$status);
        }else{
            $query->where('status','active');
        }
        $limit = $request->input('limit',10);
        $contract = $query->paginate($limit);

        return response()->json($contract, 200);
    }

    #[OA\Get(
        path: '/api/contracts/export',
        summary: 'Export Contract Report as CSV',
        description: 'Download contract report as CSV for all statuses of contracts on the owner units.',
        security: [['bearerAuth' => []]],
        tags: ['Contracts'],
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

        if ($user->role !== 'owner') {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $contracts = Contract::with(['unit.owner:id,name', 'tenant'])
            ->whereHas('unit', fn ($q) => $q->where('owner_id', $user->id))
            ->orderBy('id')
            ->get();

        $labels = [
            'pending' => 'Menunggu',
            'active' => 'Aktif',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
        ];

        $rows = $contracts->map(function (Contract $contract) use ($labels) {
            $months = BillReport::months($contract->start_date->toDateString(), $contract->end_date->toDateString());

            return [
                $contract->id,
                $contract->tenant?->name,
                $contract->unit?->name,
                $contract->unit?->owner?->name,
                $contract->start_date->format('Y-m-d'),
                $contract->end_date->format('Y-m-d'),
                "{$months} bulan",
                BillReport::monthly((float) $contract->total_price, $months),
                (int) round((float) $contract->total_price),
                /* `cancelled` tanpa `ended_at` adalah pengajuan yang ditolak
                   (ADR 0002), bukan sewa yang berhenti. */
                $contract->status === 'cancelled' && $contract->ended_at === null
                    ? 'Ditolak'
                    : ($labels[$contract->status] ?? $contract->status),
                $contract->ended_at?->format('Y-m-d') ?? '',
                $contract->end_reason ? (self::END_REASONS[$contract->end_reason] ?? $contract->end_reason) : '',
            ];
        });

        return Csv::download(
            'laporan-kontrak-'.now()->format('Ymd').'.csv',
            ['ID', 'Penyewa', 'Unit', 'Pemilik', 'Mulai', 'Selesai', 'Durasi', 'Harga per Bulan', 'Nilai Kontrak', 'Status', 'Berakhir Pada', 'Alasan'],
            $rows,
        );
    }

    #[OA\Post(
        path: '/api/contracts',
        summary: 'Create Contract Proposal',
        description: 'Tenant submits a rental proposal for a unit, or Owner creates a contract for a managed tenant.',
        security: [['bearerAuth' => []]],
        tags: ['Contracts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['unit_id', 'start_date', 'end_date', 'total_price'],
                properties: [
                    new OA\Property(property: 'unit_id', type: 'integer', example: 1),
                    new OA\Property(property: 'tenant_id', type: 'integer', description: 'Required for owner role', example: 1),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-11-01'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2027-11-01'),
                    new OA\Property(property: 'total_price', type: 'number', format: 'float', example: 15000000)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Contract Created'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation Error or date range already booked')
        ]
    )]
    public function store(Request $request)
    {
        $user = $request->user();

        if (! in_array($user->role, ['tenant', 'owner'], true)) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $rules = [
            'unit_id'     => 'required|exists:units,id',
            'start_date'  => 'required|date|after_or_equal:today',
            'end_date'    => 'required|date|after:start_date',
            'total_price' => 'required|numeric|min:0',
        ];

        if ($user->role === 'owner') {
            $rules['tenant_id'] = 'required|exists:tenants,id';
        }

        $validated = $request->validate($rules);
        $unit = Unit::findOrFail($validated['unit_id']);

        if ($user->role === 'owner') {
            if ($unit->owner_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized access'], 403);
            }

            $tenant = Tenant::findOrFail($validated['tenant_id']);
            if ($tenant->owner_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized access'], 403);
            }
        } else {
            $tenant = $user->tenant;
            if (! $tenant) {
                return response()->json(['message' => 'Profil tenant belum lengkap.'], 400);
            }
        }

        if ($unit->hasOverlap($validated['start_date'], $validated['end_date'])) {
            return response()->json(['message' => 'Tanggal yang dipilih sudah terisi atau diblokir.'], 422);
        }

        $contract = Contract::create([
            'unit_id'     => $validated['unit_id'],
            'tenant_id'   => $tenant->id,
            'start_date'  => $validated['start_date'],
            'end_date'    => $validated['end_date'],
            'total_price' => $validated['total_price'],
            'status'      => 'pending',
        ]);

        if ($user->role === 'tenant') {
            $unit->owner?->notify(new ContractStatusNotification('submitted', $contract->load('tenant')));
        }

        return response()->json([
            'message'  => $user->role === 'owner'
                ? 'Kontrak berhasil dibuat.'
                : 'Pengajuan kontrak berhasil dibuat.',
            'contract' => $contract->load(['unit', 'tenant'])
        ], 201);
    }

    #[OA\Get(
        path: '/api/contracts/{id}',
        summary: 'Get Detail Contract',
        description: 'Get detail contract by ID. Accessible by related Tenant or Owner.',
        security: [['bearerAuth' => []]],
        tags: ['Contracts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contract Detail Fetched'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Contract Not Found')
        ]
    )]
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $contract = Contract::with(['unit.owner:id,name,email', 'tenant.user:id,email', 'payments'])->find($id);

        if (!$contract) {
            return response()->json(['message' => 'Kontrak tidak ditemukan.'], 404);
        }

        // Cek hak akses
        $isTenantOwner = ($user->role === 'tenant' && $user->tenant && $contract->tenant_id === $user->tenant->id);
        $isUnitOwner   = ($user->role === 'owner' && $contract->unit->owner_id === $user->id);

        if (!$isTenantOwner && !$isUnitOwner) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        return response()->json($contract, 200);
    }

    #[OA\Put(
        path: '/api/contracts/{id}',
        summary: 'Update Contract Status',
        description: 'Update contract status. Closing an active contract before its end_date is an early termination: ended_at and end_reason become required, and end_note is required when the reason is "lainnya". Closing at or after end_date stores end_date and "selesai_normal". Reactivating clears all three. Accessible by Owner.',
        security: [['bearerAuth' => []]],
        tags: ['Contracts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'active', 'completed', 'cancelled'], example: 'active'),
                    new OA\Property(property: 'ended_at', type: 'string', format: 'date', nullable: true, description: 'Tanggal berhenti; wajib saat penghentian dini dan harus di dalam masa sewa.', example: '2026-10-15'),
                    new OA\Property(property: 'end_reason', type: 'string', nullable: true, enum: ['selesai_normal', 'berhenti_penyewa', 'pelanggaran', 'lainnya'], description: 'Wajib saat penghentian dini.', example: 'berhenti_penyewa'),
                    new OA\Property(property: 'end_note', type: 'string', nullable: true, maxLength: 500, description: 'Wajib bila end_reason = lainnya.')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Contract Status Updated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Contract Not Found'),
            new OA\Response(response: 422, description: 'Validation Error')
        ]
    )]
    public function update(Request $request, string $id)
    {
        $user = $request->user();

        if ($user->role !== 'owner') {
            return response()->json(['message' => 'Hanya owner yang dapat mengubah status kontrak.'], 403);
        }

        $contract = Contract::with('unit')->find($id);
        if (!$contract) {
            return response()->json(['message' => 'Kontrak tidak ditemukan.'], 404);
        }

        if ($contract->unit->owner_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        /* Pesan Indonesia, bukan bawaan Laravel: layar pemilik menampilkan
           `message` dari 422 apa adanya, dan handler mock memakai teks yang
           sama supaya tes tidak lolos atas kalimat yang tidak pernah muncul. */
        $pesan = [
            'status.required' => 'Status kontrak wajib diisi.',
            'status.in' => 'Status kontrak tidak dikenal.',
            'ended_at.required' => 'Tanggal penghentian wajib diisi.',
            'ended_at.date' => 'Tanggal penghentian tidak sah.',
            'ended_at.after_or_equal' => 'Tanggal penghentian tidak boleh sebelum masa sewa.',
            'ended_at.before_or_equal' => 'Tanggal penghentian tidak boleh sesudah masa sewa.',
            'end_reason.required' => 'Alasan penghentian wajib dipilih.',
            'end_reason.in' => 'Alasan penghentian tidak dikenal.',
            'end_note.required_if' => 'Catatan wajib diisi bila alasannya Lainnya.',
            'end_note.max' => 'Catatan maksimal 500 karakter.',
        ];

        $validated = $request->validate([
            'status' => 'required|string|in:pending,active,completed,cancelled',
            'ended_at' => 'nullable|date',
            'end_reason' => 'nullable|string|in:'.implode(',', array_keys(self::END_REASONS)),
            'end_note' => 'nullable|string|max:500',
        ], $pesan);

        $menutup = in_array($validated['status'], ['completed', 'cancelled'], true);
        $berjalan = $contract->status === 'active';
        /* Ditutup sebelum tanggal selesai berarti penghentian dini. Kontrak yang
           ditutup pada atau setelah tanggal selesainya adalah penyelesaian biasa
           dan tidak perlu alasan. */
        $dini = $menutup && $berjalan && $contract->end_date->isAfter(today());

        if ($dini) {
            $request->validate([
                'ended_at' => [
                    'required',
                    'date',
                    'after_or_equal:'.$contract->start_date->toDateString(),
                    'before_or_equal:'.$contract->end_date->toDateString(),
                ],
                'end_reason' => 'required|string|in:'.implode(',', array_keys(self::END_REASONS)),
                'end_note' => 'required_if:end_reason,lainnya|nullable|string|max:500',
            ], $pesan);
        }

        if ($validated['status'] === 'active' && $contract->unit->hasOverlap(
            $contract->start_date->toDateString(),
            $contract->end_date->toDateString(),
            $contract->id,
        )) {
            return response()->json(['message' => 'Tanggal yang dipilih sudah terisi atau diblokir.'], 422);
        }

        $nilai = ['status' => $validated['status']];

        if ($dini) {
            $nilai += [
                'ended_at' => $request->input('ended_at'),
                'end_reason' => $request->input('end_reason'),
                'end_note' => $request->input('end_note'),
            ];
        } elseif ($menutup && $berjalan) {
            $nilai += [
                'ended_at' => $contract->end_date->toDateString(),
                'end_reason' => 'selesai_normal',
                'end_note' => null,
            ];
        } elseif ($validated['status'] === 'active') {
            /* Mengaktifkan kembali kontrak membatalkan jejak penghentian supaya
               alasan lama tidak menempel pada sewa yang berjalan lagi. */
            $nilai += ['ended_at' => null, 'end_reason' => null, 'end_note' => null];
        }

        $statusLama = $contract->status;

        DB::transaction(function () use ($contract, $nilai, $validated) {
            $contract->update($nilai);

            // Unit hanya ditandai terisi bila masa sewa sudah berjalan
            if ($validated['status'] === 'active' && $contract->start_date->lte(today())) {
                $contract->unit->update(['status' => 'rented']);
            }
            // Jika kontrak selesai atau dibatalkan, kembalikan status unit jadi available
            else if (in_array($validated['status'], ['completed', 'cancelled'])) {
                $contract->unit->update(['status' => 'available']);
            }
        });

        /* Keputusan pemilik diteruskan ke penyewa: pengaktifan adalah persetujuan,
           sedangkan pembatalan kontrak yang masih pending adalah penolakan (ADR 0002). */
        if ($validated['status'] === 'active') {
            $contract->tenant?->user?->notify(new ContractStatusNotification('approved', $contract->load('unit')));
        } elseif ($validated['status'] === 'cancelled' && $statusLama === 'pending') {
            $contract->tenant?->user?->notify(new ContractStatusNotification('rejected', $contract->load('unit')));
        }

        return response()->json([
            'message'  => 'Status kontrak berhasil diperbarui.',
            'contract' => $contract->fresh(['unit', 'tenant'])
        ], 200);
    }

    public function destroy(string $id)
    {
        return response()->json(['message' => 'Method not allowed. Use status update instead.'], 405);
    }
}
