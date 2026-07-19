<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = User::query()
            ->where(function ($query) {
                $query->whereNull('role')
                    ->orWhereNotIn('role', User::STAFF_ROLES);
            })
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
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
