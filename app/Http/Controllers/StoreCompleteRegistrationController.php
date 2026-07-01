<?php

namespace App\Http\Controllers;

use App\Http\Requests\Store\StoreCompleteRegistrationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreCompleteRegistrationController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->profile_completed) {
            return redirect()->to($this->safeRedirect($request->query('redirect')));
        }

        [$firstName, $lastName] = $this->splitName($user->customer_name ?? '');
        $email = $this->isPlaceholderEmail($user->email) ? '' : ($user->email ?? '');

        return Inertia::render('Store/CompleteRegistration', [
            'profile' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $this->normalizePhone($user->phone),
                'email' => $email,
            ],
            'redirect' => $this->safeRedirect($request->query('redirect')),
        ]);
    }

    public function store(StoreCompleteRegistrationRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->fill([
            'customer_name' => trim($request->string('first_name').' '.$request->string('last_name')),
            'email' => $request->string('email')->toString(),
            'profile_completed' => true,
        ]);

        $user->save();

        return redirect()
            ->to($this->safeRedirect($request->input('redirect')))
            ->with('success', 'تم إنشاء حسابك بنجاح');
    }

    private function isPlaceholderEmail(?string $email): bool
    {
        return $email !== null && str_ends_with(strtolower($email), '@phone.adventureksa.com');
    }

    private function safeRedirect(?string $redirect): string
    {
        if (is_string($redirect) && str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')) {
            return $redirect;
        }

        return '/home';
    }

    private function splitName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $name, 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function normalizePhone(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (str_starts_with($digits, '966')) {
            $digits = substr($digits, 3);
        }
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }
}
