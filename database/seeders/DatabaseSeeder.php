<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Admin User', 'email' => 'admin@example.com', 'role' => 'admin'],
            ['name' => 'Manager User', 'email' => 'manager@example.com', 'role' => 'manager'],
            ['name' => 'Staff User', 'email' => 'staff@example.com', 'role' => 'staff'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'password' => Hash::make('password'),
                ],
            );
        }

        $electronics = Category::updateOrCreate(
            ['name' => 'Electronics'],
            ['description' => 'Electronic devices and accessories'],
        );

        $office = Category::updateOrCreate(
            ['name' => 'Office Supplies'],
            ['description' => 'Everyday office products'],
        );

        $keyboard = Product::firstOrCreate(
            ['sku' => 'ELEC-KEYBOARD-001'],
            [
                'category_id' => $electronics->id,
                'name' => 'USB Keyboard',
                'description' => 'Simple wired USB keyboard',
                'price' => 24.99,
                'stock_quantity' => 50,
                'low_stock_limit' => 10,
                'status' => 'Active',
            ],
        );

        Supplier::updateOrCreate(
            ['email' => 'sales@techsource.example'],
            [
                'name' => 'TechSource Supplies',
                'phone' => '+91 98765 43210',
                'address' => 'Bengaluru, Karnataka',
                'status' => 'Active',
            ],
        );

        Supplier::updateOrCreate(
            ['email' => 'orders@officehub.example'],
            [
                'name' => 'OfficeHub Wholesale',
                'phone' => '+91 98765 43211',
                'address' => 'Mumbai, Maharashtra',
                'status' => 'Active',
            ],
        );

        Customer::updateOrCreate(
            ['email' => 'anita@example.com'],
            [
                'name' => 'Anita Sharma',
                'phone' => '+91 90000 00001',
                'address' => 'Pune, Maharashtra',
            ],
        );

        Customer::updateOrCreate(
            ['email' => 'rahul@example.com'],
            [
                'name' => 'Rahul Verma',
                'phone' => '+91 90000 00002',
                'address' => 'Delhi',
            ],
        );

        $notebook = Product::firstOrCreate(
            ['sku' => 'OFFICE-NOTEBOOK-001'],
            [
                'category_id' => $office->id,
                'name' => 'A5 Notebook',
                'description' => 'A5 lined notebook',
                'price' => 4.50,
                'stock_quantity' => 100,
                'low_stock_limit' => 20,
                'status' => 'Active',
            ],
        );

        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        foreach ([$keyboard, $notebook] as $product) {
            StockMovement::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'reference' => 'Initial seeded stock',
                ],
                [
                    'type' => 'IN',
                    'quantity' => $product->stock_quantity,
                    'previous_stock' => 0,
                    'new_stock' => $product->stock_quantity,
                    'created_by' => $admin->id,
                ],
            );
        }
    }
}
