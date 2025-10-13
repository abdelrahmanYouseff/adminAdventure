<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Rental;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user', 'invoice']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment method
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Search by order number or customer name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('full_name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(15);

        // Get statistics
        $statistics = [
            'total' => Order::count(),
            'paid' => Order::where('status', 'paid')->count(),
            'pending' => Order::where('status', 'pending')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
            'refunded' => Order::where('status', 'refunded')->count(),
            'total_amount' => Order::where('status', 'paid')->sum('total_amount'),
            'pending_amount' => Order::where('status', 'pending')->sum('total_amount'),
        ];

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'statistics' => $statistics,
            'filters' => $request->only(['status', 'payment_method', 'search']),
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['user', 'invoice']);

        return Inertia::render('Orders/Show', [
            'order' => $order,
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:pending,paid,cancelled,refunded',
        ]);

        $order->update([
            'status' => $request->status,
        ]);

        return redirect()->back()->with('success', 'Order status updated successfully.');
    }
}
