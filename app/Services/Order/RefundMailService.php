<?php

namespace App\Services\Order;

use App\Mail\Order\AdminRefundReviewMail;
use App\Mail\Order\Buyer\FullRefundMail;
use App\Mail\Order\Buyer\PartialRefundMail;
use App\Models\Order\StoreOrder;
use Illuminate\Support\Facades\Mail;

class RefundMailService
{
    /**
     * Send refund email based on refund type and order status
     */
    public function sendRefundEmail(
        StoreOrder $order,
        array $refundedItems = [],
        bool $wasPartiallyRefunded = false,
        bool $isFullCancellation = false,
        ?string $reason = null,
        bool $forcePartial = false,
        ?string $chargedAmount = null,
    ): string {
        $order->loadMissing(['orderBilling', 'refunds', 'items', 'store', 'customer']);
        $emailReason = $this->emailReason($reason);

        if ($isFullCancellation) {
            return $this->sendCancellationEmail($order, $wasPartiallyRefunded, $emailReason, $chargedAmount);
        }

        return $this->sendPartialRefundEmail($order, $refundedItems, $emailReason, $forcePartial, $chargedAmount);
    }

    /**
     * Tell the admin a refund or cancellation is waiting for review.
     * The customer email is sent later, when the refund is processed.
     */
    public function notifyAdminForReview(
        StoreOrder $order,
        array $refundedItems = [],
        bool $wasPartiallyRefunded = false,
        bool $isFullCancellation = false,
        ?string $reason = null,
    ): void {
        $adminEmail = 'supportdog@dogpackapp.com';
        $order->unsetRelation('refunds');
        $order->unsetRelation('store');
        $order->loadMissing(['orderBilling', 'refunds', 'items', 'store', 'customer']);
        $this->applyRefundedQuantities($order, $refundedItems);
        $emailReason = $this->emailReason($reason);

        if ($isFullCancellation) {
            $emailData = $this->calculateCancellationAmounts($order, $wasPartiallyRefunded);
            $orderForEmail = $this->prepareOrderForCancellationEmail($order, $wasPartiallyRefunded);
            $amount = $emailData['totalRefundAmount'];
            $isCancellation = true;
        } elseif ($order->items->sum('quantity') === $order->items->sum('refunded')) {
            $emailData = $this->calculateFullRefundFromPartialAmounts($order);
            $orderForEmail = $this->prepareOrderForPartialRefundEmail($order, $refundedItems);
            $amount = $emailData['totalAmount'];
            $isCancellation = true;
        } else {
            $emailData = $this->calculatePartialRefundAmounts($this->linesForPartialEmail($order));
            $orderForEmail = $this->prepareOrderForPartialRefundEmail($order, $refundedItems);
            $amount = $emailData['totalAmount'];
            $isCancellation = false;
        }

        $mailable = new AdminRefundReviewMail(
            $order->customer,
            $orderForEmail,
            $amount,
            $emailData['itemsAmount'],
            $emailData['taxAmount'],
            $emailData['deliveryAmount'],
            $emailReason,
            $isCancellation,
        );

        Mail::to($adminEmail)->queue($mailable);
    }

    /**
     * Currency symbol the customer paid in: order billing currency first,
     * then the customer's shipping country, then "$".
     */
    private function customerCurrencySymbol(StoreOrder $order): string
    {
        $countryService = app(CountryService::class);
        $code = $order->getBuyerCurrency();

        if ($code !== '') {
            $symbol = $countryService->getByCurrencyCode($code)['currency_symbol'] ?? null;

            if (! empty($symbol)) {
                return $symbol;
            }
        }

        $country = $order->customer?->shipping_address['country'] ?? null;

        if (is_string($country) && $country !== '') {
            $symbol = $countryService->getByShortCode(strtoupper($country))['currency_symbol'] ?? null;

            if (! empty($symbol)) {
                return $symbol;
            }
        }

        return '$';
    }

    private function emailReason(?string $reason): ?string
    {
        if ($reason === null || $reason === '' || $reason === 'Other') {
            return null;
        }

        return $reason;
    }

