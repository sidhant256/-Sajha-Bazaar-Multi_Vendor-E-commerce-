<?php

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Enums\VendorStatus;
use App\Models\Coupon;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function makeVendor(): Vendor
    {
        $user = User::factory()->vendorUser()->create();

        return Vendor::create([
            'user_id' => $user->id,
            'store_name' => 'Store '.$user->id,
            'slug' => 'store-'.$user->id,
            'status' => VendorStatus::Approved,
        ]);
    }

    public function test_anyone_can_view_platform_wide_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'PLATFORM10',
            'type' => CouponType::Percent,
            'value' => 10,
            'vendor_id' => null,
        ]);
        $customer = User::factory()->customer()->create();

        $this->assertTrue($customer->can('view', $coupon));
    }

    public function test_only_admin_can_update_platform_wide_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'PLATFORM10',
            'type' => CouponType::Percent,
            'value' => 10,
            'vendor_id' => null,
        ]);
        $admin = User::factory()->admin()->create();
        $vendor = $this->makeVendor();

        $this->assertTrue($admin->can('update', $coupon));
        $this->assertFalse($vendor->user->can('update', $coupon));
    }

    public function test_vendor_can_update_own_coupon(): void
    {
        $vendor = $this->makeVendor();
        $coupon = Coupon::create([
            'code' => 'STORE10',
            'type' => CouponType::Percent,
            'value' => 10,
            'vendor_id' => $vendor->id,
        ]);

        $this->assertTrue($vendor->user->can('update', $coupon));
    }

    public function test_vendor_cannot_update_other_vendors_coupon(): void
    {
        $vendorA = $this->makeVendor();
        $vendorB = $this->makeVendor();
        $coupon = Coupon::create([
            'code' => 'STORE10',
            'type' => CouponType::Percent,
            'value' => 10,
            'vendor_id' => $vendorA->id,
        ]);

        $this->assertFalse($vendorB->user->can('update', $coupon));
    }

    public function test_customer_cannot_create_coupon(): void
    {
        $customer = User::factory()->customer()->create();

        $this->assertFalse($customer->can('create', Coupon::class));
    }
}