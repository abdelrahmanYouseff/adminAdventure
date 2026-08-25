<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Support\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Brands/Index', [
            'brands' => Brand::query()
                ->withCount('products')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateBrand($request);

        if ($request->hasFile('logo')) {
            $data['logo'] = MediaStorage::store($request->file('logo'), 'brands');
        }

        Brand::create($data);

        return back()->with('success', 'تم إضافة البراند بنجاح.');
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $data = $this->validateBrand($request);

        if ($request->hasFile('logo')) {
            $oldLogo = $brand->logo;
            $data['logo'] = MediaStorage::store($request->file('logo'), 'brands');
            $brand->update($data);
            if ($oldLogo && $oldLogo !== $data['logo']) {
                MediaStorage::delete($oldLogo);
            }
        } else {
            $brand->update($data);
        }

        return back()->with('success', 'تم تحديث البراند بنجاح.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->products()->exists()) {
            return back()->with('error', 'لا يمكن حذف براند يحتوي على منتجات. انقل المنتجات أولاً إلى براند آخر.');
        }

        if ($brand->categories()->exists()) {
            return back()->with('error', 'لا يمكن حذف براند يحتوي على أصناف. انقل الأصناف أولاً إلى براند آخر.');
        }

        if ($brand->quotations()->exists()) {
            return back()->with('error', 'لا يمكن حذف براند مرتبط بعروض أسعار.');
        }

        $oldLogo = $brand->logo;
        $brand->delete();
        MediaStorage::delete($oldLogo);

        return back()->with('success', 'تم حذف البراند بنجاح.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBrand(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['required', 'boolean'],
        ]);

        $data['description'] = $data['description'] ?: null;

        return $data;
    }
}
