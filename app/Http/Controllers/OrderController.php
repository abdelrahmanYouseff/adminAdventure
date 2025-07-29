<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index()
    {
        $rentals = Rental::with(['user', 'product'])->orderBy('created_at', 'desc')->get();
        return Inertia::render('Orders/Index', [
            'rentals' => $rentals,
        ]);
    }
}
