<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OrderService $orderService
    ) {}

    public function index(Request $request)
    {
        $orders = $request->user()->orders()
            ->with('items.productVariant.product.vendor')
            ->paginate(15);

        $orders->getCollection()->transform(function ($order) {
            $order->item_summaries = $order->items->map(function ($item) {
                return [
                    'product_name' => $item->productVariant->product->name,
                    'vendor_name' => $item->productVariant->product->vendor->store_name,
                    'quantity' => $item->quantity,
                ];
            });

            return $order;
        });

        return $this->success($orders);
    }

    public function vendorIndex(Request $request)
    {
        $vendor = $request->user()->vendor;

        if ($vendor === null) {
            return $this->error('You do not have a vendor account.', 403);
        }

        $orders = Order::whereHas('items', fn ($query) => $query->where('vendor_id', $vendor->id))
            ->with([
                'user',
                'items' => fn ($query) => $query->where('vendor_id', $vendor->id),
                'items.productVariant.product',
            ])
            ->latest()
            ->paginate(15);

        $orders->getCollection()->transform(function ($order) {
            $order->vendor_items = $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_name' => $item->productVariant->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => number_format($item->quantity * (float) $item->unit_price, 2, '.', ''),
                ];
            });

            return $order;
        });

        return $this->success($orders);
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        return $this->success($order->load('items.productVariant.product', 'payment', 'shipments'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Order::class);

        $user = $request->user();
        $cart = $user->cart;

        if ($cart === null || $cart->items()->count() === 0) {
            return $this->error('Your cart is empty.', 422);
        }

        $order = $this->orderService->placeOrder($user);

        return $this->success($order->load('items.productVariant.product'), 'Order placed.', 201);
    }

    public function cancel(Order $order)
    {
        $this->authorize('cancel', $order);

        if (in_array($order->status->value, ['shipped', 'completed', 'canceled', 'refunded'])) {
            return $this->error('This order can no longer be canceled.', 422);
        }

        $this->orderService->cancelOrder($order);

        return $this->success($order, 'Order canceled.');
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->authorize('updateStatus', $order);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                OrderStatus::Processing->value,
                OrderStatus::Shipped->value,
                OrderStatus::Completed->value,
            ])],
        ]);

        if (in_array($order->status->value, [
            OrderStatus::Canceled->value,
            OrderStatus::Refunded->value,
            OrderStatus::Completed->value,
        ], true)) {
            return $this->error('This order status can no longer be changed.', 422);
        }

        $order->update(['status' => $validated['status']]);

        return $this->success(
            $order->load('items.productVariant.product.vendor'),
            'Order status updated.'
        );
    }
}
