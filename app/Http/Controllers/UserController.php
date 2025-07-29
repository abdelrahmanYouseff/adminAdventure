<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
        $checkType = '';
        $checkValue = '';

        if ($request->has('email') && $request->email) {
            $query->where('email', $request->email);
            $checkType = 'email';
            $checkValue = $request->email;
        }

        if ($request->has('phone') && $request->phone) {
            $query->where('phone', $request->phone);
            $checkType = 'phone';
            $checkValue = $request->phone;
        }

        $user = $query->first();

                if ($user) {
            return response()->json([
                'exists' => true,
                'message' => 'مسجل',
                'check_type' => $checkType,
                'check_value' => $checkValue,
                'can_use' => false,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->full_name ?? $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'country' => $user->country,
                    'address' => $user->address,
                    'created_at' => $user->created_at,
                ],
            ], 200);
        } else {
            return response()->json([
                'exists' => false,
                'message' => 'غير مسجل',
                'check_type' => $checkType,
                'check_value' => $checkValue,
                'can_use' => true,
                'user' => null,
            ], 200);
        }
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
            // Check which name column exists in the database
            $nameColumn = Schema::hasColumn('users', 'full_name') ? 'full_name' : 'name';

            $userData = [
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'country' => $request->country,
                'address' => $request->address,
            ];

            $userData[$nameColumn] = $request->name;

            $user = User::create($userData);

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->full_name ?? $user->name,
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
