<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SocialMediaController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->session()->get('social_media_authenticated') === true) {
            return redirect()->route('social-media.index');
        }

        return Inertia::render('SocialMedia/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $configured = (string) config('social_media.password');

        if ($configured === '') {
            throw ValidationException::withMessages([
                'password' => 'بوابة السوشيال ميديا غير مفعّلة. تواصل مع الإدارة.',
            ]);
        }

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ], [
            'password.required' => 'كلمة المرور مطلوبة.',
        ]);

        if (! hash_equals($configured, $validated['password'])) {
            throw ValidationException::withMessages([
                'password' => 'كلمة المرور غير صحيحة.',
            ]);
        }

        $request->session()->put('social_media_authenticated', true);

        return redirect()->route('social-media.index');
    }

    public function index(Request $request): Response
    {
        $perPage = 12;

        $orders = Order::query()
            ->whereHas('workerOrders', function ($query) {
                $query->where(function ($inner) {
                    $inner->whereNotNull('installation_photo')
                        ->orWhereNotNull('pickup_photo');
                });
            })
            ->with(['workerOrders' => function ($query) {
                $query->orderBy('line_index')
                    ->where(function ($inner) {
                        $inner->whereNotNull('installation_photo')
                            ->orWhereNotNull('pickup_photo');
                    });
            }])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Order $order) => [
                'order_number' => $order->order_number,
                'photos' => $order->workerOrders
                    ->flatMap(function ($line) {
                        $urls = [];
                        if ($line->installation_photo_url) {
                            $urls[] = $line->installation_photo_url;
                        }
                        if ($line->pickup_photo_url) {
                            $urls[] = $line->pickup_photo_url;
                        }

                        return $urls;
                    })
                    ->values()
                    ->all(),
            ]);

        return Inertia::render('SocialMedia/Index', [
            'orders' => $orders,
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('social_media_authenticated');

        return redirect()->route('social-media.login');
    }
}
