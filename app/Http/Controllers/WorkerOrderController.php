<?php

namespace App\Http\Controllers;

use App\Models\WorkerOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class WorkerOrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString() ?: 'pending';

        $query = WorkerOrder::query()
            ->with(['order:id,order_number,location_slug,address'])
            ->orderByRaw('installation_date IS NULL')
            ->orderBy('installation_date')
            ->orderByDesc('created_at');

        if (in_array($status, ['pending', 'completed'], true)) {
            $query->where('status', $status);
        }

        $workerOrders = $query->paginate(12)->withQueryString();

        $stats = [
            'pending' => WorkerOrder::where('status', 'pending')->count(),
            'completed' => WorkerOrder::where('status', 'completed')->count(),
            'total' => WorkerOrder::count(),
        ];

        return Inertia::render('WorkerOrders/Index', [
            'workerOrders' => $workerOrders,
            'stats' => $stats,
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function complete(Request $request, WorkerOrder $workerOrder)
    {
        if ($workerOrder->status === 'completed') {
            return back()->withErrors([
                'installation_photo' => 'تم تسجيل التركيب مسبقاً لهذا الطلب.',
            ]);
        }

        $validated = $request->validate([
            'installation_photo' => ['required', 'image', 'max:5120'],
        ], [
            'installation_photo.required' => 'يجب إرفاق صورة للتركيب من أرض الواقع.',
            'installation_photo.image' => 'يجب أن يكون الملف صورة.',
            'installation_photo.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت.',
        ]);

        $path = $validated['installation_photo']->store('worker-installations', 'public');

        if ($workerOrder->installation_photo) {
            Storage::disk('public')->delete($workerOrder->installation_photo);
        }

        $workerOrder->update([
            'installation_photo' => $path,
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $request->user()->id,
        ]);

        return back()->with('success', 'تم تسجيل التركيب بنجاح.');
    }
}
