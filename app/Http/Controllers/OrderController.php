<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user', 'invoice']);

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('payment_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('full_name', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Status filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Payment method filter
        if ($request->has('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        // Currency filter
        if ($request->has('currency') && $request->currency !== 'all') {
            $query->where('currency', $request->currency);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(15);

        // Calculate statistics
        $statistics = [
            'total' => Order::count(),
            'paid' => Order::where('status', 'paid')->count(),
            'pending' => Order::where('status', 'pending')->count(),
            'processing' => Order::where('status', 'processing')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
            'refunded' => Order::where('status', 'refunded')->count(),
            'total_amount' => Order::where('status', 'paid')->sum('total_amount'),
            'pending_amount' => Order::where('status', 'pending')->sum('total_amount'),
        ];

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'statistics' => $statistics,
            'filters' => [
                'search' => $request->search ?? '',
                'status' => $request->status ?? 'all',
                'payment_method' => $request->payment_method ?? 'all',
                'currency' => $request->currency ?? 'all',
            ],
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['user', 'invoice']);

        return Inertia::render('Orders/Show', [
            'order' => $order,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'total_amount' => 'required|numeric|min:0',
            'currency' => 'required|string|in:SAR,USD,EUR',
            'payment_method' => 'required|string|in:credit_card,cash,bank_transfer,paypal,noon',
            'payment_id' => 'nullable|string|max:255',
            'status' => 'sometimes|string|in:pending,processing,paid,cancelled,refunded',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $orderData = [
            'customer_name' => $request->customer_name,
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
            'order_number' => Order::generateOrderNumber(),
            'total_amount' => $request->total_amount,
            'currency' => $request->currency,
            'payment_method' => $request->payment_method,
            'payment_id' => $request->payment_id,
            'status' => $request->status ?? 'pending',
            'items' => $request->items,
            'notes' => $request->notes,
        ];

        // Add user_id only if provided
        if ($request->user_id) {
            $orderData['user_id'] = $request->user_id;
        }

        $order = Order::create($orderData);

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => $order->load(['user']),
        ], 201);
    }

    public function apiIndex(Request $request)
    {
        $query = Order::with(['user', 'invoice']);

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('payment_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('full_name', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Status filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Payment method filter
        if ($request->has('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        // Currency filter
        if ($request->has('currency') && $request->currency !== 'all') {
            $query->where('currency', $request->currency);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function apiShow(Order $order)
    {
        $order->load(['user', 'invoice']);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    public function updateStatus(Order $order, Request $request)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,paid,cancelled,refunded',
        ]);

        $order->update([
            'status' => $request->status,
        ]);

        return redirect()->back()->with('success', 'Order status updated successfully');
    }
}

