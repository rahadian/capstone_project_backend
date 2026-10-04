<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Unit;
use OpenApi\Attributes as OA;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/api/units',
        summary: 'Get List of Units',
        tags: ['Units'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['available', 'rented', 'maintenance']))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Unit List Fetched Successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function index(Request $request)
    {
        $authUser = $request->user();
        $query = Unit::with('owner:id,name,email');
        

        // Note:
        // Owner: Automatically filter by unit they have
        // Tenant: Default only visible unit which has status 'available'
        // Admin: Visible all
        if($authUser->role === 'owner'){
            $query->where('owner_id',$authUser->id);
        }elseif($authUser->role === 'tenant'){
            $query->where('status','available');
        }

        if($request->has('status')){
            $query->where('status',$request->query('status'));
        }

        $limit = $request->input('limit',10);
        $units = $query->paginate($limit);

        return response()->json($units,200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/api/units',
        summary: 'Create New Unit',
        description: 'Create new unit. Only accessible by role owner or admin.',
        security: [['bearerAuth' => []]],
        tags: ['Units'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'address', 'price'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Unit A1 - Deluxe Room'),
                    new OA\Property(property: 'address', type: 'string', example: 'Jl. Raya Bromo No. 12, Surabaya'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 350000.00),
                    new OA\Property(property: 'status', type: 'string', enum: ['available', 'rented', 'maintenance'], example: 'available')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Unit created successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(Request $request)
    {
        $authUser = $request->user();

        if (!in_array($authUser->role,['owner','admin'])){
            return response()->json(['message'=>'Unauthorized access'],403);
        }

        $validatedData = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'required|string',
            'price'   => 'required|numeric|min:0',
            'status'  => 'nullable|string|in:available,rented,maintenance',
        ]);

        $unit = Unit::create([
            'owner_id' => $authUser->id,
            'name'     => $validatedData['name'],
            'address'  => $validatedData['address'],
            'price'    => $validatedData['price'],
            'status'   => $validatedData['status'] ?? 'available',
        ]);

        return response()->json([
            'message' => 'Unit created successfully',
            'unit'    => $unit->load('owner:id,name,email')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/api/units/{id}',
        summary: 'Get Detail Unit',
        tags: ['Units'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Unit Detail Fetched'),
            new OA\Response(response: 404, description: 'Unit Not Found')
        ]
    )]
    public function show(string $id)
    {
        $unit = Unit::with('owner:id,name,email')->findOrFail($id);

        return response()->json($unit, 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/api/units/{id}',
        summary: 'Update Unit',
        description: 'Update unit data. Only accessible by unit owner or admin.',
        security: [['bearerAuth' => []]],
        tags: ['Units'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'address', 'price', 'status'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Unit A1 - Deluxe Room (Renovated)'),
                    new OA\Property(property: 'address', type: 'string', example: 'Jl. Raya Bromo No. 12, Probolinggo'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 400000.00),
                    new OA\Property(property: 'status', type: 'string', enum: ['available', 'rented', 'maintenance'], example: 'available')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Unit updated successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Unit Not Found'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function update(Request $request, string $id)
    {
        $authUser = $request->user();
        $unit = Unit::findOrFail($id);

        // Hanya Admin atau Owner pemilik unit yang bisa mengedit
        if ($authUser->role !== 'admin' && $unit->owner_id !== $authUser->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $validatedData = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'required|string',
            'price'   => 'required|numeric|min:0',
            'status'  => 'required|string|in:available,rented,maintenance',
        ]);

        $unit->update($validatedData);

        return response()->json([
            'message' => 'Unit updated successfully',
            'unit'    => $unit->load('owner:id,name,email')
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/api/units/{id}',
        summary: 'Delete Unit',
        description: 'Delete unit data. Only accessible by unit owner or admin.',
        security: [['bearerAuth' => []]],
        tags: ['Units'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Unit deleted successfully'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Unit Not Found')
        ]
    )]
    public function destroy(Request $request,string $id)
    {
        $authUser = $request->user();
        $unit = Unit::findOrFail($id);

        // Hanya Admin atau Owner pemilik unit yang bisa menghapus
        if ($authUser->role !== 'admin' && $unit->owner_id !== $authUser->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $unit->delete();

        return response()->json([
            'message' => 'Unit deleted successfully'
        ], 200);
    }
}
