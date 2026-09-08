<?php

namespace App\Http\Controllers;

use App\Models\InboxServiceRating;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QaReportController extends Controller
{
    public function index(Request $request)
    {
        $ratings = InboxServiceRating::query()
            ->with(['conversation.contact', 'order:id,order_number'])
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $avg = round((float) InboxServiceRating::query()
            ->whereNotNull('score')
            ->avg('score'), 2);

        $completed = InboxServiceRating::query()
            ->where('status', InboxServiceRating::STATUS_COMPLETED)
            ->count();

        return Inertia::render('Reports/Qa', [
            'ratings' => $ratings,
            'stats' => [
                'average' => $avg,
                'completed' => $completed,
                'total' => InboxServiceRating::query()->count(),
            ],
        ]);
    }
}
