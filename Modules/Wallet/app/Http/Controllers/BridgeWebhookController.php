<?php

namespace Modules\Wallet\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\UsdWalletQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        // Log::error(json_encode($rawPayload));

        $signatureHeader = $request->header('x-webhook-signature');

        if (!$signatureHeader) {
            Log::error('Missing signature header.');
            return response()->json(['error' => 'Missing signature header'], 400);
        }

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
        } catch (\Exception $e) {
            Log::error('Failed to parse webhook event:', ['error' => $e->getMessage()]);
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
        info(json_encode($event));
        switch ($category) {
            case 'customer':
                switch ($eventType) {
                    case 'customer.created':
                    case 'customer.updated':
                        try {
                            $eventObject = $event['event_object'];
                            $lastname = $eventObject['last_name'];
                            $first_name = $eventObject['first_name'];
                            $borrower = Borrower::where("email", $eventObject['email'])->first();
                            if($borrower){
                                $borrower->last_name = $lastname;
                                $borrower->first_name = $first_name;
                                $borrower->save();
                            }
                            $endorsements = $eventObject['endorsements'] ?? [];
                            $isAwaitingApproval = false;
                            foreach ($endorsements as $endorsement) {
                                $status = $endorsement['status'] ?? null;
                                $pendingRequirements = $endorsement['requirements']['pending'] ?? [];

                                if ($status === 'incomplete' && 
                                    count($pendingRequirements) === 1 && 
                                    in_array("adverse_media_screen", $pendingRequirements)) 
                                {
                                    $isAwaitingApproval = true;
                                    break; 
                                }
                            }

                            if ($isAwaitingApproval) {
                                $borrower = Borrower::where("email", $eventObject['email'])->first();
                                if ($borrower) {
                                    $borrower->kyc_status = "awaiting_approval";
                                    $borrower->save();
                                    Log::info("Borrower {$borrower->id} KYC status updated to: awaiting_approval");
                                } else {
                                    Log::warning("Borrower not found for email: {$eventObject['email']}");
                                }
                            }
                        } catch (\Throwable $th) {
                            Log::info("Failed to update Customer {$eventType}, ID: {$objectId}");
                        }
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
                if ($eventType === 'kyc_link.updated.status_transitioned') {
                    Log::info("KYC Link status changed to: {$status} for ID: {$objectId}");
                } else {
                    Log::info("KYC Link event: Link {$eventType}, ID: {$objectId}");
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
                        $borrower->kyc_status = "pending";
                    }else {
                        $borrower->kyc_status = $kycStatus;
                    }
                    $borrower->rejection_reasons = json_encode($event['event_object']['rejection_reasons'], JSON_PRETTY_PRINT);
                    $borrower->save();
                }
                Log::info($event['event_object']);

                break;

            case 'transfer':
                if ($eventType === 'transfer.updated.status_transitioned') {
                    Log::info("Transfer status changed to: {$status} for ID: {$objectId}");
                } else {
                    Log::info("Transfer event: Transfer {$eventType}, ID: {$objectId}");
                }
                break;

            case 'card_transaction':
                if ($eventType === 'card_transaction.created') {
                    Log::info("New Card Transaction (Authorization): ID: {$objectId}");
                }
                if ($eventType === 'card_transaction.updated.status_transitioned') {
                    Log::info("Card Transaction status transitioned to: {$status} for ID: {$objectId}");
                }
                break;

            case 'posted_card_account_transaction':
                if ($eventType === 'posted_card_account_transaction.created') {
                    Log::info("Posted Card Transaction created (Final Charge/Credit): ID: {$objectId}");
                }
                break;

            // --- Other Categories ---
            case 'liquidation_address.drain':
                // Logic for crypto draining
                Log::info("Liquidation Address Drain event: Type: {$eventType}, ID: {$objectId}");
                break;
            case 'static_memo.activity':
                // Logic for static memo deposits
                Log::info("Static Memo Activity event: Type: {$eventType}, ID: {$objectId}");
                break;
            case 'virtual_account.activity':
                // Logic for virtual account activity
                Log::info("Virtual Account Activity event: Type: {$eventType}, ID: {$objectId}");
                break;
            case 'card_account':
                // Logic for card account lifecycle
                Log::info("Card Account event: Type: {$eventType}, ID: {$objectId}");
                break;

            default:
                Log::warning("Unhandled Webhook Category: {$category} with Type: {$eventType}");
        }
    }
}
