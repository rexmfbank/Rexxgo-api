<?php

namespace Modules\Wallet\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\Savings;
use App\Models\SavingsTransaction;
use App\Models\UsdWalletQueue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Notification\app\Http\Controllers\NotificationController;

class BridgeWebhookController extends Controller
{

    /**
     * Handle the incoming Bridge webhook request.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bridgeWebhook(Request $request)
    {
        // 1. Get the raw payload and signature
        $rawPayload = $request->getContent();

        $signatureHeader = $request->header('x-webhook-signature');

        // if (!$signatureHeader) {
        //     return response()->json(['error' => 'Missing signature header'], 400);
        // }

        // 2. Verify the signature
        // $verification = $this->verifyWebhookSignature($rawPayload, $signatureHeader, env("BRIDGE_WEBHOOK_PUBLIC_KEY"));

        // if (!$verification['isValid']) {
        //     Log::error('Signature verification failed:', ['error' => $verification['error']]);
        //     return response()->json(['error' => 'Invalid signature'], 400);
        // }

        // 3. Process the event (Signature is valid)
        try {
            $event = json_decode($rawPayload, true);
            $this->handleWebhookEvent($event);
            return response()->json(['received' => true], 200);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to process webhook event:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid JSON'], 400);
        }
    }

    /**
     * Translates the Node.js verification logic to PHP (Verification logic remains the same).
     */
    private function verifyWebhookSignature(string $payload, string $signatureHeader, string $publicKey): array
    {
        try {
            // Parse signature header
            $parts = explode(',', $signatureHeader);
            $timestamp = null;
            $signature = null;

            foreach ($parts as $part) {
                if (str_starts_with($part, 't=')) {
                    $timestamp = substr($part, 2);
                } elseif (str_starts_with($part, 'v0=')) {
                    $signature = substr($part, 3);
                }
            }

            if (!$timestamp || !$signature) {
                return ['isValid' => false, 'error' => 'Missing timestamp or signature'];
            }

            $currentTime = (int)(microtime(true) * 1000); // Current time in milliseconds
            if (($currentTime - (int)$timestamp) > 600000) {
                return ['isValid' => false, 'error' => 'Timestamp too old'];
            }

            $signedPayload = $timestamp . '.' . $payload;

            $isValid = openssl_verify(
                $signedPayload,
                base64_decode($signature),
                $publicKey,
                OPENSSL_ALGO_SHA256
            );

            if ($isValid === 1) {
                return ['isValid' => true];
            } elseif ($isValid === 0) {
                return ['isValid' => false, 'error' => 'Signature mismatch'];
            } else {
                return ['isValid' => false, 'error' => 'OpenSSL error: ' . openssl_error_string()];
            }
        } catch (\Exception $e) {
            return ['isValid' => false, 'error' => 'Verification failed: ' . $e->getMessage()];
        }
    }

    // -------------------------------------------------------------

    /**
     * Handle the valid webhook event based on the expanded list of event types.
     */
    private function handleWebhookEvent(array $event): void
    {
        $eventType = $event['event_type'] ?? 'unknown';
        $category = $event['event_category'] ?? 'unknown';
        $objectId = $event['event_object']['id'] ?? 'N/A';
        $status = $event['event_object']['status'] ?? null;
        Log::info("Webhook received: Category: {$category}, Type: {$eventType}, ID: {$objectId}");
        Log::info($event);
        switch ($category) {
            case 'customer':
                switch ($eventType) {
                    case 'customer.created':
                    case 'customer.updated':
                        $this->handleCustomerEvent($event);
                        break;
                    case 'customer.deleted':
                        Log::info("Customer data event: Customer {$eventType}, ID: {$objectId}");
                        break;
                    case 'customer.updated.status_transitioned':
                        Log::info("Customer status changed to: {$status} for ID: {$objectId}");
                        break;
                }
                break;

            case 'kyc_link':
                $this->handleKycEvent($event);
                break;

            case 'card_transaction':
                if ($eventType === 'card_transaction.created') {
                    Log::info("New Card Transaction (Authorization): ID: {$objectId}");
                }
                if ($eventType === 'card_transaction.updated.status_transitioned') {
                }
                break;

            case 'posted_card_account_transaction':
                if ($eventType === 'posted_card_account_transaction.created') {
                }
                break;

            case 'liquidation_address.drain':
                // Logic for crypto draining
                break;
            case 'static_memo.activity':
                // Logic for static memo deposits
                break;
            case 'virtual_account.activity':
            case 'virtual_account.activity.created':
            case 'virtual_account.activity.updated':
                $this->handleVirtualAccountActivities($event);
                // Logic for virtual account activity
                break;
            case 'wallet.activity.created':
            case 'wallet.activity.updated':
            case 'wallet.transfer.completed':
            case 'transfer':
            case 'transfer.updated':
            case 'transfer.completed':
            case 'wallet.transfer.failed':
                $this->handleWalletActivities($event);
                break;
            case 'card_account':
                // Logic for card account lifecycle
                
                break;

            default:
                Log::warning("Unhandled Webhook Category: {$category} with Type: {$eventType}");
        }
    }

