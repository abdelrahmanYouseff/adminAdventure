<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * تسجيل عميل فردي من الواجهة العامة — بدون صلاحية لوحة التحكم.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'customer_name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => null,
            'profile_completed' => true,
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        // العملاء يبقون على المتجر فقط — لا يدخلون لوحة التحكم
        return redirect()->route('home');
    }
}
