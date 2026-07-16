<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function makeVendor(VendorStatus $status = VendorStatus::Approved): Vendor
    {
        $user = User::factory()->vendorUser()->create();

        return Vendor::create([
            'user_id' => $user->id,
            'store_name' => 'Store '.$user->id,
            'slug' => 'store-'.$user->id,
            'status' => $status,
        ]);
    }

    protected function makeProduct(Vendor $vendor, ProductStatus $status = ProductStatus::Active): Product
    {
        $category = Category::create(['name' => 'Cat '.uniqid(), 'slug' => 'cat-'.uniqid()]);

        return Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
            'price' => 10.00,
            'status' => $status,
        ]);
    }

    public function test_admin_can_update_any_product(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = $this->makeVendor();
        $product = $this->makeProduct($vendor);

        $this->assertTrue($admin->can('update', $product));
        $this->assertTrue($admin->can('delete', $product));
    }

    public function test_vendor_can_update_own_product(): void
    {
        $vendor = $this->makeVendor();
        $product = $this->makeProduct($vendor);

        $this->assertTrue($vendor->user->can('update', $product));
    }

    public function test_vendor_cannot_update_other_vendors_product(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $product = $this->makeProduct($vendorA);

        $this->assertFalse($vendorB->user->can('update', $product));
    }

    public function test_customer_cannot_create_product(): void
    {
        $customer = User::factory()->customer()->create();

        $this->assertFalse($customer->can('create', Product::class));
    }

    public function test_pending_vendor_cannot_create_product(): void
    {
        $vendor = $this->makeVendor(VendorStatus::Pending);

        $this->assertFalse($vendor->user->can('create', Product::class));
    }

    public function test_approved_vendor_can_create_product(): void
    {
        $vendor = $this->makeVendor(VendorStatus::Approved);

        $this->assertTrue($vendor->user->can('create', Product::class));
    }

    public function test_anyone_can_view_active_product(): void
    {
        $vendor = $this->makeVendor();
        $product = $this->makeProduct($vendor, ProductStatus::Active);
        $customer = User::factory()->customer()->create();

        $this->assertTrue($customer->can('view', $product));
    }

    public function test_only_owner_can_view_draft_product(): void
    {
        $vendor = $this->makeVendor();
        $product = $this->makeProduct($vendor, ProductStatus::Draft);
        $otherCustomer = User::factory()->customer()->create();

        $this->assertTrue($vendor->user->can('view', $product));
        $this->assertFalse($otherCustomer->can('view', $product));
    }
}