    private function handleCustomerEvent(array $event): void
    {
        
        
        $eventType = $event['event_type'] ?? 'unknown';
        $objectId = $event['event_object']['id'] ?? 'N/A';
        $status = $event['event_object']['status'] ?? null;

        try {
            $eventObject = $event['event_object'];
            $lastname = $eventObject['last_name'];
            $first_name = $eventObject['first_name'];
            $borrower = Borrower::where("email", $eventObject['email'])->first();
            if ($borrower) {
                $borrower->last_name = $lastname;
                $borrower->first_name = $first_name;
                $borrower->save();
            }
            $endorsements = $eventObject['endorsements'] ?? [];
            $isAwaitingApproval = false;
            foreach ($endorsements as $endorsement) {
                $status = $endorsement['status'] ?? null;
                $pendingRequirements = $endorsement['requirements']['pending'] ?? [];

                if (
                    $status === 'incomplete' &&
                    count($pendingRequirements) === 1 &&
                    in_array("adverse_media_screen", $pendingRequirements)
                ) {
                    $isAwaitingApproval = true;
                    break;
                }
            }

            if ($isAwaitingApproval) {
                $borrower = Borrower::where("email", $eventObject['email'])->first();
                if ($borrower) {
                    $borrower->kyc_status = "awaiting_approval";
                    $borrower->save();
                    
                } else {
                    Log::warning("Borrower not found for email: {$eventObject['email']}");
                }
            }
        } catch (\Throwable $th) {
            
        }
    }

    private function handleKycEvent(array $event): void
    {
        $eventType = $event['event_type'] ?? 'unknown';
        $objectId = $event['event_object']['id'] ?? 'N/A';
        $status = $event['event_object']['status'] ?? null;

        if ($eventType === 'kyc_link.updated.status_transitioned') {
            
        } else {
            
        }
        $borrower = Borrower::where("email", $event['event_object']['email'])->first();

        if ($borrower) {
            $kycStatus = $event['event_object']['kyc_status'];
            $tos_status = $event['event_object']['tos_status'];
            $borrower->tos_status = $tos_status;
            if ($kycStatus == "approved") {
                $existingTask = UsdWalletQueue::where('borrower_id', $borrower->id)->first();

                if (!$existingTask) {
                    UsdWalletQueue::create([
                        'borrower_id' => $borrower->id,
                        'bridge_customer_id' => $borrower->bridge_customer_id,
                        'status' => 'pending',
                        'retries' => 0,
                    ]);
                }
                $borrower->kyc_status = "active";
            } elseif ($kycStatus == "not_started") {
            } elseif ($kycStatus == "under_review") {
                $borrower->kyc_status = "awaiting_approval";
            } else {
                $borrower->kyc_status = $kycStatus;
            }
            $borrower->rejection_reasons = json_encode($event['event_object']['rejection_reasons'], JSON_PRETTY_PRINT);
            $borrower->save();
        }
        
    }