    /**
     * Send cancellation/full refund email
     */
    private function sendCancellationEmail(
        StoreOrder $order,
        bool $wasPartiallyRefunded,
        ?string $reason = null,
        ?string $chargedAmount = null,
    ): string {
        $emailData = $chargedAmount !== null
            ? $this->chargedAmountData($chargedAmount)
            : $this->calculateCancellationAmounts($order, $wasPartiallyRefunded);
        $orderForEmail = $this->prepareOrderForCancellationEmail($order, $wasPartiallyRefunded);

        // This refund covers only part of the order's money when item refunds exist beside it,
        // so it must not claim a full refund. The amount shown is what this row returned.
        $mailableClass = $chargedAmount !== null && $wasPartiallyRefunded
            ? PartialRefundMail::class
            : FullRefundMail::class;

        // Without a reliable items/delivery/tax split for the row, show only its total.
        if ($mailableClass === PartialRefundMail::class) {
            $emailData['itemsAmount'] = '0.00';
        }

        $mailable = new $mailableClass(
            $order->customer,
            $orderForEmail,
            $emailData['totalRefundAmount'],
            $emailData['itemsAmount'],
            $emailData['taxAmount'],
            $emailData['deliveryAmount'],
            $reason,
            $this->customerCurrencySymbol($order)
        );
        $mailable->hideCatalogPrices = $chargedAmount !== null;

        Mail::to($order->customer->email ?? config('mail.address.from'))->send($mailable);

        // Return the rendered email HTML
        return $mailable->render();
    }

    /**
     * Send partial refund email
     */
    private function sendPartialRefundEmail(
        StoreOrder $order,
        array $refundedItems,
        ?string $reason = null,
        bool $forcePartial = false,
        ?string $chargedAmount = null,
    ): string {
        $refundableCollection = $this->linesForPartialEmail($order);
        $emailData = $chargedAmount !== null
            ? $this->chargedAmountData($chargedAmount)
            : $this->calculatePartialRefundAmounts($refundableCollection);
        $orderForEmail = $this->prepareOrderForPartialRefundEmail($order, $refundedItems);

        // A single processed item row stays a partial email even when the order is fully refunded.
        if ($chargedAmount === null && ! $forcePartial && $order->items->sum('quantity') === $order->items->sum('refunded')) {
            // Recalculate amounts for full refund scenario
            $emailData = $this->calculateFullRefundFromPartialAmounts($order);

            $mailable = new FullRefundMail(
                $order->customer,
                $orderForEmail,
                $emailData['totalAmount'],
                $emailData['itemsAmount'],
                $emailData['taxAmount'],
                $emailData['deliveryAmount'],
                $reason,
                $this->customerCurrencySymbol($order)
            );
        } else {
            $mailable = new PartialRefundMail(
                $order->customer,
                $orderForEmail,
                $emailData['totalAmount'],
                $emailData['itemsAmount'],
                $emailData['taxAmount'],
                $emailData['deliveryAmount'],
                $reason,
                $this->customerCurrencySymbol($order)
            );
        }

        $mailable->hideCatalogPrices = $chargedAmount !== null;

        Mail::to($order->customer->email ?? config('mail.address.from'))->send($mailable);

        // Return the rendered email HTML
        return $mailable->render();
    }

    /**
     * Calculate amounts for cancellation email
     */
    private function calculateCancellationAmounts(StoreOrder $order, bool $wasPartiallyRefunded): array
    {
        $subtotal = round(($order->orderBilling->currency_details['subtotal'] ?? 0), 2);
        $tax = round(($order->orderBilling->currency_details['tax'] ?? 0), 2);
        $delivery = round(($order->orderBilling->currency_details['delivery'] ?? 0), 2);

        if ($wasPartiallyRefunded) {
            $alreadyItems = round(($order->refunds->whereNotNull('order_item_id')->sum('converted')) / 100, 2);
            $alreadyTax = round(($order->refunds->whereNotNull('order_item_id')->sum('tax_converted')) / 100, 2);

            $itemsAmount = max(round($subtotal - $alreadyItems, 2), 0);
            $taxAmount = max(round($tax - $alreadyTax, 2), 0);
            $total = ($itemsAmount + $taxAmount + $delivery);
            $totalRefundAmount = number_format($total, 2);
        } else {
            $itemsAmount = $subtotal;
            $taxAmount = $tax;
            // Note: In cancel controller, total comes from Stripe API call, but we'll use calculated total here
            $totalRefundAmount = number_format(($subtotal + $tax + $delivery), 2);
        }

        return [
            'totalRefundAmount' => $totalRefundAmount,
            'itemsAmount' => $itemsAmount,
            'taxAmount' => $taxAmount,
            'deliveryAmount' => $delivery,
        ];
    }

    /**
     * @return array<int, array{converted: int, convertedTax: int}>
     */
    private function linesForPartialEmail(StoreOrder $order): array
    {
        $refundableCollection = [];

        foreach ($order->items as $item) {
            if (isset($item->refundedForEmail) && $item->refundedForEmail > 0) {
                $convertedTax = isset($item->tax['converted']) ? (($item->tax['converted'] / 100) / $item->quantity) * $item->refundedForEmail : 0;
                $convertedTotal = (round($item->currency_details['converted']['discountedPrice'], 2) * $item->refundedForEmail);

                $refundableCollection[] = [
                    'converted' => (int) round($convertedTotal * 100),
                    'convertedTax' => (int) round($convertedTax * 100),
                ];
            }
        }

        return $refundableCollection;
    }

