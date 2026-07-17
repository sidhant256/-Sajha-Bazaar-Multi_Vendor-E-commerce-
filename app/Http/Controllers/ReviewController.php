<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\OrderItem;
use App\Models\Review;
use App\Traits\ApiResponse;

class ReviewController extends Controller
{
    use ApiResponse;

    public function store(StoreReviewRequest $request)
    {
        $validated = $request->validated();

        $orderItem = OrderItem::findOrFail($validated['order_item_id']);

        $this->authorize('createForOrderItem', [Review::class, $orderItem]);

        $review = Review::create([
            'product_id' => $orderItem->productVariant->product_id,
            'user_id' => $request->user()->id,
            'order_item_id' => $orderItem->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return $this->success($review, 'Review submitted.', 201);
    }

    public function update(UpdateReviewRequest $request, Review $review)
    {
        $this->authorize('update', $review);

        $review->update($request->validated());

        return $this->success($review, 'Review updated.');
    }

    public function destroy(Review $review)
    {
        $this->authorize('delete', $review);

        $review->delete();

        return $this->success(null, 'Review deleted.');
    }
}