<?php

namespace App\Http\Controllers;

use App\Models\CompanyClient;
use Illuminate\Http\Request;

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
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'company_name.required' => 'اسم الشركة مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صالح.',
        ]);

        CompanyClient::create($validated);

        return redirect()
            ->route('customers')
            ->with('success', 'تم إضافة عميل الشركة بنجاح.');
    }

    public function destroy(CompanyClient $companyClient)
    {
        $companyClient->delete();

        return redirect()
            ->route('customers')
            ->with('success', 'تم حذف عميل الشركة.');
    }
}
