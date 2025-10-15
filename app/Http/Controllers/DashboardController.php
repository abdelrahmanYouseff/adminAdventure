<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Invoice;
use App\Models\Package;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totalProducts = Product::count();
        $totalInvoices = Invoice::count();
        $totalPackages = Package::count();

        return Inertia::render('Dashboard', [
            'totalProducts' => $totalProducts,
            'totalInvoices' => $totalInvoices,
            'totalPackages' => $totalPackages,
        ]);
    }
}
