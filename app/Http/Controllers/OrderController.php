<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $orders = $request->user()->orders()->with('items')->paginate(15);
        return $this->success($orders);
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        return $this->success($order->load('items.productVariant.product', 'payment', 'shipments'));

    }

    public function store(Request $request)
    {
        $user = $request->user();
        $cart = $user->cart;

        if ($cart === null || $cart->items()->count() === 0) {
            return $this->error('Your cart is empty.', 422);
        }
        $order = DB::transaction(function () use($cart, $user) {
            $cartItems = $cart->items()->with('productVariant.product')->get();
        })
    }

    public function cancel(Order $order)
    {

    }
}