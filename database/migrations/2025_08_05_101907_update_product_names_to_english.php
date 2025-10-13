<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update product names to English only
        $productUpdates = [
            5 => 'Ice Cream Roll',
            6 => 'VIP Cart',
            7 => 'Japanese Pancake Cart - Daily Rental',
            8 => 'Crispy Potato Cart - Daily Rental',
            9 => 'Ice Cream Machine Package with Toppings (8 toppings) - Daily Rental',
            10 => 'Ice Cream Machine Package with Toppings (4 toppings) - Daily Rental',
            11 => 'Slush Machine - Daily Rental',
            12 => '4 Snack Carts Package with 2 Workers - Daily Rental',
            13 => 'Mini Pancake Cart - Daily Rental',
            14 => 'Popcorn Cart - Daily Rental',
            15 => 'Fresh Corn Cups Cart - Daily Rental',
            16 => 'Cotton Candy Cart - Daily Rental',
            17 => '3 Snack Carts Package with Worker - Daily Rental',
            18 => 'Dream Land Prize Catcher Game',
            19 => 'Jargee Jargee Fun Prize Catcher Game',
            20 => 'IFOND DARTS Electric Darts Game',
            21 => 'Doctor Catcher Prize Game',
            22 => 'Electronic Darts - Daily Rental',
            23 => 'Advanced Boxing Game - Daily Rental',
            24 => 'Hercules Hammer Game - Daily Rental',
            26 => 'Rolling Car Game - Daily Rental',
            27 => 'Luminous Basketball Game - Daily Rental',
            28 => 'Big Electric Boxing Game',
            29 => 'Sharp Shooter Game - Daily Rental',
            30 => 'Electronic Shooting Game - Daily Rental',
            31 => 'Turntable Game - Daily Rental',
            32 => 'Fast Reaction Game - Daily Rental',
            33 => 'Yellow Car Game - Daily Rental',
            34 => 'PlayStation Package with VR - Daily Rental',
            35 => 'Pen Pag Sessions - Daily Rental',
            36 => 'Balloon Tent - Custom Service',
            37 => 'White Castle - Daily Rental',
            38 => 'Color Maze - Daily Rental',
            39 => 'Car Game - Daily Rental',
            40 => 'Happy Farm Game - Daily Rental',
            41 => 'Purple Palm Slide - Daily Rental',
            42 => 'Adventure Cabin 8m - Daily Rental',
            43 => 'Crocodile Slide - Daily Rental',
            44 => 'Little Lion Bouncer - Daily Rental',
            45 => 'Gorilla Slide - Daily Rental',
            46 => 'SpongeBob Bouncer - Daily Rental',
            47 => 'Giant Tower Game - Daily Rental',
            48 => 'Frozen Bouncer and Slide - Daily Rental',
        ];

        foreach ($productUpdates as $id => $newName) {
            DB::table('products')
                ->where('id', $id)
                ->update(['product_name' => $newName]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original names (you can add the original names here if needed)
        // For now, we'll leave this empty as it's not critical to revert
    }
};
