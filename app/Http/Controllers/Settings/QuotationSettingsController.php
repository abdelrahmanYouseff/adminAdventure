<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Support\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuotationSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('QuotationSettings/Index', [
            'brands' => Brand::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'logo', 'is_active']),
        ]);
    }

    public function show(Brand $brand): Response
    {
        return Inertia::render('QuotationSettings/Show', [
            'brand' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'logo_url' => $brand->logo_url,
                'phone' => $brand->contactPhone(),
            ],
        ]);
    }

    public function updateLogo(Request $request, Brand $brand): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'logo.required' => 'اختر صورة اللوجو.',
            'logo.image' => 'اللوجو يجب أن يكون صورة.',
            'logo.mimes' => 'الصيغ المسموحة: jpg, jpeg, png, webp.',
            'logo.max' => 'حجم اللوجو يجب ألا يتجاوز 4 ميجابايت.',
        ]);

        $oldLogo = $brand->logo;
        $newLogo = MediaStorage::store($request->file('logo'), 'brands');

        $brand->update([
            'logo' => $newLogo,
        ]);

        if ($oldLogo && $oldLogo !== $newLogo) {
            MediaStorage::delete($oldLogo);
        }

        return back()->with('success', 'تم تحديث لوجو عرض السعر بنجاح.');
    }

    public function updatePhone(Request $request, Brand $brand): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:80'],
        ], [
            'phone.required' => 'رقم هاتف الشركة مطلوب.',
            'phone.max' => 'رقم الهاتف طويل جداً.',
        ]);

        $brand->update([
            'phone' => trim($validated['phone']),
        ]);

        return back()->with('success', 'تم تحديث رقم هاتف الشركة في عروض الأسعار.');
    }
}
