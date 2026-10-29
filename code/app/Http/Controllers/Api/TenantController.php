<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TenantController extends Controller
{
    private function scopeFor(User $user): Builder
    {
        return Tenant::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('contracts.unit', fn ($query) => $query->where('owner_id', $user->id));
    }

    private function row(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'user_id' => $tenant->user_id,
            'owner_id' => $tenant->owner_id,
            'name' => $tenant->name,
            'phone' => $tenant->phone,
            'ktp_status' => $tenant->ktp_status,
            'contracts_count' => $tenant->contracts_count ?? 0,
            'created_at' => $tenant->created_at,
        ];
    }

    #[OA\Get(
        path: '/api/tenants',
        summary: 'List Tenants Managed by Owner',
        description: 'Owner-created tenants plus tenants with contracts on the owner units.',
        tags: ['Tenants'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Tenant List Fetched Successfully'),
            new OA\Response(response: 403, description: 'Forbidden')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'owner') {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $tenants = $this->scopeFor($user)
            ->withCount('contracts')
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $tenant) => $this->row($tenant));

        return response()->json(['data' => $tenants], 200);
    }

    #[OA\Post(
        path: '/api/tenants',
        summary: 'Create Tenant Without Account',
        tags: ['Tenants'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Budi Santoso'),
                    new OA\Property(property: 'phone', type: 'string', example: '628123456789'),
                    new OA\Property(property: 'ktp_status', type: 'string', enum: ['unverified', 'pending', 'verified'])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Tenant Created Successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'owner') {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'ktp_status' => 'nullable|in:unverified,pending,verified',
        ]);

        $tenant = Tenant::create([
            'user_id' => null,
            'owner_id' => $user->id,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'ktp_status' => $validated['ktp_status'] ?? 'unverified',
        ]);
        $tenant->loadCount('contracts');

        return response()->json([
            'message' => 'Penyewa berhasil ditambahkan.',
            'tenant' => $this->row($tenant),
        ], 201);
    }

    #[OA\Put(
        path: '/api/tenants/{tenant}',
        summary: 'Update Tenant',
        tags: ['Tenants'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'tenant', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Budi Santoso'),
                    new OA\Property(property: 'phone', type: 'string', example: '628123456789'),
                    new OA\Property(property: 'ktp_status', type: 'string', enum: ['unverified', 'pending', 'verified'])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Tenant Updated Successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Tenant Not Found'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $user = $request->user();

        if (! $this->scopeFor($user)->whereKey($tenant->id)->exists()) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'ktp_status' => 'sometimes|required|in:unverified,pending,verified',
        ]);

        $tenant->update($validated);
        $tenant->loadCount('contracts');

        return response()->json([
            'message' => 'Penyewa berhasil diperbarui.',
            'tenant' => $this->row($tenant),
        ], 200);
    }

    #[OA\Delete(
        path: '/api/tenants/{tenant}',
        summary: 'Delete Tenant',
        description: 'Only tenants created by the owner and without contracts can be deleted.',
        tags: ['Tenants'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'tenant', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Tenant Deleted Successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Tenant Not Found'),
            new OA\Response(response: 409, description: 'Tenant still has contracts')
        ]
    )]
    public function destroy(Request $request, Tenant $tenant): JsonResponse
    {
        if ($tenant->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        if ($tenant->contracts()->exists()) {
            return response()->json(['message' => 'Penyewa yang memiliki kontrak tidak dapat dihapus.'], 409);
        }

        $tenant->delete();

        return response()->json(null, 204);
    }
}
