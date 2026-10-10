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
Use DB;
Use Storage;
Use App\Models\Tenant;
use OpenApi\Attributes as OA;


class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/register',
        summary: 'User Registration',
        description: 'Register a new user (Owner or Tenant). If role is tenant, a tenant profile record is automatically created.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Budi Santoso'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'budi@example.com'),
                    new OA\Property(property: 'phone', type: 'string', maxLength: 20, example: '081234567890'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'P@ssw0rd123!'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', minLength: 8, example: 'P@ssw0rd123!'),
                    new OA\Property(property: 'role', type: 'string', enum: ['owner', 'tenant'], example: 'tenant'),
                    new OA\Property(
                            property: 'id_card',
                            description: 'File KTP (JPG, JPEG, PNG, PDF max 2MB). Only for tenant role.',
                            type: 'string',
                            format: 'binary'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Registration Successful'),
            new OA\Response(response: 422, description: 'Validation Error')
        ]
    )]
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|max:255|unique:users',
            'phone'                 => 'nullable|string|max:20',
            'password'              => ['required', 'string', 'confirmed',
                                            Password::min(8)->letters()->mixedCase()->numbers()->symbols()
                                        ],
            'password_confirmation' => 'required|string|min:8',
            'role'                  => 'required|string|in:owner,tenant',
            'id_card'               => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'ktp_data_json'         => 'nullable|json',
        ]);

        $user = DB::transaction(function () use ($request, $validatedData) {
            $newUser = User::create([
                'name'     => $validatedData['name'],
                'email'    => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'role'     => $validatedData['role'],
            ]);

            if ($validatedData['role'] === 'tenant') {
                $idCardUrl = null;
                if (!$request->hasFile('id_card')) {
                    throw ValidationException::withMessages([
                        'id_card' => ['The ID Card is required for tenant role.'],
                    ]);
                }else{
                    $path = $request->file('id_card')->store('id_cards', 'public');
                    $idCardUrl = Storage::disk('public')->url($path);
                }

                $ktpData = $request->filled('ktp_data_json')
                ? json_decode($request->input('ktp_data_json'), true)
                : null;

                Tenant::create([
                    'user_id' => $newUser->id,
                    'name'    => $validatedData['name'],
                    'phone'   => $validatedData['phone'] ?? null,
                    'id_card_url' => $idCardUrl,
                    'ktp_data_json' => $ktpData,
                ]);
            }

            return $newUser;
        });


        $token = auth('api')->login($user);

        return response()->json([
            'message'      => 'Registration successful',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
            'user'         => $user->load('tenant')
        ], 201);
    }

    #[OA\Post(
        path: '/api/login',
        summary: 'User Login',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'admin@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'password123')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login successfully'),
            new OA\Response(response: 422, description: 'Validation failed')
        ]
    )]
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!$token = auth('api')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = auth('api')->user();

        return response()->json([
            'message'      => 'Login successfully',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
            'user'         => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ]
        ], 200);
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'User Logout',
        tags: ['Auth'],
        security: [
            'bearerAuth' => []
        ],
        responses: [
            new OA\Response(response: 200, description: 'Logout successful'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]

    public function logout(Request $request)
    {
        auth('api')->logout();

        return response()->json([
            'message' => 'Logout successful',
        ], 200);
    }


     #[OA\Get(
          path: '/api/me',
          tags: ['Auth'],
          summary: 'Get User Profile',
          security: [
              'bearerAuth' => []
          ],
          responses: [
              new OA\Response(response: 200, description: 'Data User Logged In'),
              new OA\Response(response: 401, description: 'Unauthenticated')
          ]
      )]

    public function me(Request $request)
    {
        $user = auth('api')->user();
        if($user->role === 'tenant'){
            $user->load('tenant');
        }
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        return response()->json($user, 200);
    }

    public function refresh(Request $request)
    {
        $token = auth('api')->refresh();
        return response()->json([
            'message'      => 'Token refreshed successfully',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
        ], 200);
    }
}
