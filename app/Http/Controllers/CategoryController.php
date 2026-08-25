<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Support\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CategoryController extends Controller
{
    /**
     * Display brands as cards for category management.
     */
    public function index()
    {
        $brands = Brand::query()
            ->withCount('categories')
            ->with([
                'categories' => fn ($query) => $query
                    ->latest()
                    ->limit(4)
                    ->select(['id', 'brand_id', 'category_name', 'image']),
            ])
            ->orderBy('name')
            ->get();

        return Inertia::render('Categories/Index', [
            'brands' => $brands,
        ]);
    }

    /**
     * Display categories for a specific brand.
     */
    public function brand(Brand $brand)
    {
        $brand->load([
            'categories' => fn ($query) => $query
                ->withCount('products')
                ->orderByDesc('created_at'),
        ]);

        return Inertia::render('Categories/Brand', [
            'brand' => $brand,
        ]);
    }

    /**
     * Display the specified category with its products.
     */
    public function show(Category $category)
    {
        $category->load(['products', 'brand']);

        return Inertia::render('Categories/Show', [
            'category' => $category,
        ]);
    }

    /**
     * Show the form for creating a new category.
     */
    public function create(Request $request)
    {
        $defaultBrandId = (int) ($request->query('brand') ?: Brand::default()->id);

        return Inertia::render('Categories/Create', [
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(),
            'defaultBrandId' => $defaultBrandId,
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'category_name')->where(
                    fn ($query) => $query->where('brand_id', $request->input('brand_id'))
                ),
            ],
            'brand_id' => 'required|exists:brands,id',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = MediaStorage::store($request->file('image'), 'categories');
        }

        $category = Category::create($data);
        $brand = Brand::find($category->brand_id);

        return redirect()
            ->route('categories.brand.show', $brand)
            ->with('success', 'تم إنشاء الصنف بنجاح');
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(Category $category)
    {
        return Inertia::render('Categories/Edit', [
            'category' => $category->load('brand'),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'category_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'category_name')
                    ->where(fn ($query) => $query->where('brand_id', $request->input('brand_id')))
                    ->ignore($category->id),
            ],
            'brand_id' => 'required|exists:brands,id',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $oldImage = $category->image;
            $data['image'] = MediaStorage::store($request->file('image'), 'categories');
            $category->update($data);
            if ($oldImage && $oldImage !== $data['image']) {
                MediaStorage::delete($oldImage);
            }
        } else {
            unset($data['image']);
            $category->update($data);
        }

        $brand = Brand::find($category->brand_id);

        return redirect()
            ->route('categories.brand.show', $brand)
            ->with('success', 'تم تحديث الصنف بنجاح');
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category)
    {
        if ($category->products()->count() > 0) {
            return back()->with('error', 'Cannot delete category that has products. Please remove or reassign products first.');
        }

        $brand = $category->brand;
        $oldImage = $category->image;
        $category->delete();
        MediaStorage::delete($oldImage);

        if ($brand) {
            return redirect()
                ->route('categories.brand.show', $brand)
                ->with('success', 'تم حذف الصنف بنجاح');
        }

        return redirect()->route('categories.index')->with('success', 'تم حذف الصنف بنجاح');
    }

    /**
     * Return all categories as JSON for API.
     */
    public function apiIndex()
    {
        $categories = Category::query()
            ->where('brand_id', Brand::storefront()->id)
            ->whereHas('products', fn ($query) => $query->storefront())
            ->orderBy('category_name')
            ->get();

        return response()->json($categories);
    }
}
