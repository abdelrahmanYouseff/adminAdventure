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
        $users = User::query()
            ->whereIn('role', User::STAFF_ROLES)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Create a new app user from the dashboard.
     * POST /users
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'email'         => 'required|email|lowercase|max:255|unique:users,email',
            'phone'         => 'nullable|string|max:20|unique:users,phone',
            'country'       => 'nullable|string|max:100',
            'password'      => 'required|string|min:6|confirmed',
            'role'          => 'required|in:'.implode(',', User::STAFF_ROLES),
        ]);

        User::create([
            'customer_name' => $request->customer_name,
            'email'         => $request->email,
            'phone'         => $request->phone,
            'country'       => $request->country,
            'password'      => $request->password,
            'role'          => $request->role,
        ]);

        return redirect()->route('users')->with('success', 'تم إنشاء المستخدم بنجاح');
    }

    /**
     * Update an existing staff user.
     * PUT/PATCH /users/{user}
     */
    public function update(Request $request, User $user)
    {
        if (! in_array($user->role, User::STAFF_ROLES, true)) {
            return redirect()
                ->route('users')
                ->with('error', 'لا يمكن تعديل هذا المستخدم من هنا.');
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'email' => 'required|email|lowercase|max:255|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:20|unique:users,phone,'.$user->id,
            'country' => 'nullable|string|max:100',
            'password' => 'nullable|string|min:6|confirmed',
            'role' => 'required|in:'.implode(',', User::STAFF_ROLES),
        ], [
            'customer_name.required' => 'الاسم مطلوب.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.unique' => 'البريد الإلكتروني مستخدم مسبقاً.',
            'phone.unique' => 'رقم الهاتف مستخدم مسبقاً.',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
            'role.required' => 'الدور مطلوب.',
        ]);

        $user->customer_name = $validated['customer_name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        $user->country = $validated['country'] ?? null;
        $user->role = $validated['role'];

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return redirect()->route('users')->with('success', 'تم تحديث المستخدم بنجاح');
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
     * Delete a user (web dashboard).
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

    /**
     * Permanently delete a user via API.
     * DELETE /api/users/{id}
     */
    public function apiDestroy(User $user)
    {
        try {
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المستخدم بنجاح',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل حذف المستخدم',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
