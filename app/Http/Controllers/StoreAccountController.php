<?php

namespace App\Http\Controllers;

use App\Http\Requests\Store\StoreAccountUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreAccountController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        [$firstName, $lastName] = $this->splitName($user->customer_name ?? '');

        return Inertia::render('Store/Account', [
            'profile' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $this->normalizePhone($user->phone),
                'email' => $user->email,
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'gender' => $user->gender,
            ],
        ]);
    }

    public function update(StoreAccountUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->fill([
            'customer_name' => trim($request->string('first_name').' '.$request->string('last_name')),
            'phone' => $request->string('phone')->toString(),
            'email' => $request->string('email')->toString(),
            'date_of_birth' => $request->input('date_of_birth'),
            'gender' => $request->input('gender'),
        ]);

        $user->save();

        return redirect()->route('store.account')->with('success', 'تم حفظ بياناتك بنجاح');
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
