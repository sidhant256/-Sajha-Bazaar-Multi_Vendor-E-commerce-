<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CouponPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;  
    }
    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Coupon $coupon): bool
    {
        if ($coupon->isPlatformWide()) {
            return true;
        }
        return $user !== null && $user->vendor?->id === $coupon->vendor_id;
    }
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isVendor() && $user->vendor?->status->value === 'approved';
    }
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Coupon $coupon): bool
    {
        if ($coupon->isPlatformWide()) {
            return false;
        }
        return $user->vendor?->id === $coupon->vendor_id;
    }
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Coupon $coupon): bool
    {
        if ($coupon->isPlatformWide()) {
            return false;
        }

        return $user->vendor?->id === $coupon->vendor_id;
    }
}