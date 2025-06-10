<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function salesReport(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $salesData = Transaction::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_transactions,
                SUM(total_amount) as total_sales,
                SUM(discount_amount) as total_discounts,
                AVG(total_amount) as average_sale
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $summary = Transaction::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(total_amount) as total_sales,
                SUM(discount_amount) as total_discounts,
                AVG(total_amount) as average_sale,
                MIN(total_amount) as min_sale,
                MAX(total_amount) as max_sale
            ')
            ->first();

        return response()->json([
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'daily_sales' => $salesData,
            'summary' => $summary
        ], 200);
    }

    public function productReport(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $topSellingProducts = TransactionItem::whereHas('transaction', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate])
                      ->where('status', 'completed');
            })
            ->with('product.category')
            ->selectRaw('
                product_id,
                SUM(quantity) as total_sold,
                SUM(total_price) as total_revenue,
                AVG(unit_price) as average_price
            ')
            ->groupBy('product_id')
            ->orderBy('total_sold', 'desc')
            ->limit(20)
            ->get();

        $lowStockProducts = Product::whereRaw('stock_quantity <= min_stock_level')
            ->where('is_active', true)
            ->with('category')
            ->get();

        $categoryPerformance = TransactionItem::whereHas('transaction', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate])
                      ->where('status', 'completed');
            })
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw('
                categories.name as category_name,
                SUM(transaction_items.quantity) as total_sold,
                SUM(transaction_items.total_price) as total_revenue
            ')
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        return response()->json([
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'top_selling_products' => $topSellingProducts,
            'low_stock_products' => $lowStockProducts,
            'category_performance' => $categoryPerformance
        ], 200);
    }

    public function customerReport(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $topCustomers = Customer::whereHas('transactions', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate])
                      ->where('status', 'completed');
            })
            ->withSum(['transactions' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate])
                      ->where('status', 'completed');
            }], 'total_amount')
            ->withCount(['transactions' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate])
                      ->where('status', 'completed');
            }])
            ->orderBy('transactions_sum_total_amount', 'desc')
            ->limit(20)
            ->get();

        $customerStats = Customer::selectRaw('
            COUNT(*) as total_customers,
            COUNT(CASE WHEN created_at >= ? THEN 1 END) as new_customers,
            AVG(total_spent) as average_spent,
            AVG(total_orders) as average_orders
        ', [$startDate])
        ->first();

        return response()->json([
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'top_customers' => $topCustomers,
            'customer_statistics' => $customerStats
        ], 200);
    }

    public function feedbackReport(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $feedbackStats = Feedback::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                AVG(rating) as average_rating,
                COUNT(*) as total_feedback,
                SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as positive_feedback,
                SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as negative_feedback
            ')
            ->first();

        $ratingDistribution = Feedback::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating')
            ->get();

        $recentFeedback = Feedback::whereBetween('created_at', [$startDate, $endDate])
            ->with(['transaction', 'customer'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'statistics' => $feedbackStats,
            'rating_distribution' => $ratingDistribution,
            'recent_feedback' => $recentFeedback
        ], 200);
    }

    public function dashboardStats()
    {
        $today = now()->toDateString();
        $thisMonth = now()->startOfMonth()->toDateString();

        $todaySales = Transaction::whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('total_amount');

        $monthSales = Transaction::whereBetween('created_at', [$thisMonth, now()])
            ->where('status', 'completed')
            ->sum('total_amount');

        $totalProducts = Product::where('is_active', true)->count();
        $lowStockProducts = Product::whereRaw('stock_quantity <= min_stock_level')
            ->where('is_active', true)
            ->count();

        $totalCustomers = Customer::where('is_active', true)->count();
        $averageRating = Feedback::avg('rating');

        return response()->json([
            'today_sales' => $todaySales,
            'month_sales' => $monthSales,
            'total_products' => $totalProducts,
            'low_stock_products' => $lowStockProducts,
            'total_customers' => $totalCustomers,
            'average_rating' => round($averageRating, 2)
        ], 200);
    }
}