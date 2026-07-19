<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\User;
use App\Services\SiteVisitService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(SiteVisitService $siteVisitService)
    {
        $user = auth()->user();

        if ($user?->hasAnyRole(User::ROLE_WORKER, User::ROLE_ACCOUNTS)) {
            return redirect()->route($user->homeRouteName());
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
            'visitorStats' => $siteVisitService->summaryStats(),
            'visitorsByCountry' => $siteVisitService->visitorsByCountry(),
        ]);
    }
}
