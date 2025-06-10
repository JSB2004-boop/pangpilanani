<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category')->where('is_active', true);

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        if ($request->has('low_stock') && $request->get('low_stock')) {
            $query->whereRaw('stock_quantity <= min_stock_level');
        }

        $products = $query->orderBy('name')->get();

        return response()->json([
            'products' => $products
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sku' => ['required', 'string', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'unique:products,barcode'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'min_stock_level' => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
            'image' => ['nullable', 'image', 'max:2048'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'brand' => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($validated);

        // Record initial stock movement
        if ($validated['stock_quantity'] > 0) {
            StockMovement::create([
                'product_id' => $product->id,
                'user_id' => auth()->id(),
                'type' => 'in',
                'quantity' => $validated['stock_quantity'],
                'previous_stock' => 0,
                'new_stock' => $validated['stock_quantity'],
                'reason' => 'Initial stock',
            ]);
        }

        return response()->json([
            'message' => 'Product created successfully.',
            'product' => $product->load('category')
        ], 201);
    }

    public function show(Product $product)
    {
        return response()->json([
            'product' => $product->load('category')
        ], 200);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sku' => ['required', 'string', 'unique:products,sku,' . $product->id],
            'barcode' => ['nullable', 'string', 'unique:products,barcode,' . $product->id],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'min_stock_level' => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
            'image' => ['nullable', 'image', 'max:2048'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'brand' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($validated);

        return response()->json([
            'message' => 'Product updated successfully.',
            'product' => $product->load('category')
        ], 200);
    }

    public function destroy(Product $product)
    {
        $product->update(['is_active' => false]);

        return response()->json([
            'message' => 'Product deactivated successfully.'
        ], 200);
    }

    public function updateStock(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer'],
            'type' => ['required', 'in:in,out,adjustment'],
            'reason' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $previousStock = $product->stock_quantity;
        
        if ($validated['type'] === 'adjustment') {
            $newStock = $validated['quantity'];
        } else {
            $newStock = $validated['type'] === 'in' 
                ? $previousStock + $validated['quantity']
                : $previousStock - $validated['quantity'];
        }

        if ($newStock < 0) {
            return response()->json([
                'message' => 'Insufficient stock quantity.'
            ], 422);
        }

        $product->update(['stock_quantity' => $newStock]);

        StockMovement::create([
            'product_id' => $product->id,
            'user_id' => auth()->id(),
            'type' => $validated['type'],
            'quantity' => abs($newStock - $previousStock),
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'reason' => $validated['reason'],
            'notes' => $validated['notes'],
        ]);

        return response()->json([
            'message' => 'Stock updated successfully.',
            'product' => $product->fresh()
        ], 200);
    }

    public function getLowStockProducts()
    {
        $products = Product::with('category')
            ->whereRaw('stock_quantity <= min_stock_level')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'products' => $products
        ], 200);
    }
}