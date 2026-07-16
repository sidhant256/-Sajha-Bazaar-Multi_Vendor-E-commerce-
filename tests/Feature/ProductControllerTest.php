<?php

namespace Tests\Feature;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
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

    protected function makeCategory(): Category
    {
        return Category::create(['name' => 'Cat '.uniqid(), 'slug' => 'cat-'.uniqid()]);
    }

    public function test_index_returns_only_active_products(): void
    {
        $vendor = $this->makeVendor();
        $category = $this->makeCategory();

        Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Active One', 'slug' => 'active-one', 'price' => 10, 'status' => 'active',
        ]);
        Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Draft One', 'slug' => 'draft-one', 'price' => 10, 'status' => 'draft',
        ]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $names = collect($response->json('data.data'))->pluck('name');
        $this->assertTrue($names->contains('Active One'));
        $this->assertFalse($names->contains('Draft One'));
    }

    public function test_approved_vendor_can_create_product(): void
    {
        $vendor = $this->makeVendor();
        $category = $this->makeCategory();

        $response = $this->actingAs($vendor->user, 'sanctum')->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'New Gadget',
            'price' => 49.99,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', [
            'name' => 'New Gadget',
            'vendor_id' => $vendor->id,
            'status' => 'draft',
        ]);
    }

    public function test_customer_cannot_create_product(): void
    {
        $customer = User::factory()->customer()->create();
        $category = $this->makeCategory();

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Sneaky Product',
            'price' => 10,
        ]);

        $response->assertStatus(403);
    }

    public function test_vendor_cannot_set_arbitrary_vendor_id(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $category = $this->makeCategory();

        $response = $this->actingAs($vendorA->user, 'sanctum')->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Product',
            'price' => 10,
            'vendor_id' => $vendorB->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', [
            'name' => 'Product',
            'vendor_id' => $vendorA->id,
        ]);
    }

    public function test_vendor_can_update_own_product(): void
    {
        $vendor = $this->makeVendor();
        $category = $this->makeCategory();
        $product = Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Old Name', 'slug' => 'old-name', 'price' => 10, 'status' => 'draft',
        ]);

        $response = $this->actingAs($vendor->user, 'sanctum')
            ->putJson("/api/products/{$product->id}", ['name' => 'New Name']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'New Name']);
    }

    public function test_vendor_cannot_update_other_vendors_product(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $category = $this->makeCategory();
        $product = Product::create([
            'vendor_id' => $vendorA->id, 'category_id' => $category->id,
            'name' => 'Old Name', 'slug' => 'old-name', 'price' => 10, 'status' => 'draft',
        ]);

        $response = $this->actingAs($vendorB->user, 'sanctum')
            ->putJson("/api/products/{$product->id}", ['name' => 'Hacked']);

        $response->assertStatus(403);
    }

    public function test_vendor_can_delete_own_product(): void
    {
        $vendor = $this->makeVendor();
        $category = $this->makeCategory();
        $product = Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'To Delete', 'slug' => 'to-delete', 'price' => 10, 'status' => 'draft',
        ]);

        $response = $this->actingAs($vendor->user, 'sanctum')
            ->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_guest_cannot_view_draft_product(): void
    {
        $vendor = $this->makeVendor();
        $category = $this->makeCategory();
        $product = Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id,
            'name' => 'Hidden', 'slug' => 'hidden', 'price' => 10, 'status' => 'draft',
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(403);
    }
}