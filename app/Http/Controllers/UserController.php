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
     * Get user by phone number
     */
    public function getUserByPhone(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $phoneNumber = $request->phone;
        $phoneWithPlus = $phoneNumber;
        $phoneWithoutPlus = $phoneNumber;

        // If phone doesn't start with +, add it
        if (!str_starts_with($phoneNumber, '+')) {
            $phoneWithPlus = '+' . $phoneNumber;
        }

        // If phone starts with +, remove it for comparison
        if (str_starts_with($phoneNumber, '+')) {
            $phoneWithoutPlus = substr($phoneNumber, 1);
        }

        // Search for phone with both formats
        $user = User::where(function($q) use ($phoneWithPlus, $phoneWithoutPlus) {
            $q->where('phone', $phoneWithPlus)
              ->orWhere('phone', $phoneWithoutPlus)
              ->orWhere('phone', '+' . $phoneWithoutPlus);
        })->first();

        if ($user) {
            return response()->json([
                'success' => true,
                'message' => 'المستخدم موجود',
                'user' => [
                    'id' => $user->id,
                    'customer_name' => $user->customer_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'country' => $user->country,
                    'created_at' => $user->created_at,
                ],
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير موجود',
                'user' => null,
            ], 404);
        }
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
            // Handle phone number with or without + prefix
            $phoneNumber = $request->phone;
            $phoneWithPlus = $phoneNumber;
            $phoneWithoutPlus = $phoneNumber;

            // If phone doesn't start with +, add it
            if (!str_starts_with($phoneNumber, '+')) {
                $phoneWithPlus = '+' . $phoneNumber;
            }

            // If phone starts with +, remove it for comparison
            if (str_starts_with($phoneNumber, '+')) {
                $phoneWithoutPlus = substr($phoneNumber, 1);
            }

            // Search for phone with both formats
            $query->where(function($q) use ($phoneWithPlus, $phoneWithoutPlus) {
                $q->where('phone', $phoneWithPlus)
                  ->orWhere('phone', $phoneWithoutPlus)
                  ->orWhere('phone', '+' . $phoneWithoutPlus);
            });

            $checkType = 'phone';
            $checkValue = $request->phone;
        }

        $user = $query->first();

        if ($user) {
            return response()->json([
                'exists' => true,
                'message' => 'المستخدم موجود',
                'check_type' => $checkType,
                'check_value' => $checkValue,
                'user' => [
                    'id' => $user->id,
                    'customer_name' => $user->customer_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'country' => $user->country,
                    'created_at' => $user->created_at,
                ],
            ], 200);
        } else {
            return response()->json([
                'exists' => false,
                'message' => 'المستخدم غير موجود',
                'check_type' => $checkType,
                'check_value' => $checkValue,
                'user' => null,
            ], 404);
        }
    }

    /**
     * Login user via API
     */
    public function apiLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        try {
            // Find user by email
            $user = User::where('email', $request->email)->first();

            // Check if user exists and password is correct
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة',
                ], 401);
            }

            // Return user data
            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الدخول بنجاح',
                'user' => [
                    'id' => $user->id,
                    'customer_name' => $user->customer_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'country' => $user->country,
                    'created_at' => $user->created_at,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل الدخول',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Register a new user via API
     */
    public function apiRegister(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:20|unique:users',
            'country' => 'nullable|string|max:100',
        ]);

        try {
            $user = User::create([
                'customer_name' => $request->customer_name,
                'email' => $request->email,
                'password' => $request->password,
                'phone' => $request->phone,
                'country' => $request->country,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل المستخدم بنجاح',
                'user' => [
                    'id' => $user->id,
                    'customer_name' => $user->customer_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'country' => $user->country,
                    'created_at' => $user->created_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل تسجيل المستخدم',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a user
     */
    public function destroy(User $user)
    {
        try {
            $user->delete();

            return redirect()->back()->with('success', 'تم حذف المستخدم بنجاح');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'فشل حذف المستخدم');
        }
    }
}