    private function handleVirtualAccountActivities(array $event): void
    {
        $activityType = $event['event_object']['type'] ?? null;
        $amount = $event['event_object']['amount'] ?? 0;
        $customerId = $event['event_object']['customer_id'] ?? null;

        $paymentId = $event['event_object']['deposit_id'] ?? null;
        $createdAt = $event['event_object']['created_at'] ?? null;
        $virtualAccountId = $event['event_object']['virtual_account_id'] ?? null;
        $wallet = Savings::where('bridge_id', $virtualAccountId)->first();
        if (!$wallet) {
            Log::error("Wallet not found for Virtual Account ID: {$virtualAccountId}");
            return;
        }

        $source = $event['event_object']['source'] ?? null;
        $senderName = $source['sender_name'] ?? 'N/A';
        $sender_bank_routing_number = $source['sender_bank_routing_number'] ?? 'N/A';
        $trace_number = $source['trace_number'] ?? 'N/A';
        $description = $source['description'] ?? 'N/A';
        $payment_rail = $source['payment_rail'] ?? 'N/A';

        $borrower = Borrower::where('bridge_customer_id', $customerId)->first();

        if (!$borrower) {
            
            return;
        }

        $wallet = Savings::where('bridge_id', $virtualAccountId)->first();
        if (!$wallet) {
            Log::error("Wallet not found for Virtual Account ID: {$virtualAccountId} for Borrower #{$borrower->id}");
            return;
        }

        $transaction = SavingsTransaction::where("external_tx_id", $paymentId)->first();

        if (!$transaction && in_array($activityType, ['payment_submitted', 'funds_received', 'payment_processed', 'payment_failed', 'cancelled'])) {
            $carbonDate = \Carbon\Carbon::parse($createdAt);
            $timeOnly = $carbonDate->format('H:i:s');
            $dateOnly = $carbonDate->format('Y-m-d');
            $reference = "REX-USD-" . date("Ymdhsi") . '-' . uniqid();

            $initialStatus = ($activityType === 'funds_received' || $activityType === 'payment_processed') ? 'completed' : (($activityType === 'payment_failed' || $activityType === 'cancelled') ? 'failed' : 'pending');

            $finalWalletBalance = $wallet->available_balance;
            if ($initialStatus === 'completed') {
                $finalWalletBalance += $amount;
            } else {
            }

            $transaction = SavingsTransaction::create([
                "reference" => $reference,
                "borrower_id" => $borrower->id,
                "savings_id" => $wallet->id,
                "transaction_amount" => $amount,
                "balance" => $finalWalletBalance,
                "transaction_date" => $dateOnly,
                "transaction_time" => $timeOnly,
                "transaction_type" => "credit",
                "transaction_description" => "Deposit: from {$senderName} | {$description}",
                "credit" => $amount,
                "status_id" => $initialStatus,
                "currency" => "USD",
                "external_response" => json_encode($event, JSON_PRETTY_PRINT),
                "external_tx_id" => $paymentId,
                "provider" => "bridge",
                "category" => "fund_received",
                "details" => json_encode([
                    "currency" =>  "USD",
                    "amount" => $amount,
                    "sender_bank_routing_number" => $sender_bank_routing_number,
                    "trace_number" => $trace_number,
                    "description" => $description,
                    "payment_rail" => $payment_rail,
                ], JSON_PRETTY_PRINT)
            ]);


            if ($initialStatus === 'completed') {
                $wallet->increment('available_balance', $amount);
                $notificationMessage = 'You just recieved USD ' . $amount . ' from ' . $senderName . '.';
                $notificationController = new NotificationController();
                $notificationRequest = [
                    'type' => 'transaction',
                    'notifiable_type' => 'transaction',
                    'borrower_id' => $borrower->id,
                    'data' => [
                        'message' => $notificationMessage,
                        'data' => $transaction,
                    ],
                    'company_id' => null,
                    'branch_id' => null,
                ];

                $notificationController->createNotification($notificationRequest);
                
            } else {
                
            }
            return;
        }


        if ($transaction) {
            $currentStatus = $transaction->status_id;
            $shouldUpdateWallet = false;
            $newStatus = $currentStatus;

            switch ($activityType) {
                case 'payment_submitted':
                    if ($currentStatus === 'pending') {
                        
                    }
                    break;

                case 'funds_received':
                case 'payment_processed':
                    if ($currentStatus != 'completed') {
                        $newStatus = 'completed';
                        $shouldUpdateWallet = true;
                        $notificationMessage = 'You just recieved USD ' . $amount . ' from ' . $senderName . '.';
                        $notificationController = new NotificationController();
                        $notificationRequest = [
                            'type' => 'transaction',
                            'notifiable_type' => 'transaction',
                            'borrower_id' => $borrower->id,
                            'data' => [
                                'message' => $notificationMessage,
                                'data' => $transaction,
                            ],
                            'company_id' => null,
                            'branch_id' => null,
                        ];

                        $notificationController->createNotification($notificationRequest);
                        
                    } else if ($currentStatus === 'completed') {
                        Log::warning("Payment already {$activityType} received for {$paymentId}. Already completed. Idempotency check passed.");
                    }
                    break;

                case 'payment_failed':
                case 'cancelled':
                    if ($currentStatus === 'pending') {
                        $newStatus = 'failed';
                        Log::warning("❌ Payment {$activityType} received for {$paymentId}. Changing from pending to failed.");
                    } else if ($currentStatus === 'completed') {
                        Log::error("🚨 Completed payment {$paymentId} received a {$activityType} event! Manual review required.");
                    }
                    break;

                default:
                    
                    break;
            }

            if ($newStatus !== $currentStatus) {
                $updateData = [
                    "status_id" => $newStatus,
                    "external_response" => json_encode($event, JSON_PRETTY_PRINT),
                    "data" => json_encode($event, JSON_PRETTY_PRINT),
                ];

                if ($shouldUpdateWallet) {
                    $finalWalletBalance = $wallet->available_balance + $amount;

                    $updateData["balance"] = $finalWalletBalance; // <--- UPDATED: Sets the balance after this transaction

                    $wallet->increment('available_balance', $amount);
                }

                $transaction->update($updateData);
            }
        }
    }


