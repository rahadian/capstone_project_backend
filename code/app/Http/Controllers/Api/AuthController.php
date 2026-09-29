<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
Use App\Models\User;
use OpenApi\Attributes as OA;


class AuthController extends Controller
{
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
