<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRoleRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function updateRole(UpdateRoleRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        $user->role = $request->role;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully',
            'data' => [
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->role
            ]
        ]);
    }
}
