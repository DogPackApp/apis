<?php

namespace App\Services\Order\Integrations;

use App\Models\Order\Avalara;
use App\Models\Order\StoreOrder;
use Avalara\AvaTaxClient;
use Exception;

class AvalaraService
{
    protected $client;

    const DOC_TYPE = 'SalesInvoice';

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->client = new AvaTaxClient(
            'DogPack Local',
            '1.0',
            'localhost',
            config('services.avalara.environment')
        );

        $this->client->withSecurity(
            config('services.avalara.email'),
            config('services.avalara.password'),
        );

    }

    public function estimate(array $orderData)
    {
        try {
            $response = $this->client->createTransaction(null, [
                'type' => AvalaraService::DOC_TYPE,
                'companyCode' => config('services.avalara.code'),
                'date' => now()->format('Y-m-d'),
                'customerCode' => $orderData['customer_code'],
                'purchaseOrderNo' => now()->format('Y-m-d'),
                'addresses' => [
                    'ShipFrom' => [
                        'line1' => $orderData['store_address']['line1'],
                        'city' => $orderData['store_address']['city'],
                        'region' => $orderData['store_address']['region'],
                        'postalCode' => $orderData['store_address']['postalCode'],
                        'country' => $orderData['store_address']['country'],
                    ],
                    'ShipTo' => [
                        'line1' => $orderData['address']['line1'],
                        'city' => $orderData['address']['city'],
                        'region' => $orderData['address']['region'],
                        'postalCode' => $orderData['address']['postalCode'],
                        'country' => $orderData['address']['country'],
                    ],
                ],
                'lines' => $orderData['items'],
                'currencyCode' => $orderData['currency'] ?? 'CAD',
                'commit' => false,
            ]);
            if (is_object($response) && get_class($response) === 'stdClass') {
                return $response;
            } else {
                try {
                    \DB::table('avalara_error_logs')->insert([
                        'order_data' => json_encode($orderData),
                        'error' => is_array($response) || is_object($response) ? json_encode($response) : (string) $response,
                        'ip' => request()->ip() ?? 'unknown',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (Exception $ex) {
                    // Optionally log or handle DB error
                } catch (\Throwable $ex) {
                    // Optionally log or handle DB error
                }

                return [];
            }
        } catch (Exception $e) {

            $errors = [
                'message' => $e->getMessage(),
            ];
            try {
                \DB::table('avalara_error_logs')->insert([
                    'order_data' => json_encode($orderData),
                    'error' => json_encode($errors),
                    'ip' => request()->ip() ?? 'unknown',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (Exception $ex) {

            } catch (\Throwable $ex) {

            }

            return [];
        }
    }

    public function commit(array $transactionCodes): bool
    {
        try {
            $commitModel = [
                'commit' => true,
            ];

            foreach ($transactionCodes as $transactionCode) {
                $result = $this->client->commitTransaction(
                    config('services.avalara.code'),
                    $transactionCode,
                    'SalesInvoice', // documentType (optional)
                    'Summary', // include (optional)
                    $commitModel // THIS is the required request body
                );

                if (! isset($result->status) || $result->status !== 'Committed') {
                    throw new Exception("Transaction {$transactionCode} failed to commit");
                }
            }

            return true;
        } catch (Exception $e) {
            logger()->error('Avalara commit failed', [
                'error' => $e->getMessage(),
                'transaction' => $transactionCode ?? null, // safe fallback
            ]);
        }

        return true;
    }

    public function cancel(StoreOrder $order): bool
    {
        $order = $order->loadMissing(['avalara']);

        if (! $order->avalara) {
            return false;
        }

        // string $transactionCode, string $refundTransactionCode, string $refundDate, string $referenceCode = null
        try {
            $refundModel = [
                'refundTransactionCode' => $order->uuid,
                'refundDate' => now()->format('Y-m-d'),
                'refundType' => 'Full', // You can make this configurable if needed
                'referenceCode' => $referenceCode ?? 'Refund for a committed transaction',
            ];

            $result = $this->client->refundTransaction(
                config('services.avalara.code'),
                $order->avalara->code,
                null, // include
                null, // documentType
                null, // useTaxDateOverride
                $refundModel
            );

            if (! isset($result->status) || $result->status !== 'Committed') {
                throw new Exception("Transaction {$order->avalara->code} refund failed");
            }

            return true;
        } catch (Exception $e) {
            logger()->error("Avalara refund failed for transaction code {$order->uuid}", [
                'error' => $e->getMessage(),
                'transaction' => $transactionCode ?? null,
                'refundTxn' => $refundTransactionCode ?? null,
            ]);
        }

        return false;
    }

    public function partial(StoreOrder $order, array $refundableLines = []): bool
    {
        $order = $order->loadMissing(['avalara']);

        if (! $order->avalara) {
            return false;
        }

        try {
            // fetch original Avalara transaction
            $transaction = $this->client->getTransactionByCode(
                config('services.avalara.code'),
                $order->avalara->code
            );

            if (empty($transaction->lines)) {
                throw new Exception("No lines found in Avalara transaction {$order->avalara->code}");
            }

            // build ReturnInvoice lines
            $lines = [];
            foreach ($refundableLines as $refund) {
                // $refund = ['lineNumber' => '1', 'quantity' => 2, 'amount' => 20.00]

                $avalaraLine = collect($transaction->lines)
                    ->firstWhere('lineNumber', $refund['lineNumber']);

                if (! $avalaraLine) {
                    logger()->warning("Refund skipped - line {$refund['lineNumber']} not found in Avalara transaction");

                    continue;
                }

                $lines[] = [
                    'number' => $avalaraLine->lineNumber,
                    'itemCode' => $avalaraLine->itemCode ?? $avalaraLine->lineNumber,
                    'quantity' => $refund['quantity'],           // positive quantity
                    'amount' => -1 * abs($refund['amount']),   // negative amount
                    'description' => "Refund for line {$avalaraLine->lineNumber}",
                ];
            }

            if (empty($lines)) {
                throw new Exception("No valid refund lines matched Avalara transaction {$order->avalara->code}");
            }

            // build ReturnInvoice payload
            $payload = [
                'type' => 'ReturnInvoice',
                'companyCode' => config('services.avalara.code'),
                'date' => now()->format('Y-m-d'),
                'customerCode' => $transaction->customerCode,
                'purchaseOrderNo' => $order->order_no,
                'commit' => true,
                'currencyCode' => $transaction->currencyCode ?? 'USD',
                'description' => "Partial Refund for Order {$order->uuid}",
                'lines' => $lines,
                'addresses' => [
                    'shipFrom' => [
                        'line1' => $transaction->addresses[1]->line1,
                        'city' => $transaction->addresses[1]->city,
                        'region' => $transaction->addresses[1]->region,
                        'country' => $transaction->addresses[1]->country,
                        'postalCode' => $transaction->addresses[1]->postalCode,
                    ],
                    'shipTo' => [
                        'line1' => $transaction->addresses[0]->line1,
                        'city' => $transaction->addresses[0]->city,
                        'region' => $transaction->addresses[0]->region,
                        'country' => $transaction->addresses[0]->country,
                        'postalCode' => $transaction->addresses[0]->postalCode,
                    ],
                ],

            ];

            // call Avalara CreateTransaction because for indivual items we need to use this instead of refund
            $result = $this->client->createTransaction(null, $payload);

            if (! isset($result->status) || $result->status !== 'Committed') {
                throw new Exception('Avalara ReturnInvoice failed: '.json_encode($result));
            }

            return true;

        } catch (Exception $e) {
            logger()->error("Avalara ReturnInvoice failed for transaction {$order->uuid}", [
                'error' => $e->getMessage(),
                'transaction' => $order->avalara->code,
            ]);
        }

        return false;
    }

    public function delivery(StoreOrder $order): bool
    {
        $order = $order->loadMissing(['avalara']);

        if (! $order->avalara) {
            return false;
        }

        try {
            // fetch original Avalara transaction
            $transaction = $this->client->getTransactionByCode(
                config('services.avalara.code'),
                $order->avalara->code
            );

            if (empty($transaction->lines)) {
                throw new Exception("No lines found in Avalara transaction {$order->avalara->code}");
            }

            $lines = [];

            foreach (collect($transaction->lines) as $avalaraLine) {
                if (($avalaraLine->description === 'DELIVERY_FEE_FLAT_RATE' ||
                    $avalaraLine->description === 'DELIVERY_FEE_EASY_POST') && $avalaraLine->lineAmount > 0
                ) {
                    $lines[] = [
                        'number' => $avalaraLine->lineNumber,
                        'itemCode' => $avalaraLine->itemCode ?? $avalaraLine->lineNumber,
                        'quantity' => 1,           // positive quantity
                        'amount' => -1 * abs($avalaraLine->lineAmount),   // negative amount
                        'description' => 'Refund for delivery',
                    ];
                }
            }

            if (empty($lines)) {
                throw new Exception("No valid refund lines matched Avalara transaction {$order->avalara->code}");
            }

            // build ReturnInvoice payload
            $payload = [
                'type' => 'ReturnInvoice',
                'companyCode' => config('services.avalara.code'),
                'date' => now()->format('Y-m-d'),
                'customerCode' => $transaction->customerCode,
                'purchaseOrderNo' => $order->order_no,
                'commit' => true,
                'currencyCode' => $transaction->currencyCode ?? 'USD',
                'description' => "Partial Refund for Order {$order->uuid}",
                'lines' => $lines,
                'addresses' => [
                    'shipFrom' => [
                        'line1' => $transaction->addresses[1]->line1,
                        'city' => $transaction->addresses[1]->city,
                        'region' => $transaction->addresses[1]->region,
                        'country' => $transaction->addresses[1]->country,
                        'postalCode' => $transaction->addresses[1]->postalCode,
                    ],
                    'shipTo' => [
                        'line1' => $transaction->addresses[0]->line1,
                        'city' => $transaction->addresses[0]->city,
                        'region' => $transaction->addresses[0]->region,
                        'country' => $transaction->addresses[0]->country,
                        'postalCode' => $transaction->addresses[0]->postalCode,
                    ],
                ],

            ];

            // call Avalara CreateTransaction because for indivual items we need to use this instead of refund
            $result = $this->client->createTransaction(null, $payload);

            if (! isset($result->status) || $result->status !== 'Committed') {
                throw new Exception('Avalara ReturnInvoice failed: '.json_encode($result));
            }

            return true;

        } catch (Exception $e) {
            logger()->error("Avalara ReturnInvoice failed for transaction {$order->uuid}", [
                'error' => $e->getMessage(),
                'transaction' => $order->avalara->code,
            ]);
        }

        return false;
    }

    public function save(array $transactionCodes): bool
    {
        if (empty($transactionCodes)) {
            return false;
        }

        $codes = [];

        foreach ($transactionCodes as $transactionCode) {

            $codes[] = [
                'store_order_id' => $transactionCode['storeOrderId'],
                'code' => $transactionCode['code'],
            ];
        }

        Avalara::insert($codes);

        return true;
    }

    public function refund(StoreOrder $order, float $amount, string $reason = 'Refunding for Avalara'): bool
    {
        $order = $order->loadMissing(['avalara']);

        if (! $order->avalara) {
            return false;
        }

        try {
            $refundDocCode = $order->uuid;

            $transaction = $this->client->getTransactionByCode(
                config('services.avalara.code'),
                $order->avalara->code
            );

            $refundTransaction = [
                'type' => 'ReturnInvoice',
                'commit' => true,
                'date' => now()->format('Y-m-d'),
                'customerCode' => $transaction->customerCode,
                'currencyCode' => $transaction->currencyCode ?? 'USD',
                'referenceCode' => $order->avalara->code, // links back to original
                'lines' => [
                    [
                        'number' => '1',
                        'description' => $reason,
                        'amount' => -abs($amount), // negative refund line
                    ],
                ],
                'taxOverride' => [
                    'type' => 'TaxDate',
                    'taxDate' => $order->created_at->toDateString(),
                    'reason' => 'Align with original tax date for refund',
                ],
                'addresses' => [
                    'shipFrom' => [
                        'line1' => $transaction->addresses[1]->line1,
                        'city' => $transaction->addresses[1]->city,
                        'region' => $transaction->addresses[1]->region,
                        'country' => $transaction->addresses[1]->country,
                        'postalCode' => $transaction->addresses[1]->postalCode,
                    ],
                    'shipTo' => [
                        'line1' => $transaction->addresses[0]->line1,
                        'city' => $transaction->addresses[0]->city,
                        'region' => $transaction->addresses[0]->region,
                        'country' => $transaction->addresses[0]->country,
                        'postalCode' => $transaction->addresses[0]->postalCode,
                    ],
                ],
            ];

            $result = $this->client->createTransaction(
                config('services.avalara.code'),
                $refundTransaction
            );

            if (! isset($result->status) || $result->status !== 'Committed') {
                throw new Exception("Goodwill refund failed for transaction {$order->avalara->code}");
            }

            return true;
        } catch (Exception $e) {
            logger()->error("Avalara goodwill refund failed for order {$order->uuid}", [
                'error' => $e->getMessage(),
                'transaction' => $order->avalara->code ?? null,
                'refundDoc' => $refundDocCode ?? null,
            ]);
        }

        return false;
    }

    /**
     * Post Stripe invoice to Avalara with tax override
     * This method registers Stripe-calculated tax to Avalara for compliance and reporting
     *
     * @param  array  $invoice  Stripe invoice data
     * @param  array  $customerAddress  Customer address from Stripe
     * @param  string  $customerCode  Store/Seller identifier (Store UUID)
     * @param  int  $maxRetries  Maximum number of retry attempts
     * @return array|false Returns transaction data on success, false on failure
     */
    public function postStripeInvoiceToAvalara(array $invoice, array $customerAddress, string $customerCode, int $maxRetries = 3): array|false
    {
        $retryCount = 0;
        $lastError = null;

        while ($retryCount < $maxRetries) {
            try {
                // Fixed company address (ShipFrom)
                $companyAddress = [
                    'line1' => '9999 Boul Cavendish',
                    'city' => 'Montreal',
                    'region' => 'Quebec',
                    'postalCode' => 'H4L 1Y5',
                    'country' => 'Canada',
                ];

                // Extract invoice data
                $subtotal = ($invoice['subtotal'] ?? 0) / 100; // Convert from cents to dollars
                $taxAmount = ($invoice['tax'] ?? 0) / 100; // Convert from cents to dollars
                $currency = strtoupper($invoice['currency'] ?? 'USD');
                $invoiceDate = isset($invoice['created']) ? date('Y-m-d', $invoice['created']) : now()->format('Y-m-d');
                $invoiceId = $invoice['id'] ?? '';

                // Build Avalara transaction payload
                $transaction = [
                    'type' => self::DOC_TYPE,
                    'companyCode' => config('services.avalara.code'),
                    'date' => $invoiceDate,
                    'customerCode' => $customerCode,
                    'purchaseOrderNo' => $invoiceId,
                    'addresses' => [
                        'ShipFrom' => $companyAddress,
                        'ShipTo' => [
                            'line1' => $customerAddress['line1'] ?? '',
                            'line2' => $customerAddress['line2'] ?? '',
                            'city' => $customerAddress['city'] ?? '',
                            'region' => $customerAddress['state'] ?? $customerAddress['region'] ?? '',
                            'postalCode' => $customerAddress['postal_code'] ?? $customerAddress['postalCode'] ?? '',
                            'country' => $customerAddress['country'] ?? '',
                        ],
                    ],
                    'lines' => [
                        [
                            'number' => '1',
                            'quantity' => 1,
                            'amount' => $subtotal,
                            'description' => 'Subscription Payment',
                            // IMPORTANT: taxOverride means "use tax I give you"
                            'taxOverride' => [
                                'type' => 'TaxAmount',
                                'reason' => 'Stripe Tax Calculation',
                                'taxAmount' => $taxAmount,
                            ],
                        ],
                    ],
                    'currencyCode' => $currency,
                    'commit' => true, // Required so Avalara doesn't try to recompute anything
                ];

                // Call Avalara API
                $response = $this->client->createTransaction(null, $transaction);

                // Validate response
                if (is_object($response) && get_class($response) === 'stdClass') {
                    if (isset($response->status) && $response->status === 'Committed') {
                        // Success - return transaction data
                        return [
                            'code' => $response->code ?? null,
                            'status' => $response->status ?? 'Committed',
                            'totalTax' => $response->totalTax ?? $taxAmount,
                            'totalAmount' => $response->totalAmount ?? $subtotal + $taxAmount,
                        ];
                    }
                }

                // If we get here, response was not successful
                throw new Exception('Avalara transaction was not committed successfully');
            } catch (Exception $e) {
                $lastError = $e;
                $retryCount++;

                // Log error
                try {
                    \DB::table('avalara_error_logs')->insert([
                        'order_data' => json_encode([
                            'invoice_id' => $invoice['id'] ?? null,
                            'customer_code' => $customerCode,
                            'retry_attempt' => $retryCount,
                            'max_retries' => $maxRetries,
                        ]),
                        'error' => json_encode([
                            'message' => $e->getMessage(),
                            'retry_count' => $retryCount,
                        ]),
                        'ip' => request()->ip() ?? 'unknown',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (Exception $ex) {
                    // Silently fail logging if DB is unavailable
                }

                // If we've exhausted retries, return false
                if ($retryCount >= $maxRetries) {
                    logger()->error('Avalara Stripe invoice posting failed after retries', [
                        'invoice_id' => $invoice['id'] ?? null,
                        'customer_code' => $customerCode,
                        'error' => $e->getMessage(),
                        'retry_count' => $retryCount,
                    ]);

                    return false;
                }

                // Wait before retrying (exponential backoff: 1s, 2s, 4s)
                sleep(pow(2, $retryCount - 1));
            }
        }

        return false;
    }
}
