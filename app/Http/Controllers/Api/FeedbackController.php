<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\Transaction;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function index()
    {
        $feedback = Feedback::with(['transaction', 'customer'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'feedback' => $feedback
        ], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'exists:transactions,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
            'survey_responses' => ['nullable', 'array'],
        ]);

        $feedback = Feedback::create($validated);

        // Send feedback notification to Make.com
        $this->sendFeedbackNotification($feedback);

        return response()->json([
            'message' => 'Feedback submitted successfully.',
            'feedback' => $feedback->load(['transaction', 'customer'])
        ], 201);
    }

    public function show(Feedback $feedback)
    {
        return response()->json([
            'feedback' => $feedback->load(['transaction', 'customer'])
        ], 200);
    }

    public function getStatistics()
    {
        $stats = Feedback::selectRaw('
            AVG(rating) as average_rating,
            COUNT(*) as total_feedback,
            SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as positive_feedback,
            SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as negative_feedback
        ')->first();

        $ratingDistribution = Feedback::selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating')
            ->get();

        return response()->json([
            'statistics' => $stats,
            'rating_distribution' => $ratingDistribution
        ], 200);
    }

    private function sendFeedbackNotification($feedback)
    {
        $webhookUrl = config('app.make_webhook_url');
        
        if ($webhookUrl) {
            try {
                $data = [
                    'event' => 'feedback_received',
                    'feedback' => [
                        'id' => $feedback->id,
                        'rating' => $feedback->rating,
                        'comment' => $feedback->comment,
                        'transaction_number' => $feedback->transaction->transaction_number,
                        'customer' => $feedback->customer ? $feedback->customer->full_name : 'Anonymous',
                        'created_at' => $feedback->created_at->toISOString(),
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
                \Log::error('Failed to send feedback notification: ' . $e->getMessage());
            }
        }
    }
}