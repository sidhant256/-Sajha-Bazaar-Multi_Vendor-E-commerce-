<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Admin ---
        User::factory()->admin()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@example.com',
        ]);

        // --- Category tree: 3 roots, each with 2 children ---
        $rootCategories = collect(['Clothing', 'Electronics', 'Books'])
            ->map(fn (string $name) => Category::factory()->create([
                'name' => $name,
                'slug' => Str::slug($name),
                'parent_id' => null,
            ]));

        $childCategories = $rootCategories->flatMap(
            fn (Category $root) => collect(range(1, 2))->map(
                fn () => Category::factory()->childOf($root)->create()
            )
        );

        $allCategories = $rootCategories->merge($childCategories);

        // --- Vendors: 5 approved, 1 pending, 1 suspended ---
        $approvedVendors = Vendor::factory()->approved()->count(5)->create();
        Vendor::factory()->create(['status' => VendorStatus::Pending]);
        Vendor::factory()->create(['status' => VendorStatus::Suspended]);

        // --- Products + variants + inventory, approved vendors only ---
        $approvedVendors->each(function (Vendor $vendor) use ($allCategories) {
            Product::factory()
                ->count(4)
                ->create([
                    'vendor_id' => $vendor->id,
                    'category_id' => fn () => $allCategories->random()->id,
                ])
                ->each(function (Product $product) {
                    ProductVariant::factory()
                        ->count(2)
                        ->create(['product_id' => $product->id])
                        ->each(fn (ProductVariant $variant) =>
                            \App\Models\Inventory::factory()->create([
                                'product_variant_id' => $variant->id,
                            ])
                        );
                });
        });

        // --- Coupons: one platform-wide, one vendor-scoped ---
        Coupon::factory()->create(['code' => 'WELCOME10', 'value' => 10]);
        Coupon::factory()->scopedToVendor($approvedVendors->first())->create();

        // --- Customers with a real order -> payment -> review chain ---
        User::factory()->customer()->count(10)->create()->each(function (User $customer) {
            $order = Order::factory()->create([
                'user_id' => $customer->id,
                'status' => OrderStatus::Completed,
            ]);

            $variant = ProductVariant::inRandomOrder()->first();

            if (! $variant) {
                return;
            }

            $orderItem = OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_variant_id' => $variant->id,
                'unit_price' => $variant->effective_price,
            ]);

            Payment::factory()->succeeded()->create([
                'order_id' => $order->id,
                'amount' => $order->total,
            ]);

            Review::factory()->create([
                'order_item_id' => $orderItem->id,
            ]);
        });
    }
}