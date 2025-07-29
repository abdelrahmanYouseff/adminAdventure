<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use App\Models\Package;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
            'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Create admin user
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Create sample categories
        $camping = Category::firstOrCreate(['category_name' => 'Camping']);
        $hiking = Category::firstOrCreate(['category_name' => 'Hiking']);
        $bags = Category::firstOrCreate(['category_name' => 'Bags']);

        // Create sample products only if they don't exist
        if (Product::count() === 0) {
            Product::create([
                'product_name' => 'Adventure Backpack',
                'description' => 'High-quality backpack for outdoor adventures with multiple compartments.',
                'price' => 89.99,
                'status' => 'active',
                'category_id' => $bags->id,
            ]);

            Product::create([
                'product_name' => 'Camping Tent',
                'description' => '4-person camping tent with weather protection and easy setup.',
                'price' => 149.99,
                'status' => 'active',
                'category_id' => $camping->id,
            ]);

            Product::create([
                'product_name' => 'Hiking Boots',
                'description' => 'Comfortable and durable hiking boots for all terrain types.',
                'price' => 129.99,
                'status' => 'active',
                'category_id' => $hiking->id,
            ]);

            Product::create([
                'product_name' => 'Sleeping Bag',
                'description' => 'Warm and lightweight sleeping bag for camping trips.',
                'price' => 79.99,
                'status' => 'inactive',
                'category_id' => $camping->id,
            ]);
        }

        // Create sample packages only if they don't exist
        if (Package::count() === 0) {
            Package::create([
                'name' => 'Weekend Adventure Package',
                'description' => 'Complete package for a weekend adventure including tent, sleeping bag, and basic camping gear.',
                'price' => 299.99,
                'status' => 'active',
            ]);

            Package::create([
                'name' => 'Hiking Essentials Package',
                'description' => 'Essential hiking gear including backpack, boots, water bottle, and first aid kit.',
                'price' => 199.99,
                'status' => 'active',
            ]);

            Package::create([
                'name' => 'Family Camping Package',
                'description' => 'Perfect for family camping trips with larger tent, multiple sleeping bags, and cooking equipment.',
                'price' => 499.99,
                'status' => 'active',
            ]);

            Package::create([
                'name' => 'Luxury Adventure Package',
                'description' => 'Premium adventure package with high-end gear and luxury camping equipment.',
                'price' => 899.99,
                'status' => 'inactive',
            ]);
        }
    }
}
