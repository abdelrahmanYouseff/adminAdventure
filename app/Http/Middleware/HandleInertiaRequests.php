<?php

namespace App\Http\Middleware;

use App\Http\Controllers\ImpersonationController;
use App\Models\User;
use App\Support\SidebarNavBadges;
use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs(
            'quotations.pdf',
            'invoices.pdf',
            'quotations.pay',
            'quotations.pay.short',
            'orders.pay',
            'payment.return',
            'payment.return.status',
            'payment.success',
            'payment.fail',
            'payment.cancel',
        )) {
            return $next($request);
        }

        if ($request->user()?->canAccessDashboard()) {
            $request->session()->forget(['payment_success', 'open_pdf']);
        }

        return parent::handle($request, $next);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => fn () => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                ] : null,
            ],
            'impersonation' => function () use ($request) {
                $adminId = $request->session()->get(ImpersonationController::SESSION_KEY);

                if (! $adminId || ! $request->user()) {
                    return null;
                }

                $admin = User::query()->find($adminId);

                return [
                    'active' => true,
                    'admin_name' => $admin?->name,
                    'as_name' => $request->user()->name,
                    'as_role' => $request->user()->roleLabel(),
                ];
            },
            'ziggy' => fn () => [
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'sidebarBadges' => fn () => SidebarNavBadges::forUser($request->user()),
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'payment_success' => $request->routeIs('home', 'store.*')
                    ? $request->session()->get('payment_success')
                    : null,
                'open_pdf' => $request->session()->get('open_pdf'),
            ],
            'csrf_token' => fn () => $request->session()->token(),
        ];
    }
}
