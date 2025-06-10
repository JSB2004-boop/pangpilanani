<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function index()
    {
        $discounts = Discount::orderBy('created_at', 'desc')->get();

        return response()->json([
            'discounts' => $discounts
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'unique:discounts,code'],
            'type' => ['required', 'in:percentage,fixed_amount'],
            'value' => ['required', 'numeric', 'min:0'],
            'minimum_amount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $discount = Discount::create($validated);

        return response()->json([
            'message' => 'Discount created successfully.',
            'discount' => $discount
        ], 201);
    }

    public function show(Discount $discount)
    {
        return response()->json([
            'discount' => $discount
        ], 200);
    }

    public function update(Request $request, Discount $discount)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'unique:discounts,code,' . $discount->id],
            'type' => ['required', 'in:percentage,fixed_amount'],
            'value' => ['required', 'numeric', 'min:0'],
            'minimum_amount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $discount->update($validated);

        return response()->json([
            'message' => 'Discount updated successfully.',
            'discount' => $discount
        ], 200);
    }

    public function destroy(Discount $discount)
    {
        $discount->update(['is_active' => false]);

        return response()->json([
            'message' => 'Discount deactivated successfully.'
        ], 200);
    }

    public function validateCode(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $discount = Discount::where('code', $request->code)->first();

        if (!$discount) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid discount code.'
            ], 404);
        }

        if (!$discount->isValid()) {
            return response()->json([
                'valid' => false,
                'message' => 'Discount code is not valid or has expired.'
            ], 422);
        }

        $discountAmount = $discount->calculateDiscount($request->amount);

        return response()->json([
            'valid' => true,
            'discount' => $discount,
            'discount_amount' => $discountAmount
        ], 200);
    }
}