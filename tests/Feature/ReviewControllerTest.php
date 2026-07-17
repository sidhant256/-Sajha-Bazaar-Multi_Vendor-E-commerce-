<?php

namespace Tests\Feature;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOrderItemFor(User $customer): OrderItem
    {
        $vendorUser = User::factory()->vendorUser()->create();
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'store_name' => 'Store '.$vendorUser->id,
            'slug' => 'store-'.$vendorUser->id,
            'status' => VendorStatus::Approved,
        ]);

        $category = Category::create(['name' => 'Cat '.uniqid(), 'slug' => 'cat-'.uniqid()]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Product',
            'slug' => 'product-'.uniqid(),
            'price' => 15.00,
            'status' => 'active',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'options' => [],
        ]);

        $order = Order::create([
            'user_id' => $customer->id,
            'status' => 'completed',
            'total' => 15.00,
        ]);

        return OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'vendor_id' => $vendor->id,
            'quantity' => 1,
            'unit_price' => 15.00,
        ]);
    }

    public function test_customer_can_review_purchased_item(): void
    {
        $customer = User::factory()->customer()->create();
        $orderItem = $this->makeOrderItemFor($customer);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/reviews', [
            'order_item_id' => $orderItem->id,
            'rating' => 5,
            'comment' => 'Great product!',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reviews', [
            'order_item_id' => $orderItem->id,
            'user_id' => $customer->id,
            'rating' => 5,
        ]);
    }

    public function test_customer_cannot_review_unpurchased_item(): void
    {
        $customer = User::factory()->customer()->create();
        $otherCustomer = User::factory()->customer()->create();
        $orderItem = $this->makeOrderItemFor($otherCustomer);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/reviews', [
            'order_item_id' => $orderItem->id,
            'rating' => 5,
        ]);

        $response->assertStatus(403);
    }

    public function test_customer_cannot_review_same_item_twice(): void
    {
        $customer = User::factory()->customer()->create();
        $orderItem = $this->makeOrderItemFor($customer);

        $this->actingAs($customer, 'sanctum')->postJson('/api/reviews', [
            'order_item_id' => $orderItem->id,
            'rating' => 4,
        ])->assertStatus(201);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/reviews', [
            'order_item_id' => $orderItem->id,
            'rating' => 2,
        ]);

        $response->assertStatus(403);
    }

    public function test_rating_out_of_range_is_rejected(): void
    {
        $customer = User::factory()->customer()->create();
        $orderItem = $this->makeOrderItemFor($customer);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/reviews', [
            'order_item_id' => $orderItem->id,
            'rating' => 6,
        ]);

        $response->assertStatus(422);
    }

    public function test_owner_can_update_own_review(): void
    {
        $customer = User::factory()->customer()->create();
        $orderItem = $this->makeOrderItemFor($customer);

        $review = Review::create([
            'product_id' => $orderItem->productVariant->product_id,
            'user_id' => $customer->id,
            'order_item_id' => $orderItem->id,
            'rating' => 3,
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->putJson("/api/reviews/{$review->id}", ['rating' => 5]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 5]);
    }

    public function test_non_owner_cannot_update_review(): void
    {
        $customer = User::factory()->customer()->create();
        $otherCustomer = User::factory()->customer()->create();
        $orderItem = $this->makeOrderItemFor($customer);

        $review = Review::create([
            'product_id' => $orderItem->productVariant->product_id,
            'user_id' => $customer->id,
            'order_item_id' => $orderItem->id,
            'rating' => 3,
        ]);

        $response = $this->actingAs($otherCustomer, 'sanctum')
            ->putJson("/api/reviews/{$review->id}", ['rating' => 1]);

        $response->assertStatus(403);
    }

    public function test_owner_can_delete_own_review(): void
    {
        $customer = User::factory()->customer()->create();
        $orderItem = $this->makeOrderItemFor($customer);

        $review = Review::create([
            'product_id' => $orderItem->productVariant->product_id,
            'user_id' => $customer->id,
            'order_item_id' => $orderItem->id,
            'rating' => 3,
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->deleteJson("/api/reviews/{$review->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }
}