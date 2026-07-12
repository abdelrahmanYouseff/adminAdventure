<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Product;
use App\Services\QuotationPdfService;
use App\Support\QuotationPdfData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

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
        ], [
            'customer_name.required' => 'اسم العميل مطلوب.',
            'customer_name.max' => 'اسم العميل طويل جداً.',
            'customer_email.email' => 'البريد الإلكتروني غير صالح.',
            'valid_until.required' => 'تاريخ الصلاحية مطلوب.',
            'valid_until.date' => 'تاريخ الصلاحية غير صالح.',
            'valid_until.after' => 'تاريخ الصلاحية يجب أن يكون بعد اليوم.',
            'items.required' => 'يجب إضافة منتج واحد على الأقل.',
            'items.min' => 'يجب إضافة منتج واحد على الأقل.',
            'items.*.product_id.required' => 'المنتج مطلوب.',
            'items.*.product_id.exists' => 'المنتج المحدد غير موجود.',
            'items.*.quantity.required' => 'الكمية مطلوبة.',
            'items.*.quantity.min' => 'الكمية يجب أن تكون 1 على الأقل.',
            'items.*.unit_price.required' => 'سعر الوحدة مطلوب.',
            'items.*.unit_price.min' => 'سعر الوحدة لا يمكن أن يكون سالباً.',
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

            return redirect()->route('quotations.index')
                ->with('success', 'تم إنشاء عرض السعر بنجاح.')
                ->with('open_pdf', $quotation->id);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'فشل إنشاء عرض السعر.']);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Quotation $quotation)
    {
        try {
            $pdfUrl = '/quotations/'.$quotation->id.'/pdf';

            if ($request->header('X-Inertia')) {
                return Inertia::location($pdfUrl);
            }

            return redirect()->to($pdfUrl);
        } catch (\Exception $e) {
            Log::error('Error in quotation show: ' . $e->getMessage());
            return back()->withErrors(['error' => 'فشل تحميل عرض السعر.']);
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

            return redirect()->route('quotations.index')
                ->with('success', 'تم تحديث عرض السعر بنجاح.')
                ->with('open_pdf', $quotation->id);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'فشل تحديث عرض السعر.']);
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
    public function generatePdf(Quotation $quotation, QuotationPdfService $pdfService)
    {
        $data = QuotationPdfData::fromQuotation($quotation);
        $content = $pdfService->render($data);
        $filename = 'quotation-'.$quotation->quotation_number.'.pdf';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
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
