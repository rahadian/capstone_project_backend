<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

     #[OA\Get(
        path: '/api/list/units',
        summary: 'Get List of Units Without Login',
        tags: ['Units'],
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['all','available', 'rented', 'maintenance']))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Unit List Fetched Successfully'),
        ]
    )]

    public function list_nologin(Request $request)
    {
        $query = Unit::with('owner:id,name,email');

        $status = $request->input('status');

        if ($status==='all'){

        }else if($status){
            $query->where('status',$status);
        }else{
            $query->where('status','available');
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
        description: 'Create new unit. Only accessible by role owner.',
        security: [['bearerAuth' => []]],
        tags: ['Units'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'address', 'price'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Unit A1 - Deluxe Room'),
                    new OA\Property(property: 'type', type: 'string', example: 'Rumah Kontrakan'),
                    new OA\Property(
                        property: 'facilities',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['AC', 'WiFi', 'Kamar Mandi Dalam']
                    ),
                    new OA\Property(
                            property: 'image',
                            description: 'Image Unit (JPG, JPEG, PNG, max 2MB).',
                            type: 'string',
                            format: 'binary'
                    ),
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

        if (!in_array($authUser->role,['owner'])){
            return response()->json(['message'=>'Unauthorized access'],403);
        }

        $validatedData = $request->validate([
            'name'    => 'required|string|max:255',
            'type'    => 'required|string|max:100',
            'facilities'   => 'nullable|array',
            'facilities.*' => 'string',
            'image'   => 'nullable|file|mimes:jpeg,png,jpg,webp|max:2048',
            'address' => 'required|string',
            'price'   => 'required|numeric|min:0',
            'status'  => 'nullable|string|in:available,rented,maintenance',
        ]);

        $imgUrl = null;

        $path = $request->file('image')->store('unit_image', 'public');
        $imgUrl = Storage::disk('public')->url($path);


        $unit = Unit::create([
            'owner_id' => $authUser->id,
            'name'     => $validatedData['name'],
            'type'     => $validatedData['type'],
            'facilities' => $validatedData['facilities'] ?? [],
            'image'    => $imgUrl,
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

    #[OA\Get(
        path: '/api/detail/unit/{id}',
        summary: 'Get Detail Unit Without Login',
        tags: ['Units'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Unit Detail Fetched'),
            new OA\Response(response: 404, description: 'Unit Not Found')
        ]
    )]
    public function show_nologin(string $id)
    {
        $unit = Unit::findOrFail($id);

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
        description: 'Update unit data. Only accessible by unit owner.',
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
                    new OA\Property(property: 'name', type: 'string', example: 'Unit A1 - Deluxe Room'),
                    new OA\Property(property: 'type', type: 'string', example: 'Rumah Kontrakan'),
                    new OA\Property(
                        property: 'facilities',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['AC', 'WiFi', 'Kamar Mandi Dalam']
                    ),
                    new OA\Property(
                            property: 'image',
                            description: 'Image Unit (JPG, JPEG, PNG, max 2MB).',
                            type: 'string',
                            format: 'binary'
                    ),
                    new OA\Property(property: 'address', type: 'string', example: 'Jl. Raya Bromo No. 12, Surabaya'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 350000.00),
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

        // Only Owner can edit their own unit
        if ($unit->owner_id !== $authUser->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $validatedData = $request->validate([
            'name'    => 'required|string|max:255',
            'type'    => 'required|string|max:100',
            'facilities'   => 'nullable|array',
            'facilities.*' => 'string',
            'image'   => 'nullable|file|mimes:jpeg,png,jpg,webp|max:2048',
            'address' => 'required|string',
            'price'   => 'required|numeric|min:0',
            'status'  => 'required|string|in:available,rented,maintenance',
        ]);

        if ($request->hasFile('image')) {
            // Hapus gambar lama jika ada
            if ($unit->image) {
                $oldPath = str_replace(Storage::disk('public')->url(''), '', $unit->image);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('image')->store('unit_image', 'public');
            $validatedData['image'] = Storage::disk('public')->url($path);
        } else {
            // Gunakan gambar yang sudah ada
            unset($validatedData['image']);
        }

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
        description: 'Delete unit data. Only accessible by unit owner.',
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

        // Only Owner can delete their own unit
        if ($unit->owner_id !== $authUser->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $unit->delete();

        return response()->json([
            'message' => 'Unit deleted successfully'
        ], 200);
    }
}
