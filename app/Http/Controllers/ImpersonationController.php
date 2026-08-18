<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ImpersonationController extends Controller
{
    public const SESSION_KEY = 'impersonator_id';

    /**
     * Admin-only: log in as another staff user without their password.
     */
    public function start(Request $request, User $user): RedirectResponse|Response
    {
        $admin = $request->user();

        if (! $admin?->isAdmin()) {
            return back()->with('error', 'الدخول السريع متاح لحساب الأدمن فقط.');
        }

        if ($request->session()->has(self::SESSION_KEY)) {
            return back()->with('error', 'أنت بالفعل داخل حساب مستخدم آخر. ارجع لحساب الأدمن أولاً.');
        }

        if ($user->id === $admin->id) {
            return back()->with('error', 'لا يمكن الدخول إلى حسابك الحالي.');
        }

        if (! in_array($user->role, User::STAFF_ROLES, true)) {
            return back()->with('error', 'لا يمكن الدخول بهذا المستخدم.');
        }

        $request->session()->put(self::SESSION_KEY, $admin->id);

        Auth::login($user, false);
        $request->session()->regenerate();

        Log::info('Admin impersonated user', [
            'admin_id' => $admin->id,
            'target_id' => $user->id,
            'target_role' => $user->role,
        ]);

        return Inertia::location(route($user->homeRouteName()));
    }

    /**
     * Return to the original admin account.
     */
    public function stop(Request $request): RedirectResponse|Response
    {
        $adminId = $request->session()->pull(self::SESSION_KEY);

        if (! $adminId) {
            return back()->with('error', 'لا يوجد دخول سريع نشط.');
        }

        $admin = User::query()->find($adminId);

        if (! $admin?->isAdmin()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', 'تعذر الرجوع لحساب الأدمن. سجّل الدخول مرة أخرى.');
        }

        Auth::login($admin, false);
        $request->session()->regenerate();

        return Inertia::location(route('users'));
    }

    /**
     * If the current session is an admin impersonation, return to the admin
     * instead of destroying the session on logout.
     */
    public static function interceptLogout(Request $request): RedirectResponse|Response|null
    {
        if (! $request->session()->has(self::SESSION_KEY)) {
            return null;
        }

        return app(self::class)->stop($request);
    }
}
