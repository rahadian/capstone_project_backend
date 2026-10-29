<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\UnitBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class UnitBlockController extends Controller
{
    #[OA\Get(
        path: '/api/units/{unit}/blocks',
        summary: 'List Blocked Dates of a Unit',
        tags: ['Unit Blocks'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'unit', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Block List Fetched Successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Unit Not Found')
        ]
    )]
    public function index(Request $request, Unit $unit): JsonResponse
    {
        if ($unit->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $blocks = $unit->blocks()->orderBy('start_date')->get();

        return response()->json(['data' => $blocks], 200);
    }

    #[OA\Post(
        path: '/api/units/{unit}/blocks',
        summary: 'Block a Date Range',
        tags: ['Unit Blocks'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'unit', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['start_date', 'end_date'],
                properties: [
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-11-03'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-11-04'),
                    new OA\Property(property: 'note', type: 'string', example: 'Renovasi')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Date Range Blocked Successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error or date range already booked')
        ]
    )]
    public function store(Request $request, Unit $unit): JsonResponse
    {
        if ($unit->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $validated = $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'note' => 'nullable|string|max:255',
        ]);

        if ($unit->hasOverlap($validated['start_date'], $validated['end_date'])) {
            return response()->json(['message' => 'Tanggal yang dipilih sudah terisi atau diblokir.'], 422);
        }

        $block = $unit->blocks()->create([
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'note' => $validated['note'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Tanggal berhasil diblokir.',
            'block' => $block,
        ], 201);
    }

    #[OA\Delete(
        path: '/api/units/{unit}/blocks/{block}',
        summary: 'Delete a Blocked Date Range',
        tags: ['Unit Blocks'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'unit', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'block', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Block Deleted Successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Block Not Found')
        ]
    )]
    public function destroy(Request $request, Unit $unit, UnitBlock $block): JsonResponse
    {
        if ($unit->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        if ($block->unit_id !== $unit->id) {
            abort(404);
        }

        $block->delete();

        return response()->json(null, 204);
    }
}
