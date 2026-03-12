<?php

namespace App\Http\Controllers;

use App\Imports\ProductsImport;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     */
    public function index()
    {
        $products = Product::with('category')->orderBy('created_at', 'desc')->get();
        $categories = Category::all();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create()
    {
        $categories = Category::all();
        return Inertia::render('Products/Create', [
            'categories' => $categories,
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
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        Product::create($data);

        return redirect()->route('products')->with('success', 'Product created successfully!');
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product)
    {
        $categories = Category::all();
        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categories' => $categories,
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
            'description' => 'sometimes|nullable|string',
            'status' => 'sometimes|required|in:active,inactive',
            'category_id' => 'sometimes|required|exists:categories,id',
            'image' => 'sometimes|nullable|image|max:2048',
        ];

        $data = $request->validate($rules);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        } else {
            // Never overwrite existing image unless a new file was explicitly uploaded
            unset($data['image']);
        }

        $product->update($data);

        return response()->json(['success' => true]);
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products')->with('success', 'Product deleted successfully!');
    }

    /**
     * Import products from Excel file.
     * Expected columns: A = اسم المنتج, B = الفئة, C = السعر
     */
    public function import(Request $request)
    {
        $request->validate([
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

            $import = new ProductsImport;
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
        return response()->json(Product::active()->with('category')->get());
    }

    /**
     * API: Return products by category name as JSON
     */
    public function apiByCategory(Request $request)
    {
        $categoryName = $request->query('category_name');
        if (!$categoryName) {
            return response()->json([]);
        }
        $category = \App\Models\Category::where('category_name', $categoryName)->first();
        if (!$category) {
            return response()->json([]);
        }
        $products = Product::active()->where('category_id', $category->id)->get();
        return response()->json($products);
    }

    /**
     * API: Return the latest added products as JSON (limit 10)
     */
    public function apiLatest()
    {
        $products = Product::active()->orderBy('created_at', 'desc')->limit(10)->get();
        return response()->json($products);
    }
}
