<?php

namespace App\Http\Controllers;

use App\Imports\ProductsImport;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     */
    public function index()
    {
        $brands = Brand::query()
            ->withCount('products')
            ->with([
                'products' => fn ($query) => $query
                    ->latest()
                    ->limit(4)
                    ->select(['id', 'brand_id', 'product_name', 'image']),
            ])
            ->orderBy('name')
            ->get();

        return Inertia::render('Products/Index', [
            'brands' => $brands,
        ]);
    }

    public function brand(Brand $brand, Request $request)
    {
        $categoryId = $request->query('category');
        $categoryId = is_numeric($categoryId) ? (int) $categoryId : null;

        $categories = Category::query()
            ->where('brand_id', $brand->id)
            ->withCount('products')
            ->orderBy('category_name')
            ->get(['id', 'category_name', 'brand_id']);

        if ($categoryId !== null && ! $categories->contains('id', $categoryId)) {
            $categoryId = null;
        }

        $brand->load([
            'products' => fn ($query) => $query
                ->with('category:id,category_name')
                ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
                ->latest(),
        ]);

        return Inertia::render('Products/Brand', [
            'brand' => $brand,
            'categories' => $categories,
            'selectedCategoryId' => $categoryId,
            'totalProducts' => Product::query()->where('brand_id', $brand->id)->count(),
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(Request $request)
    {
        $categories = Category::query()->orderBy('category_name')->get(['id', 'category_name', 'brand_id']);
        $defaultBrandId = (int) ($request->query('brand') ?: Brand::default()->id);

        return Inertia::render('Products/Create', [
            'categories' => $categories,
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(),
            'defaultBrandId' => $defaultBrandId,
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'insurance_amount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where('brand_id', $request->input('brand_id'))
                ),
            ],
            'brand_id' => 'required|exists:brands,id',
            'image' => 'nullable|image|max:2048',
        ], [
            'category_id.exists' => 'الصنف المختار لا ينتمي إلى البراند المحدد.',
        ]);

        $data['insurance_amount'] = $data['insurance_amount'] ?? 0;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        Product::create($data);

        return redirect()->route('products')->with('success', 'تم إضافة المنتج بنجاح');
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product)
    {
        $categories = Category::query()->orderBy('category_name')->get(['id', 'category_name', 'brand_id']);

        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categories' => $categories,
            'brands' => Brand::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Toggle product status between active and inactive.
     * Only changes the status field — image and all other data are untouched.
     * PATCH /products/{product}/toggle-status
     */
    public function toggleStatus(Product $product)
    {
        $product->update([
            'status' => $product->status === 'active' ? 'inactive' : 'active',
        ]);

        return back();
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, Product $product)
    {
        $rules = [
            'product_name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric',
            'insurance_amount' => 'sometimes|nullable|numeric|min:0',
            'description' => 'sometimes|nullable|string',
            'status' => 'sometimes|required|in:active,inactive',
            'category_id' => [
                'sometimes',
                'required',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where('brand_id', $request->input('brand_id', $product->brand_id))
                ),
            ],
            'brand_id' => 'sometimes|required|exists:brands,id',
            'image' => 'sometimes|nullable|image|max:2048',
        ];

        $data = $request->validate($rules, [
            'category_id.exists' => 'الصنف المختار لا ينتمي إلى البراند المحدد.',
        ]);

        if (array_key_exists('insurance_amount', $data) && $data['insurance_amount'] === null) {
            $data['insurance_amount'] = 0;
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        } else {
            // Never overwrite existing image unless a new file was explicitly uploaded
            unset($data['image']);
        }

        $product->update($data);

        return redirect()->route('products')->with('success', 'تم تعديل المنتج بنجاح');
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products')->with('success', 'تم حذف المنتج بنجاح');
    }

    /**
     * Import products from Excel file.
     * Expected columns: A = اسم المنتج, B = الفئة, C = السعر
     */
    public function import(Request $request)
    {
        $request->validate([
            'brand_id' => ['nullable', 'exists:brands,id'],
            'file' => [
                'required',
                'file',
                'max:10240',
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    $allowed = ['xlsx', 'xls', 'csv'];
                    if (! in_array($ext, $allowed, true)) {
                        $fail('الملف يجب أن يكون Excel (.xlsx, .xls) أو CSV.');
                    }
                },
            ],
        ]);

        try {
            $file = $request->file('file');
            $path = $file->getRealPath();
            $extension = $file->getClientOriginalExtension();

            $brandId = (int) ($request->input('brand_id') ?: Brand::default()->id);
            $import = new ProductsImport($brandId);
            $import->importFromPath($path, $extension);

            $imported = $import->getImportedCount();
            $errors = $import->getErrors();

            if ($imported > 0 && empty($errors)) {
                return redirect()->route('products')->with('success', "تم استيراد {$imported} منتج بنجاح.");
            }
            if ($imported > 0 && ! empty($errors)) {
                return redirect()->route('products')->with('success', "تم استيراد {$imported} منتج. تحذيرات: " . implode(' ', array_slice($errors, 0, 3)));
            }
            if (! empty($errors)) {
                return redirect()->route('products')->with('error', 'فشل الاستيراد: ' . implode(' ', array_slice($errors, 0, 5)));
            }

            return redirect()->route('products')->with('error', 'لم يتم استيراد أي منتج. تأكد من صيغة الملف (العمود أ: اسم المنتج، ب: الفئة، ج: السعر).');
        } catch (\Throwable $e) {
            report($e);
            $msg = $e->getMessage();
            $class = get_class($e);
            return redirect()->route('products')->with('error', 'حدث خطأ أثناء الاستيراد: ' . $msg . ' (' . $class . ')');
        }
    }

    /**
     * API: Return all products as JSON
     */
    public function apiIndex()
    {
        $products = Product::storefront()
            ->with('category')
            ->orderBy('product_name')
            ->get();

        return response()->json($products);
    }

    /**
     * API: Return products by category name as JSON
     */
    public function apiByCategory(Request $request)
    {
        $categoryId = $request->query('category_id');
        $categoryName = $request->query('category_name');

        if (! $categoryId && ! $categoryName) {
            return response()->json([]);
        }

        $categoryQuery = Category::query()->where('brand_id', Brand::storefront()->id);

        if ($categoryId) {
            $category = $categoryQuery->where('id', $categoryId)->first();
        } else {
            $category = $categoryQuery->where('category_name', $categoryName)->first();
        }

        if (! $category) {
            return response()->json([]);
        }

        $products = Product::storefront()
            ->with('category')
            ->where('category_id', $category->id)
            ->orderBy('product_name')
            ->get();

        return response()->json($products);
    }

    /**
     * API: Return the latest added products as JSON (limit 10)
     */
    public function apiLatest()
    {
        $products = Product::storefront()
            ->with('category')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json($products);
    }
}
