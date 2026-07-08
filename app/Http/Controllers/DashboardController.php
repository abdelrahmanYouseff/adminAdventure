<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Quotation;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        if (auth()->user()?->isWorker()) {
            return redirect()->route('worker-orders.index');
        }

        $totalProducts = Product::count();
        $totalInvoices = Invoice::count();
        $totalPackages = Package::count();
        $totalQuotations = Quotation::count();

        return Inertia::render('Dashboard', [
            'totalProducts' => $totalProducts,
            'totalInvoices' => $totalInvoices,
            'totalPackages' => $totalPackages,
            'totalQuotations' => $totalQuotations,
        ]);
    }
}
