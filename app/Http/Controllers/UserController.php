<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
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
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'country' => $user->country,
                'address' => $user->address,
                'created_at' => $user->created_at,
            ] : null,
        ]);
    }
}
