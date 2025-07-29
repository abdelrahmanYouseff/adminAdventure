<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->get();
        return Inertia::render('Customers/Index', [
            'users' => $users,
        ]);
    }

    /**
     * API: Check if a phone number exists in the users table
     */
    public function apiCheckPhone(Request $request)
    {
        $phone = $request->query('phone');
        $exists = false;
        if ($phone) {
            $exists = \App\Models\User::where('phone', $phone)->exists();
        }
        return response()->json(['exists' => $exists]);
    }
}
