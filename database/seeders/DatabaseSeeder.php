<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use App\Models\Package;
use App\Models\Order;
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


        // Create admin user
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'customer_name' => 'Admin',
                'password' => Hash::make('password'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'customer_name' => 'Super Admin',
                'password' => Hash::make('password'),
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

        // Create sample orders only if they don't exist
        if (Order::count() === 0) {
            $adminUser = User::where('email', 'admin@gmail.com')->first();
            $superAdminUser = User::where('email', 'superadmin@gmail.com')->first();

            Order::create([
                'user_id' => $adminUser->id,
                'customer_name' => 'John Doe',
                'order_number' => Order::generateOrderNumber(),
                'total_amount' => 299.99,
                'currency' => 'SAR',
                'status' => 'paid',
                'payment_method' => 'credit_card',
                'payment_id' => 'PAY_' . uniqid(),
                'items' => [
                    ['name' => 'Camping Tent', 'quantity' => 1, 'price' => 149.99],
                    ['name' => 'Sleeping Bag', 'quantity' => 2, 'price' => 79.99],
                ],
                'notes' => 'Customer requested early delivery',
            ]);

            Order::create([
                'user_id' => $superAdminUser->id,
                'customer_name' => 'Sarah Smith',
                'order_number' => Order::generateOrderNumber(),
                'total_amount' => 459.97,
                'currency' => 'SAR',
                'status' => 'processing',
                'payment_method' => 'noon',
                'payment_id' => 'NOON_' . uniqid(),
                'items' => [
                    ['name' => 'Adventure Backpack', 'quantity' => 2, 'price' => 89.99],
                    ['name' => 'Hiking Boots', 'quantity' => 1, 'price' => 129.99],
                    ['name' => 'Camping Tent', 'quantity' => 1, 'price' => 149.99],
                ],
                'notes' => 'Gift wrapping requested',
            ]);

            Order::create([
                'user_id' => $adminUser->id,
                'customer_name' => 'Michael Johnson',
                'order_number' => Order::generateOrderNumber(),
                'total_amount' => 899.99,
                'currency' => 'USD',
                'status' => 'pending',
                'payment_method' => 'bank_transfer',
                'items' => [
                    ['name' => 'Luxury Adventure Package', 'quantity' => 1, 'price' => 899.99],
                ],
                'notes' => 'Waiting for bank transfer confirmation',
            ]);

            Order::create([
                'user_id' => $superAdminUser->id,
                'customer_name' => 'Emily Davis',
                'order_number' => Order::generateOrderNumber(),
                'total_amount' => 199.99,
                'currency' => 'SAR',
                'status' => 'cancelled',
                'payment_method' => 'cash',
                'items' => [
                    ['name' => 'Hiking Essentials Package', 'quantity' => 1, 'price' => 199.99],
                ],
                'notes' => 'Customer cancelled due to schedule conflict',
            ]);

            Order::create([
                'user_id' => $adminUser->id,
                'customer_name' => 'David Wilson',
                'order_number' => Order::generateOrderNumber(),
                'total_amount' => 499.99,
                'currency' => 'EUR',
                'status' => 'refunded',
                'payment_method' => 'paypal',
                'payment_id' => 'PP_' . uniqid(),
                'items' => [
                    ['name' => 'Family Camping Package', 'quantity' => 1, 'price' => 499.99],
                ],
                'notes' => 'Product damaged during shipping, full refund issued',
            ]);

            Order::create([
                'user_id' => $superAdminUser->id,
                'customer_name' => 'Lisa Anderson',
                'order_number' => Order::generateOrderNumber(),
                'total_amount' => 389.97,
                'currency' => 'SAR',
                'status' => 'paid',
                'payment_method' => 'credit_card',
                'payment_id' => 'PAY_' . uniqid(),
                'items' => [
                    ['name' => 'Hiking Boots', 'quantity' => 3, 'price' => 129.99],
                ],
                'notes' => 'Bulk order for hiking club',
            ]);

            Order::create([
                'user_id' => $adminUser->id,
                'customer_name' => 'Robert Martinez',
                'order_number' => Order::generateOrderNumber(),
                'total_amount' => 229.98,
                'currency' => 'SAR',
                'status' => 'processing',
                'payment_method' => 'noon',
                'payment_id' => 'NOON_' . uniqid(),
                'items' => [
                    ['name' => 'Camping Tent', 'quantity' => 1, 'price' => 149.99],
                    ['name' => 'Sleeping Bag', 'quantity' => 1, 'price' => 79.99],
                ],
            ]);

            Order::create([
                'user_id' => $superAdminUser->id,
                'customer_name' => 'Jennifer Taylor',
                'order_number' => Order::generateOrderNumber(),
                'total_amount' => 179.98,
                'currency' => 'SAR',
                'status' => 'paid',
                'payment_method' => 'cash',
                'items' => [
                    ['name' => 'Adventure Backpack', 'quantity' => 2, 'price' => 89.99],
                ],
                'notes' => 'Paid in cash on pickup',
            ]);
        }
    }
}