    /**
     * @param  array<int, array{uuid?: string, qty?: int}>  $refundedItems
     */
    private function applyRefundedQuantities(StoreOrder $order, array $refundedItems): void
    {
        $qtyByUuid = collect($refundedItems)->keyBy('uuid');

        foreach ($order->items as $item) {
            $qty = (int) ($qtyByUuid[$item->uuid]['qty'] ?? 0);

            if ($qty > 0) {
                $item->refundedForEmail = $qty;
            }
        }
    }

    /**
     * @return array{totalRefundAmount: string, totalAmount: string, itemsAmount: string, taxAmount: string, deliveryAmount: string}
     */
    private function chargedAmountData(string $chargedAmount): array
    {
        return [
            'totalRefundAmount' => $chargedAmount,
            'totalAmount' => $chargedAmount,
            'itemsAmount' => $chargedAmount,
            'taxAmount' => '0.00',
            'deliveryAmount' => '0.00',
        ];
    }

    /**
     * Calculate amounts for partial refund email
     */
    private function calculatePartialRefundAmounts(array $refundableItems): array
    {
        $refundableCollection = collect($refundableItems);
        $totalAmount = $refundableCollection->sum(fn ($item) => $item['converted'] + $item['convertedTax']);
        $amount = number_format($totalAmount / 100, 2);
        $itemsAmount = number_format($refundableCollection->sum(fn ($item) => $item['converted']) / 100, 2);
        $taxAmount = number_format($refundableCollection->sum(fn ($item) => $item['convertedTax']) / 100, 2);
        $deliveryAmount = 0;

        return [
            'totalAmount' => $amount,
            'itemsAmount' => $itemsAmount,
            'taxAmount' => $taxAmount,
            'deliveryAmount' => $deliveryAmount,
        ];
    }

    /**
     * Calculate amounts when partial refund becomes full refund
     */
    private function calculateFullRefundFromPartialAmounts(StoreOrder $order): array
    {
        $alreadyItems = round(($order->refunds->whereNotNull('order_item_id')->sum('converted')) / 100, 2);
        $subtotal = round(($order->orderBilling->currency_details['subtotal'] ?? 0), 2);
        $itemsAmount = max(round($subtotal - $alreadyItems, 2), 0);

        $alreadyTax = round(($order->refunds->whereNotNull('order_item_id')->sum('tax_converted')) / 100, 2);
        $tax = round(($order->orderBilling->currency_details['tax'] ?? 0), 2);
        $taxAmount = max(round($tax - $alreadyTax, 2), 0);

        $deliveryAmount = round(($order->orderBilling->currency_details['delivery'] ?? 0), 2);
        $totalAmount = $itemsAmount + $deliveryAmount + $taxAmount;

        return [
            'totalAmount' => $totalAmount,
            'itemsAmount' => $itemsAmount,
            'taxAmount' => $taxAmount,
            'deliveryAmount' => $deliveryAmount,
        ];
    }

    /**
     * Prepare order instance for cancellation email
     */
    private function prepareOrderForCancellationEmail(StoreOrder $order, bool $wasPartiallyRefunded): StoreOrder
    {
        $itemsForEmail = $order->items->map(function ($item) use ($wasPartiallyRefunded) {
            $cancelledQty = $wasPartiallyRefunded
                ? max(($item->quantity - ($item->refunded ?? 0)), 0)
                : $item->quantity;
            $item->refundedForEmail = $cancelledQty;

            return $item;
        })->filter(function ($item) {
            return $item->refundedForEmail > 0;
        });

        $orderForEmail = $order->replicate();
        $orderForEmail->setRelation('items', $itemsForEmail);
        $orderForEmail->setRelation('store', $order->getRelation('store'));

        return $orderForEmail;
    }

    /**
     * Prepare order instance for partial refund email
     */
    private function prepareOrderForPartialRefundEmail(StoreOrder $order, array $refundedItems): StoreOrder
    {
        // Extract UUIDs from refunded items (different structure depending on source)
        $refundedItemUuids = [];

        if (isset($refundedItems[0]['uuid'])) {
            // From validator in partial refund controller
            $refundedItemUuids = collect($refundedItems)->pluck('uuid')->all();
        } else {
            // From order items collection - get UUIDs of items that have refundedForEmail set
            $refundedItemUuids = $order->items
                ->filter(fn ($item) => isset($item->refundedForEmail))
                ->pluck('uuid')
                ->all();
        }

        $filteredItems = $order->items->filter(fn ($item) => in_array($item->uuid, $refundedItemUuids))->values();
        $orderForEmail = $order->replicate();
        $orderForEmail->setRelation('items', $filteredItems);

        // Ensure store relation is available for the email template
        if ($order->relationLoaded('store')) {
            $orderForEmail->setRelation('store', $order->getRelation('store'));
        }

        return $orderForEmail;
    }
}