    protected function handleWalletActivities($event)
    {
        
        $object = $event['event_object'] ?? [];
        $activityType = $event['event_object_status'] ?? null;
        if($activityType == "in_review") return;

        $status = $this->mapEventToStatus($activityType);

        $externalReference = $event['event_object_id'] ?? null;
        $clientReferenceId = $object['client_reference_id'] ?? null;
        $amount = $object['receipt']['final_amount'] ?? 0;
        $currency = strtoupper($object['currency'] ?? 'USD');
        $fromWallet = $object['source']['bridge_wallet_id'] ?? null;
        $toAddress = $object['destination']['to_address'] ?? null;
        $paymentRail = $object['destination']['payment_rail'] ?? null;
        $txHash = $object['receipt']['destination_tx_hash'] ?? null;
        $receiptUrl = $object['receipt']['url'] ?? null;
        $updatedAt = $object['updated_at'] ?? now();
        $createdAt = $object['created_at'] ?? now();
        $carbonDate = \Carbon\Carbon::parse($createdAt);
        $timeOnly = $carbonDate->format('H:i:s');
        $dateOnly = $carbonDate->format('Y-m-d');


        //check if the transfer was made on this platform 
        $isTransactionInitiatedHere = SavingsTransaction::where("reference", $clientReferenceId)->first();
        if($isTransactionInitiatedHere){
            $isTransactionInitiatedHere->status_id = $status;
            $isTransactionInitiatedHere->save();
        }
        $wallet = Savings::where('account_number', $toAddress)->orWhere('destination_address', $toAddress)->first();

        if (!$wallet) {
            Log::error("Wallet not found for Virtual Account ID: {$toAddress}");
            return;
        }
        $borrower = Borrower::where('id', $wallet->borrower_id)->first();
            Log::error("Borrower found for transaction {$externalReference}");
        
        $reference = "REX-" . $wallet->currency . "-" . date("Ymdhsi") . '-' . $borrower->id . uniqid();

        $finalWalletBalance = $wallet->available_balance;
        

        $isExist = SavingsTransaction::where("external_tx_id", $externalReference)->first();
        if($isExist){
            if($isExist->status_id == "completed"){
                
                return;
            }
            $reference = $isExist->reference;
            $finalWalletBalance = $isExist->balance;
        }

        if ($status === 'completed') {
            $finalWalletBalance += $amount;
            $wallet->increment('available_balance', $amount);
            $wallet->increment('ledger_balance', $amount);
        } 
        $category = "fund_received";
        $details = [
            "currency" =>  "USDC",
            "amount" => $amount,
            "transaction_hash" => $txHash
        ];
        if($wallet->currency == "USD"){
            $details = [
                "currency" =>  "USD",
                "amount" => $amount,
                "transaction_hash" => $txHash
            ];
        }
        if($isTransactionInitiatedHere){
            $category = "fund_converted";
        }
        SavingsTransaction::updateOrCreate(
            ['external_tx_id' => $externalReference],
            [
                "reference" => $reference,
                "borrower_id" => $borrower->id,
                "savings_id" => $wallet->id,
                "transaction_amount" => $amount,
                "balance" => $finalWalletBalance,
                "transaction_date" => $dateOnly,
                "transaction_time" => $timeOnly,
                "transaction_type" => "credit",
                "transaction_description" => "",
                "credit" => $amount,
                "status_id" => $status,
                "currency" => $wallet->currency,
                "external_response" => json_encode($event, JSON_PRETTY_PRINT),
                "external_tx_id" => $externalReference,
                "provider" => "bridge",
                "category" => $category,
                "details" => json_encode($details, JSON_PRETTY_PRINT)
            ]
        );
    }
    private function mapEventToStatus(string $eventType): string
    {
        return match ($eventType) {
            'payment_created',
            'payment_pending',
            'in_review',
            'payment_submitted',
            'wallet.transaction.created',
            'wallet.transaction.pending'   => 'pending',

            'payment_failed',
            'payment_canceled',
            'payment_cancelled',
            'wallet.transaction.failed',
            'wallet.transaction.canceled',
            'refunded',
            'wallet.transaction.cancelled' => 'failed',

            'wallet.transaction.confirmed',
            'wallet.transaction.received',
            'wallet.transaction.completed',
            'payment_confirmed',
            'payment_received',
            'payment_completed',
            'payment_processed',
            'funds_received',
            'wallet.transaction.processed' => 'completed',

            default => 'pending',
        };
    }
}
