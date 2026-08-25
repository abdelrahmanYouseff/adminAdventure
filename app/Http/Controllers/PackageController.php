<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Product;
use App\Support\MediaStorage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PackageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $perPage = (int) $request->query('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15;

        $query = Package::with('products');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('products', function ($productQuery) use ($search) {
                        $productQuery->where('product_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status !== 'all' && in_array($status, ['active', 'inactive'], true)) {
            $query->where('status', $status);
        }

        $packages = $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $statusCounts = [
            'all' => Package::query()->count(),
            'active' => Package::query()->where('status', 'active')->count(),
            'inactive' => Package::query()->where('status', 'inactive')->count(),
        ];

        return Inertia::render('Packages/Index', [
            'packages' => $packages,
            'filters' => [
                'search' => $search,
                'status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all',
                'per_page' => $perPage,
            ],
            'statusCounts' => $statusCounts,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $products = Product::all();
        return Inertia::render('Packages/Create', [
            'products' => $products,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
       // في دالة store:
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // أضف webp هنا
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $data = $request->except('product_ids');

        if ($request->hasFile('image')) {
            $imagePath = MediaStorage::store($request->file('image'), 'packages');
            $data['image'] = $imagePath;
        }

        $package = Package::create($data);
        $package->products()->sync($request->input('product_ids'));

        return redirect()->route('packages.index')->with('success', 'Package created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Package $package)
    {
        $package->load('products');
        return Inertia::render('Packages/Show', [
            'package' => $package,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Package $package)
    {
        $products = Product::all();
        $package->load('products');
        return Inertia::render('Packages/Edit', [
            'package' => $package,
            'products' => $products,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Package $package)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $data = $request->except('product_ids');

        if ($request->hasFile('image')) {
            $oldImage = $package->image;
            $imagePath = MediaStorage::store($request->file('image'), 'packages');
            $data['image'] = $imagePath;
            $package->update($data);
            $package->products()->sync($request->input('product_ids'));
            if ($oldImage && $oldImage !== $imagePath) {
                MediaStorage::delete($oldImage);
            }
        } else {
            $package->update($data);
            $package->products()->sync($request->input('product_ids'));
        }

        return redirect()->route('packages.index')->with('success', 'Package updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Package $package)
    {
        // Detach all products before deleting the package
        $package->products()->detach();

        $oldImage = $package->image;
        $package->delete();
        MediaStorage::delete($oldImage);

        return redirect()->route('packages.index')->with('success', 'Package deleted successfully.');
    }

    /**
     * API: Return all packages with their products as JSON
     */
    public function apiIndex()
    {
        $packages = Package::query()
            ->where('status', 'active')
            ->with([
                'products' => fn ($query) => $query
                    ->storefront()
                    ->with('category'),
            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->filter(fn (Package $package) => $package->products->isNotEmpty())
            ->values();

        return response()->json($packages);
    }
}
