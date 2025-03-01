<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Cashier\Exceptions\IncompletePayment;

class SubscriptionController extends Controller
{
    /**
     * Get the user's subscription.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $subscription = null;

        if ($user->subscription('default')) {
            $subscription = [
                'name' => $user->subscription('default')->name,
                'stripe_id' => $user->subscription('default')->stripe_id,
                'stripe_status' => $user->subscription('default')->stripe_status,
                'stripe_price' => $user->subscription('default')->stripe_price,
                'quantity' => $user->subscription('default')->quantity,
                'trial_ends_at' => $user->subscription('default')->trial_ends_at,
                'ends_at' => $user->subscription('default')->ends_at,
                'on_trial' => $user->subscription('default')->onTrial(),
                'canceled' => $user->subscription('default')->canceled(),
                'on_grace_period' => $user->subscription('default')->onGracePeriod(),
                'active' => $user->subscription('default')->active(),
            ];
        }

        return response()->json([
            'subscription_status' => $user->subscription_status,
            'subscription' => $subscription,
        ]);
    }

    /**
     * Create a new subscription.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|string',
            'price_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        try {
            // Create the subscription
            $subscription = $user->newSubscription('default', $request->price_id)
                ->create($request->payment_method);

            // Update user subscription status
            $user->subscription_status = 'premium';
            $user->save();

            return response()->json([
                'message' => 'Subscription created successfully',
                'subscription' => $subscription,
            ]);
        } catch (IncompletePayment $exception) {
            return response()->json([
                'message' => 'Payment failed',
                'payment_intent' => $exception->payment->id,
                'payment_intent_status' => $exception->payment->status,
                'payment_intent_client_secret' => $exception->payment->client_secret,
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Subscription creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing subscription.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'price_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if (!$user->subscription('default')) {
            return response()->json([
                'message' => 'No active subscription found',
            ], 404);
        }

        try {
            // Update the subscription
            $subscription = $user->subscription('default')->swap($request->price_id);

            return response()->json([
                'message' => 'Subscription updated successfully',
                'subscription' => $subscription,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Subscription update failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel the subscription.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function cancel(Request $request)
    {
        $user = $request->user();

        if (!$user->subscription('default')) {
            return response()->json([
                'message' => 'No active subscription found',
            ], 404);
        }

        try {
            // Cancel the subscription
            $subscription = $user->subscription('default')->cancel();

            // Update user subscription status
            $user->subscription_status = 'cancelled';
            $user->save();

            return response()->json([
                'message' => 'Subscription cancelled successfully',
                'subscription' => $subscription,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Subscription cancellation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the user's invoices.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function invoices(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'invoices' => $user->invoices(),
        ]);
    }
}
