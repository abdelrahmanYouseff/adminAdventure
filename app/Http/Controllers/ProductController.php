<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
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
     * API: Return all products as JSON
     */
    public function apiIndex()
    {
        return response()->json(Product::all());
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
        $products = \App\Models\Product::where('category_id', $category->id)->get();
        return response()->json($products);
    }

    /**
     * API: Return the latest added products as JSON (limit 10)
     */
    public function apiLatest()
    {
        $products = Product::orderBy('created_at', 'desc')->limit(10)->get();
        return response()->json($products);
    }
}
