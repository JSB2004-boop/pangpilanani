<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FeedbackController extends Controller
{
    public function store(Request $request)
    {
        // Validate the incoming request
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string',
        ]);

        // Send a POST request to an external API
        $response = Http::post('https://hook.us2.make.com/46sd3f82rhetafwf14ryhoy3hktu1rwj', [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'message' => $validated['message'],
        ]);

        // Return a JSON response based on the success of the POST request
        return response()->json([
            'message' => $response->successful()
                ? 'Thank you for your feedback!'
                : 'Failed to submit feedback',
            'success' => $response->successful(),
        ], $response->status());
    }
}
