<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with(['user', 'customer', 'items.product']);

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($transactions, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_code' => ['nullable', 'string'],
            'payment_method' => ['required', 'in:cash,card,digital_wallet,bank_transfer'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // Calculate subtotal
            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                
                // Check stock availability
                if ($product->stock_quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }

                $itemTotal = ($item['unit_price'] * $item['quantity']) - ($item['discount_amount'] ?? 0);
                $subtotal += $itemTotal;

                $itemsData[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'total_price' => $itemTotal,
                ];
            }

            // Apply discount if provided
            $discountAmount = 0;
            if (!empty($validated['discount_code'])) {
                $discount = Discount::where('code', $validated['discount_code'])->first();
                if ($discount && $discount->isValid()) {
                    $discountAmount = $discount->calculateDiscount($subtotal);
                    $discount->increment('used_count');
                }
            }

            // Calculate tax (assuming 12% VAT)
            $taxRate = 0.12;
            $taxableAmount = $subtotal - $discountAmount;
            $taxAmount = $taxableAmount * $taxRate;
            $totalAmount = $taxableAmount + $taxAmount;

            // Calculate change
            $changeAmount = max(0, $validated['amount_paid'] - $totalAmount);

            // Create transaction
            $transaction = Transaction::create([
                'user_id' => auth()->id(),
                'customer_id' => $validated['customer_id'],
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'amount_paid' => $validated['amount_paid'],
                'change_amount' => $changeAmount,
                'payment_method' => $validated['payment_method'],
                'status' => 'completed',
                'notes' => $validated['notes'],
                'completed_at' => now(),
            ]);

            // Create transaction items and update stock
            foreach ($itemsData as $itemData) {
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $itemData['product']->id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'discount_amount' => $itemData['discount_amount'],
                    'total_price' => $itemData['total_price'],
                ]);

                // Update product stock
                $product = $itemData['product'];
                $previousStock = $product->stock_quantity;
                $newStock = $previousStock - $itemData['quantity'];
                
                $product->update(['stock_quantity' => $newStock]);

                // Record stock movement
                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => auth()->id(),
                    'type' => 'out',
                    'quantity' => $itemData['quantity'],
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'reason' => 'Sale - Transaction #' . $transaction->transaction_number,
                ]);
            }

            // Update customer statistics if customer is provided
            if ($validated['customer_id']) {
                $customer = Customer::find($validated['customer_id']);
                $customer->increment('total_orders');
                $customer->increment('total_spent', $totalAmount);
                $customer->update(['last_purchase_at' => now()]);
            }

            // Send transaction notification to Make.com
            $this->sendTransactionNotification($transaction);

            return response()->json([
                'message' => 'Transaction completed successfully.',
                'transaction' => $transaction->load(['items.product', 'customer'])
            ], 201);
        });
    }

    public function show(Transaction $transaction)
    {
        return response()->json([
            'transaction' => $transaction->load(['user', 'customer', 'items.product', 'feedback'])
        ], 200);
    }

    public function getDailySales(Request $request)
    {
        $date = $request->get('date', now()->toDateString());
        
        $sales = Transaction::whereDate('created_at', $date)
            ->where('status', 'completed')
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(total_amount) as total_sales,
                SUM(discount_amount) as total_discounts,
                AVG(total_amount) as average_sale
            ')
            ->first();

        $topProducts = TransactionItem::whereHas('transaction', function ($query) use ($date) {
                $query->whereDate('created_at', $date)->where('status', 'completed');
            })
            ->with('product')
            ->selectRaw('product_id, SUM(quantity) as total_sold, SUM(total_price) as total_revenue')
            ->groupBy('product_id')
            ->orderBy('total_sold', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'date' => $date,
            'sales_summary' => $sales,
            'top_products' => $topProducts
        ], 200);
    }

    private function sendTransactionNotification($transaction)
    {
        $webhookUrl = config('app.make_webhook_url');
        
        if ($webhookUrl) {
            try {
                $data = [
                    'event' => 'transaction_completed',
                    'transaction' => [
                        'id' => $transaction->id,
                        'number' => $transaction->transaction_number,
                        'total_amount' => $transaction->total_amount,
                        'payment_method' => $transaction->payment_method,
                        'cashier' => $transaction->user->full_name,
                        'customer' => $transaction->customer ? $transaction->customer->full_name : 'Walk-in',
                        'completed_at' => $transaction->completed_at->toISOString(),
                    ]
                ];

                $ch = curl_init($webhookUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
            } catch (\Exception $e) {
                \Log::error('Failed to send transaction notification: ' . $e->getMessage());
            }
        }
    }
}