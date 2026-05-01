<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRoleRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function updateRole(UpdateRoleRequest $request): JsonResponse
    {
        // Email is validated and sanitized by UpdateRoleRequest (exists:users,email)
        $user = User::where('email', $request->validated()['email'])->first();

        // Role is validated by UpdateRoleRequest (in:user,admin)
        $user->role = $request->validated()['role'];
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully',
            'data' => [
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->role,
            ],
        ]);
    }
}
