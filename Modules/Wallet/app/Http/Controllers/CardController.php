<?php

namespace Modules\Wallet\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Savings;
use App\Models\SavingsTransaction;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Modules\Wallet\app\Http\Resources\WalletResource;
use Modules\Wallet\Services\BridgeService;
use Modules\Wallet\Services\RexMfbService;
use Modules\Wallet\Services\StripeService;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Stripe;
use Stripe\Token;
use Stripe\SetupIntent;

class CardController extends Controller
{

    use ApiResponse;
    protected $stripeService;


    // 2. Inject the service into the constructor
    public function __construct(StripeService $stripeService)
    {

        $this->stripeService = $stripeService;
    }



    /**
     * @OA\Get(
     *     path="/api/cards",
     *     tags={"Cards"},
     *     summary="Get user's saved cards",
     *     description="Returns all saved debit/credit cards for the authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Cards retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cards retrieved"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="brand", type="string", example="visa"),
     *                     @OA\Property(property="last4", type="string", example="4242"),
     *                     @OA\Property(property="exp_month", type="integer", example=12),
     *                     @OA\Property(property="exp_year", type="integer", example=2028),
     *                     @OA\Property(property="payment_method_id", type="string", example="pm_1Sma3LJPyplrmPSHxyz"),
     *                     @OA\Property(property="created_at", type="string", example="2025-01-09 14:22:11")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getUserCards()
    {
        $borrower = auth()->guard('borrower')->user();

        $cards = Card::where('borrower_id', $borrower->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($card) {
                return [
                    'id' => $card->id,
                    'brand' => $card->brand,
                    'last4' => $card->last4,
                    'exp_month' => $card->exp_month,
                    'exp_year' => $card->exp_year,
                    'payment_method_id' => $card->payment_method_id,
                    'created_at' => $card->created_at->toDateTimeString(),
                ];
            });
            return $this->success($cards);
    }


    /**
     * @OA\Post(
     *   path="/api/cards/new/init",
     *   tags={"Cards"},
     *   summary="Create add card intent",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Success"),
     *   @OA\Response(response=400, description="Bad Request"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function createSetupIntent()
    {
        $borrower = auth()->guard('borrower')->user();

        $customerId = $this->stripeService->getOrCreateCustomer($borrower);

        $intent = SetupIntent::create([
            'customer' => $customerId,
            'payment_method_types' => ['card'],
        ]);

        return $this->success([
            'client_secret' => $intent->client_secret
        ]);
    }



    /**
     * @OA\Post(
     *     path="/api/cards/save",
     *     tags={"Cards"},
     *     summary="Save card after Stripe setup intent",
     *     description="Saves a card to the authenticated borrower using a Stripe SetupIntent",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"setup_intent_id"},
     *             @OA\Property(
     *                 property="setup_intent_id",
     *                 type="string",
     *                 example="seti_1Sma3LJPyplrmPSHabc123"
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Card saved successfully"),
     *     @OA\Response(response=400, description="Invalid request"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'setup_intent_id' => 'required|string',
        ]);
        $data = $request->all();
        $borrower = auth()->guard('borrower')->user();

        $this->stripeService->getOrCreateCustomer($borrower);

        try {
            // Retrieve SetupIntent
            $setupIntent = SetupIntent::retrieve($data["setup_intent_id"]);

            if (!$setupIntent->payment_method) {
                return $this->error('No payment method attached to setup intent');
            }

            // Retrieve Payment Method
            $paymentMethod = PaymentMethod::retrieve($setupIntent->payment_method);

            // Prevent duplicate cards
            $exists = Card::where('borrower_id', $borrower->id)
                ->where('payment_method_id', $paymentMethod->id)
                ->exists();

            if ($exists) {
                return $this->error('Card already exists');
            }


            $exists = Card::where('borrower_id', $borrower->id)
            ->where('last4', $paymentMethod->card->last4)
            ->where('brand', $paymentMethod->card->brand)
            ->where('exp_month', $paymentMethod->card->exp_month)
            ->where('exp_year', $paymentMethod->card->exp_year)
            ->exists();

        if ($exists) {
                            return $this->error('Card already exists');

        }

            // Save card locally
            $card = Card::create([
                'borrower_id'        => $borrower->id,
                'payment_method_id'  => $paymentMethod->id,
                'brand'              => $paymentMethod->card->brand,
                'last4'              => $paymentMethod->card->last4,
                'exp_month'          => $paymentMethod->card->exp_month,
                'exp_year'           => $paymentMethod->card->exp_year,
                'is_default'         => false,
            ]);

            return $this->success($card, 'Card saved successfully');
        } catch (\Throwable $e) {
            Log::error('Save card failed', [
                'error' => $e->getMessage()
            ]);

            return $this->error('Unable to save card');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/cards/charge-card",
     *     tags={"Cards"},
     *     summary="Debit card and credit wallet",
     *     description="Charges a saved card using Stripe and credits the user's wallet",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"wallet_id","payment_method_id","amount"},
     *             @OA\Property(property="wallet_id", type="integer", example=1),
     *             @OA\Property(property="payment_method_id", type="string", example="pm_1Sma3LJPyplrmPSHxyz"),
     *             @OA\Property(property="amount", type="number", format="float", example=50.00)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Wallet credited successfully"),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Payment failed")
     * )
     */
    public function chargeCard(Request $request)
    {
        $request->validate([
            'wallet_id' => 'required|integer|exists:savings,id',
            'payment_method_id' => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        $borrower = auth()->guard('borrower')->user();

        $wallet = Savings::where('id', $request->wallet_id)
            ->where('borrower_id', $borrower->id)
            ->firstOrFail();

        try {
            DB::beginTransaction();

            // 1️⃣ Charge card
            $intent = PaymentIntent::create([
                'amount' => (int) ($request->amount * 100), // cents
                'currency' => strtolower($wallet->currency ?? 'usd'),
                'customer' => $borrower->stripe_customer_id,
                'payment_method' => $request->payment_method_id,
                'off_session' => true,
                'confirm' => true,
                'description' => 'Wallet funding',
                'metadata' => [
                    'borrower_id' => $borrower->id,
                    'wallet_id' => $wallet->id,
                ],
            ]);

            if ($intent->status !== 'succeeded') {
                throw new \Exception('Payment not successful');
            }

            // 2️⃣ Credit wallet
            $wallet->balance += $request->amount;
            $wallet->save();

            // 3️⃣ Save transaction
            SavingsTransaction::create([
                'borrower_id' => $borrower->id,
                'savings_id' => $wallet->id,
                'reference' => $intent->id,
                'transaction_amount' => $request->amount,
                'balance' => $wallet->balance,
                'currency' => $wallet->currency,
                'transaction_type' => 'credit',
                'transaction_description' => 'Wallet funded via card',
                'provider' => 'stripe',
                'external_tx_id' => $intent->id,
                'external_response' => json_encode($intent),
                'status_id' => 'success',
                'transaction_date' => now()->toDateString(),
                'transaction_time' => now()->toTimeString(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Wallet funded successfully',
                'data' => [
                    'wallet_balance' => $wallet->balance,
                    'payment_intent' => $intent->id,
                ]
            ]);
        } catch (\Stripe\Exception\CardException $e) {
            DB::rollBack();
            return $this->error($e->getError()->message);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Card charge failed', [
                'error' => $e->getMessage()
            ]);
            return $this->error('Unable to charge card');
        }
    }


    /**
     * @OA\Delete(
     *     path="/api/cards/{card_id}",
     *     tags={"Cards"},
     *     summary="Remove a saved card",
     *     description="Detaches a saved card from Stripe and removes it from the user's account",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="card_id",
     *         in="path",
     *         required=true,
     *         description="Card ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Card removed successfully"
     *     ),
     *     @OA\Response(response=404, description="Card not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function removeCard($card_id)
    {
        $borrower = auth()->guard('borrower')->user();

        $card = Card::where('id', $card_id)
            ->where('borrower_id', $borrower->id)
            ->first();

        if (!$card) {
            return $this->error('Card not found');

        }

        try {
            // Detach card from Stripe customer
            $paymentMethod = PaymentMethod::retrieve($card->payment_method_id);
            $paymentMethod->detach();

            // Remove locally (or soft delete)
            $card->delete();
            return $this->success([], 'Card removed successfully');
        } catch (\Exception $e) {
            Log::error('Remove card failed', [
                'card_id' => $card_id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Unable to remove card at this time');
        }
    }
}
