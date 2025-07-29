<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Check if a user exists by email or phone
     */
    public function apiCheckUser(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
        ]);

        $query = User::query();

        if ($request->has('email') && $request->email) {
            $query->where('email', $request->email);
        }

        if ($request->has('phone') && $request->phone) {
            $query->where('phone', $request->phone);
        }

        $user = $query->first();

        return response()->json([
            'exists' => $user !== null,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'country' => $user->country,
                'address' => $user->address,
                'created_at' => $user->created_at,
            ] : null,
        ]);
    }

    /**
     * Register a new user via API
     */
    public function apiRegister(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20|unique:users',
            'country' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
        ]);

        try {
            $user = User::create([
                'full_name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'country' => $request->country,
                'address' => $request->address,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'country' => $user->country,
                    'address' => $user->address,
                    'created_at' => $user->created_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to register user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
