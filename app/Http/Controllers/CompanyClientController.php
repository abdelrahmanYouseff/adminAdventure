<?php

namespace App\Http\Controllers;

use App\Models\CompanyClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyClientController extends Controller
{
    public function index()
    {
        return redirect()->route('customers');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'phone_secondary' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'iban' => ['nullable', 'string', 'max:34'],
            'iban_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'company_name.required' => 'اسم الشركة مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صالح.',
            'iban_image.image' => 'ملف الآيبان يجب أن يكون صورة.',
            'iban_image.mimes' => 'صيغ صورة الآيبان المسموحة: jpg, jpeg, png, webp.',
            'iban_image.max' => 'حجم صورة الآيبان يجب ألا يتجاوز 5 ميجابايت.',
        ]);

        if (! empty($validated['iban'])) {
            $validated['iban'] = strtoupper(preg_replace('/\s+/', '', $validated['iban']) ?? '');
        }

        if ($request->hasFile('iban_image')) {
            $validated['iban_image'] = $request->file('iban_image')->store('customer-ibans', 'public');
        } else {
            unset($validated['iban_image']);
        }

        CompanyClient::create($validated);

        return redirect()
            ->route('customers')
            ->with('success', 'تم إضافة عميل الشركة بنجاح.');
    }

    public function destroy(CompanyClient $companyClient)
    {
        return app(CustomerController::class)->destroy('company', $companyClient->id);
    }
}
