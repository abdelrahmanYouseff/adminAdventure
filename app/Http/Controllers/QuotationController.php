<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class QuotationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $quotations = Quotation::with(['user', 'items'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return Inertia::render('Quotations/Index', [
                'quotations' => $quotations,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in quotation index: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to load quotations.']);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $products = Product::where('status', 'active')->get();

        return Inertia::render('Quotations/Create', [
            'products' => $products,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string',
            'valid_until' => 'required|date|after:today',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $quotation = Quotation::create([
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'customer_address' => $request->customer_address,
                'valid_until' => $request->valid_until,
                'notes' => $request->notes,
                'user_id' => auth()->id(),
            ]);

            $subtotal = 0;
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                $totalPrice = $item['quantity'] * $item['unit_price'];
                $subtotal += $totalPrice;

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $product->product_name,
                    'description' => $product->description,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $totalPrice,
                ]);
            }

            $taxAmount = $subtotal * 0.15; // 15% tax
            $totalAmount = $subtotal + $taxAmount;

            $quotation->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
            ]);

            DB::commit();

            return redirect()->route('quotations.pdf', $quotation)
                ->with('success', 'Quotation created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create quotation.']);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Quotation $quotation)
    {
        try {
            // Redirect to PDF instead of showing the web page
            return redirect()->route('quotations.pdf', $quotation);
        } catch (\Exception $e) {
            Log::error('Error in quotation show: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to load quotation.']);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Quotation $quotation)
    {
        $quotation->load(['items.product']);
        $products = Product::where('status', 'active')->get();

        return Inertia::render('Quotations/Edit', [
            'quotation' => $quotation,
            'products' => $products,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Quotation $quotation)
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string',
            'valid_until' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $quotation->update([
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'customer_address' => $request->customer_address,
                'valid_until' => $request->valid_until,
                'notes' => $request->notes,
            ]);

            // Delete existing items
            $quotation->items()->delete();

            $subtotal = 0;
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                $totalPrice = $item['quantity'] * $item['unit_price'];
                $subtotal += $totalPrice;

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $product->product_name,
                    'description' => $product->description,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $totalPrice,
                ]);
            }

            $taxAmount = $subtotal * 0.15; // 15% tax
            $totalAmount = $subtotal + $taxAmount;

            $quotation->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
            ]);

            DB::commit();

            return redirect()->route('quotations.pdf', $quotation)
                ->with('success', 'Quotation updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to update quotation.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Quotation $quotation)
    {
        try {
            $quotation->delete();
            return redirect()->route('quotations.index')
                ->with('success', 'Quotation deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete quotation.']);
        }
    }

    /**
     * Generate PDF for the quotation
     */
    public function generatePdf(Quotation $quotation)
    {
        $quotation->load(['user', 'items.product']);

        $pdf = PDF::loadView('quotation-pdf', [
            'quotation' => $quotation,
        ]);

        // Set PDF options for Arabic support
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'Arial',
            'chroot' => public_path(),
            'defaultPaperSize' => 'a4',
            'dpi' => 150,
            'fontHeightRatio' => 0.9,
            'isFontSubsettingEnabled' => true,
        ]);

        return $pdf->download("quotation-{$quotation->quotation_number}.pdf");
    }

    /**
     * Update quotation status
     */
    public function updateStatus(Request $request, Quotation $quotation)
    {
        $request->validate([
            'status' => 'required|in:draft,sent,accepted,rejected,expired',
        ]);

        $quotation->update(['status' => $request->status]);

        return back()->with('success', 'Quotation status updated successfully.');
    }
}
