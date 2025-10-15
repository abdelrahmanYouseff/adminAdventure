<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Invoice;
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

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
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

        // Create invoice first
        $invoiceData = [
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'amount' => $request->total_amount,
            'status' => $request->status === 'paid' ? 'paid' : 'pending',
            'payment_method' => $request->payment_method,
            'issued_at' => now(),
            'due_date' => now()->addDays(30),
        ];

        // Add user_id only if provided (since it might be required in invoices table)
        if ($request->user_id) {
            $invoiceData['user_id'] = $request->user_id;
        } else {
            // If no user_id, we need to provide a default or skip invoice creation
            // For now, let's use user_id = 1 as default (admin) or skip if not available
            $invoiceData['user_id'] = 1; // Default to admin user
        }

        $invoice = Invoice::create($invoiceData);

        // Create order with invoice_id
        $orderData = [
            'customer_name' => $request->customer_name,
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
            'invoice_id' => $invoice->id,
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
            'message' => 'Order and invoice created successfully',
            'data' => [
                'order' => $order->load(['user', 'invoice']),
                'invoice' => $invoice,
            ],
        ], 201);
    }

    /**
     * API endpoint to create a new order
     */
    public function apiStore(Request $request)
    {
        try {
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

            // Create invoice first
            $invoiceData = [
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'amount' => $request->total_amount,
                'status' => $request->status === 'paid' ? 'paid' : 'pending',
                'payment_method' => $request->payment_method,
                'issued_at' => now(),
                'due_date' => now()->addDays(30),
                'user_id' => $request->user_id ?? 1, // Default to admin user
            ];

            $invoice = Invoice::create($invoiceData);

            // Create order with invoice_id
            $orderData = [
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'invoice_id' => $invoice->id,
                'order_number' => Order::generateOrderNumber(),
                'total_amount' => $request->total_amount,
                'currency' => $request->currency,
                'payment_method' => $request->payment_method,
                'payment_id' => $request->payment_id,
                'status' => $request->status ?? 'pending',
                'items' => $request->items,
                'notes' => $request->notes,
                'user_id' => $request->user_id,
            ];

            $order = Order::create($orderData);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الطلب والفاتورة بنجاح',
                'data' => [
                    'order' => $order->load(['user', 'invoice']),
                    'invoice' => $invoice,
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات المرسلة',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الطلب',
                'error' => $e->getMessage(),
            ], 500);
        }
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

    /**
     * API endpoint to update order status
     */
    public function apiUpdateStatus(Order $order, Request $request)
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,processing,paid,cancelled,refunded',
            ]);

            $order->update([
                'status' => $request->status,
            ]);

            // Update invoice status if invoice exists
            if ($order->invoice_id) {
                $invoice = Invoice::find($order->invoice_id);
                if ($invoice) {
                    $invoiceStatus = 'pending';

                    if ($request->status === 'paid') {
                        $invoiceStatus = 'paid';
                    } elseif (in_array($request->status, ['cancelled', 'refunded'])) {
                        $invoiceStatus = 'cancelled';
                    }

                    $invoice->update(['status' => $invoiceStatus]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة الطلب بنجاح',
                'data' => $order->load(['user', 'invoice']),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في البيانات المرسلة',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث حالة الطلب',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateStatus(Order $order, Request $request)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,paid,cancelled,refunded',
        ]);

        $order->update([
            'status' => $request->status,
        ]);

        // Update invoice status if invoice exists
        if ($order->invoice_id) {
            $invoice = Invoice::find($order->invoice_id);
            if ($invoice) {
                $invoiceStatus = 'pending';

                if ($request->status === 'paid') {
                    $invoiceStatus = 'paid';
                } elseif (in_array($request->status, ['cancelled', 'refunded'])) {
                    $invoiceStatus = 'cancelled';
                }

                $invoice->update(['status' => $invoiceStatus]);
            }
        }

        return redirect()->back()->with('success', 'Order status updated successfully');
    }

    /**
     * Delete an order
     */
    public function destroy(Order $order)
    {
        try {
            $order->delete();

            return redirect()->back()->with('success', 'تم حذف الطلب بنجاح');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'فشل حذف الطلب: ' . $e->getMessage());
        }
    }
}

