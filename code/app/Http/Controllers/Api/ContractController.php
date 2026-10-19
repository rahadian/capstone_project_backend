<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ContractController extends Controller
{
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

            $contracts = Contract::with(['unit.owner:id,name,email', 'payments'])
                ->where('tenant_id', $tenant->id)
                ->latest()
                ->paginate($limit);

        } else if ($user->role === 'owner') {
            $contracts = Contract::with(['unit', 'tenant.user:id,email', 'payments'])
                ->whereHas('unit', function ($query) use ($user) {
                    $query->where('owner_id', $user->id);
                })
                ->latest()
                ->paginate($limit);

        } else {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        return response()->json($contracts, 200);
    }

    #[OA\Post(
        path: '/api/contracts',
        summary: 'Create Contract Proposal',
        description: 'Tenant submits a new rental contract proposal for a unit.',
        security: [['bearerAuth' => []]],
        tags: ['Contracts'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['unit_id', 'start_date', 'end_date', 'total_price'],
                properties: [
                    new OA\Property(property: 'unit_id', type: 'integer', example: 1),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-11-01'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2027-11-01'),
                    new OA\Property(property: 'total_price', type: 'number', format: 'float', example: 15000000)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Contract Created'),
            new OA\Response(response: 403, description: 'Forbidden - Only Tenant can create contract'),
            new OA\Response(response: 422, description: 'Validation Error')
        ]
    )]
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'tenant') {
            return response()->json(['message' => 'Hanya tenant yang dapat membuat pengajuan kontrak.'], 403);
        }

        $tenant = $user->tenant;
        if (!$tenant) {
            return response()->json(['message' => 'Profil tenant belum lengkap.'], 400);
        }

        $validated = $request->validate([
            'unit_id'     => 'required|exists:units,id',
            'start_date'  => 'required|date|after_or_equal:today',
            'end_date'    => 'required|date|after:start_date',
            'total_price' => 'required|numeric|min:0',
        ]);

        // Cek ketersediaan unit
        $unit = Unit::findOrFail($validated['unit_id']);
        if ($unit->status !== 'available') {
            return response()->json(['message' => 'Unit sedang tidak tersedia untuk disewa.'], 400);
        }

        $contract = DB::transaction(function () use ($validated, $tenant) {
            return Contract::create([
                'unit_id'     => $validated['unit_id'],
                'tenant_id'   => $tenant->id,
                'start_date'  => $validated['start_date'],
                'end_date'    => $validated['end_date'],
                'total_price' => $validated['total_price'],
                'status'      => 'pending',
            ]);
        });

        return response()->json([
            'message'  => 'Pengajuan kontrak berhasil dibuat.',
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
        description: 'Update contract status (e.g. active, cancelled, completed). Accessible by Owner.',
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
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'active', 'completed', 'cancelled'], example: 'active')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Contract Status Updated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Contract Not Found')
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

        $validated = $request->validate([
            'status' => 'required|string|in:pending,active,completed,cancelled',
        ]);

        DB::transaction(function () use ($contract, $validated) {
            $contract->update(['status' => $validated['status']]);

            // Jika status kontrak diaktifkan, ubah status unit jadi rented
            if ($validated['status'] === 'active') {
                $contract->unit->update(['status' => 'rented']);
            }
            // Jika kontrak selesai atau dibatalkan, kembalikan status unit jadi available
            else if (in_array($validated['status'], ['completed', 'cancelled'])) {
                $contract->unit->update(['status' => 'available']);
            }
        });

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
