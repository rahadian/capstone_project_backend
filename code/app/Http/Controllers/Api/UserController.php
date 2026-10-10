<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
Use App\Models\User;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    // #[OA\Get(
    //     path: '/api/users',
    //     tags: ['Users'],
    //     summary: 'Get User List',
    //     security: [['bearerAuth' => []]],
    //     responses: [
    //         new OA\Response(response: 200, description: 'User List'),
    //         new OA\Response(response: 401, description: 'Unauthenticated'),
    //         new OA\Response(response: 403, description: 'Forbidden')
    //     ]
    // )]
    // /**
    //  * Display a listing of the resource.
    //  */
    // public function index(Request $request)
    // {
    //     $authUser = $request->user();
    //     if (in_array($authUser->role, ['owner'])) {
    //         $limit = $request->input('limit', 10);
    //         $users = User::paginate($limit);
    //         return response()->json($users, 200);

    //     }else{
    //         return response()->json(['message' => 'Unauthorized access'], 403);
    //     }


    // }

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
    // #[OA\Post(
    //     path: '/api/users',
    //     summary: 'Create New User',
    //     description: 'Create new user. If role is tenant, a tenant profile record is automatically created.',
    //     security: [['bearerAuth' => []]],
    //     tags: ['Users'],
    //     requestBody: new OA\RequestBody(
    //         required: true,
    //         content: new OA\JsonContent(
    //             required: ['name', 'email', 'password', 'password_confirmation', 'role'],
    //             properties: [
    //                 new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Budi Santoso'),
    //                 new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'budi@example.com'),
    //                 new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'P@ssw0rd123!'),
    //                 new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', minLength: 8, example: 'P@ssw0rd123!'),
    //                 new OA\Property(property: 'role', type: 'string', enum: ['admin', 'owner', 'tenant'], example: 'tenant')
    //             ]
    //         )
    //     ),
    //     responses: [
    //         new OA\Response(
    //             response: 201,
    //             description: 'User created successfully',
    //             content: new OA\JsonContent(
    //                 properties: [
    //                     new OA\Property(property: 'message', type: 'string', example: 'User created successfully'),
    //                     new OA\Property(
    //                         property: 'user',
    //                         type: 'object',
    //                         properties: [
    //                             new OA\Property(property: 'id', type: 'integer', example: 1),
    //                             new OA\Property(property: 'name', type: 'string', example: 'Budi Santoso'),
    //                             new OA\Property(property: 'email', type: 'string', example: 'budi@example.com'),
    //                             new OA\Property(property: 'role', type: 'string', example: 'tenant'),
    //                             new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-09-27T09:54:27.000000Z'),
    //                             new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-09-27T09:54:27.000000Z')
    //                         ]
    //                     )
    //                 ]
    //             )
    //         ),
    //         new OA\Response(
    //             response: 401,
    //             description: 'Unauthenticated',
    //             content: new OA\JsonContent(
    //                 properties: [
    //                     new OA\Property(property: 'status', type: 'string', example: 'error'),
    //                     new OA\Property(property: 'message', type: 'string', example: 'Token not valid.')
    //                 ]
    //             )
    //         ),
    //         new OA\Response(
    //             response: 403,
    //             description: 'Forbidden',
    //             content: new OA\JsonContent(
    //                 properties: [
    //                     new OA\Property(property: 'message', type: 'string', example: 'Unauthorized access')
    //                 ]
    //             )
    //         ),
    //         new OA\Response(
    //             response: 422,
    //             description: 'Unprocessable Entity',
    //             content: new OA\JsonContent(
    //                 properties: [
    //                     new OA\Property(property: 'message', type: 'string', example: 'The email has already been taken.'),
    //                     new OA\Property(
    //                         property: 'errors',
    //                         type: 'object',
    //                         properties: [
    //                             new OA\Property(
    //                                 property: 'email',
    //                                 type: 'array',
    //                                 items: new OA\Items(type: 'string', example: 'The email has already been taken.')
    //                             )
    //                         ]
    //                     )
    //                 ]
    //             )
    //         )
    //     ]
    // )]

    // public function store(Request $request)
    // {
    //     $authUser = $request->user();

    //     $validatedData = $request->validate([
    //         'name' => 'required|string|max:255',
    //         'email' => 'required|string|email|max:255|unique:users',
    //         'password' => ['required','string','confirmed',
    //             Password::min(8)
    //                 ->letters()
    //                 ->mixedCase()
    //                 ->numbers()
    //                 ->symbols()
    //         ],
    //         'password_confirmation' => 'required|string|min:8',
    //         'role' => 'required|string|in:admin,owner,tenant',
    //     ]);

    //     $user = DB::transaction(function () use ($validatedData) {
    //         $newUser = User::create([
    //             'name'     => $validatedData['name'],
    //             'email'    => $validatedData['email'],
    //             'password' => Hash::make($validatedData['password']),
    //             'role'     => $validatedData['role'],
    //         ]);

    //         if ($validatedData['role'] === 'tenant') {
    //             Tenant::create([
    //                 'user_id' => $newUser->id,
    //                 'name'    => $validatedData['name'],
    //                 'phone'   => $validatedData['phone'] ?? null,
    //             ]);
    //         }

    //         return $newUser;
    //     });

    //     return response()->json([
    //         'message'   => 'User created successfully',
    //         'user'      => $user
    //     ], 201);
    // }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
        path: '/api/users/{id}',
        summary: 'Update Data User',
        description: 'Change or update data user based on ID.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'User ID will be updated',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Budi Santoso Updated'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'budi_new@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'P@ssw0rdNew123!'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', minLength: 8, example: 'P@ssw0rdNew123!'),
                    new OA\Property(property: 'phone', type: 'string', maxLength: 20, example: '081234567890')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'User updated successfully'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Budi Santoso Updated'),
                                new OA\Property(property: 'email', type: 'string', example: 'budi_new@example.com'),
                                new OA\Property(property: 'phone', type: 'string', example: '081234567890'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-09-27T09:54:27.000000Z'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-09-27T10:15:00.000000Z')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Token not valid.')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized access')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Not Found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No query results for model [App\\Models\\User] 1')
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Unprocessable Entity',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The email has already been taken.'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'email',
                                    type: 'array',
                                    items: new OA\Items(type: 'string', example: 'The email has already been taken.')
                                )
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function update(Request $request, string $id)
    {
        $authUser = $request->user();


        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'password' => ['required','string','confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
            'password_confirmation' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = User::findOrFail($id);
        $user->update([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
        ]);
        if ($validatedData['role'] === 'tenant') {
            $tenant = $user->tenant;
                // Update the existing tenant profile
                $tenant->update([
                    'name'  => $validatedData['name'],
                    'phone' => $validatedData['phone'] ?? null,
                ]);
        }

        return response()->json([
            'message'   => 'User updated successfully',
            'user'      => $user
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    // #[OA\Delete(
    //     path: '/api/users/{id}',
    //     summary: 'Delete User',
    //     description: 'Delete user data based on ID. Only can accessed by role owner.',
    //     security: [['bearerAuth' => []]],
    //     tags: ['Users'],
    //     parameters: [
    //         new OA\Parameter(
    //             name: 'id',
    //             in: 'path',
    //             required: true,
    //             description: 'User ID will be deleted',
    //             schema: new OA\Schema(type: 'integer', example: 1)
    //         )
    //     ],
    //     responses: [
    //         new OA\Response(
    //             response: 200,
    //             description: 'User deleted successfully',
    //             content: new OA\JsonContent(
    //                 properties: [
    //                     new OA\Property(property: 'message', type: 'string', example: 'User deleted successfully')
    //                 ]
    //             )
    //         ),
    //         new OA\Response(
    //             response: 401,
    //             description: 'Unauthenticated',
    //             content: new OA\JsonContent(
    //                 properties: [
    //                     new OA\Property(property: 'status', type: 'string', example: 'error'),
    //                     new OA\Property(property: 'message', type: 'string', example: 'Token not valid.')
    //                 ]
    //             )
    //         ),
    //         new OA\Response(
    //             response: 403,
    //             description: 'Forbidden',
    //             content: new OA\JsonContent(
    //                 properties: [
    //                     new OA\Property(property: 'message', type: 'string', example: 'Unauthorized access')
    //                 ]
    //             )
    //         ),
    //         new OA\Response(
    //             response: 404,
    //             description: 'Not Found',
    //             content: new OA\JsonContent(
    //                 properties: [
    //                     new OA\Property(property: 'message', type: 'string', example: 'No query results for model [App\\Models\\User] 1')
    //                 ]
    //             )
    //         )
    //     ]
    // )]

    // public function destroy(Request $request,string $id)
    // {
    //     $authUser = $request->user();

    //     if ($authUser->role !== 'owner') {
    //         return response()->json(['message' => 'Unauthorized access'], 403);
    //     }

    //     $user = User::findOrFail($id);
    //     $user->delete();

    //     return response()->json([
    //         'message' => 'User deleted successfully'
    //     ], 200);
    // }
}
