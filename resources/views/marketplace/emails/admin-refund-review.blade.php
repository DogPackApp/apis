@include('marketplace.emails.header')

<!-- Main content section -->
<tr>
    <td
        style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('marketplace.emails.main-title', ['content' => $isCancellation
            ? "Cancellation pending review – Order #{$order->order_no}"
            : "Refund pending review – Order #{$order->order_no}"])

        <p style="margin:20px 0;line-height:1.25;">
            Hi,
        </p>

        <p>
            {{ $order->store->name }}
            {{ $isCancellation ? 'cancelled' : 'partially refunded' }}
            order #{{ $order->order_no }}.
            The customer has not been refunded yet. Please review and process this refund.
        </p>

        <p>{{ $isCancellation ? 'Cancelled items:' : 'Refunded items:' }}</p>

        @include('marketplace.emails.partials.product-listing', [
            'currency' => $currency ?? "$",
            'order' => [$order],
            'showCancelledQty' => true,
            'showQty' => true,
            'showStore' => true,
            'showReview' => false
        ])

        <p>Refund details:</p>

        <table cellpadding="0" cellspacing="0" border="0"
            style="width:100%;max-width:350px;margin:0px auto;background-color:#0D6A68;border:1px solid #e9ecef;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1); color: #fff;">

            <tr>
                <td style="padding:15px 25px;">

                    <div style="margin-bottom:12px;">
                        <p style="margin:0;font-size:16px;line-height:1.4;">
                            <span style="font-size: 16px; color: #fff; font-weight: bold;">Store Name: </span>
                            <span style="float: right;">{{ $order->store->name }}</span>
                        </p>
                    </div>
                    <div style="margin-bottom:12px;">
                        <p style="margin:0;font-size:16px;line-height:1.4;">
                            <span style="font-size: 16px; color: #fff; font-weight: bold;">Order Number: </span>
                            <span style="float: right;">#{{ $order->order_no }}</span>
                        </p>
                    </div>
                    @if(!empty($customerName) || !empty($customerEmail))
                        <div style="margin-bottom:12px;">
                            <p style="margin:0;font-size:16px;line-height:1.4;">
                                <span style="font-size: 16px; color: #fff; font-weight: bold;">Customer: </span>
                                <span style="float: right;">{{ trim(($customerName ?? '').' '.($customerEmail ?? '')) }}</span>
                            </p>
                        </div>
                    @endif
                    @if(!empty($reason))
                        <div style="margin-bottom:12px;">
                            <table cellpadding="0" cellspacing="0" border="0" style="width:100%;font-size:16px;line-height:1.4;color:#fff;">
                                <tr>
                                    <td style="vertical-align:top;white-space:nowrap;font-weight:bold;padding-right:12px;">Reason:</td>
                                    <td style="vertical-align:top;text-align:right;word-wrap:break-word;word-break:break-word;">{{ $reason }}</td>
                                </tr>
                            </table>
                        </div>
                    @endif
                    @if(isset($itemsAmount) && floatval($itemsAmount) > 0)
                        <div style="margin-bottom:12px;">
                            <p style="margin:0;font-size:16px;line-height:1.4;">
                                <span style="font-size: 16px; color: #fff; font-weight: bold;">Items refunded: </span>
                                <span style="float: right;">{{ $currency ?? '$' }} {{ $itemsAmount }}</span>
                            </p>
                        </div>
                    @endif
                    @if(isset($deliveryAmount) && floatval($deliveryAmount) > 0)
                        <div style="margin-bottom:12px;">
                            <p style="margin:0;font-size:16px;line-height:1.4;">
                                <span style="font-size: 16px; color: #fff; font-weight: bold;">Delivery fees refunded: </span>
                                <span style="float: right;">{{ $currency ?? '$' }} {{ $deliveryAmount }}</span>
                            </p>
                        </div>
                    @endif
                    @if(!$isUk && isset($taxAmount) && floatval($taxAmount) > 0)
                        <div style="margin-bottom:12px;">
                            <p style="margin:0;font-size:16px;line-height:1.4;">
                                <span style="font-size: 16px; color: #fff; font-weight: bold;">Tax refunded: </span>
                                <span style="float: right;">{{ $currency ?? '$' }} {{ $taxAmount }}</span>
                            </p>
                        </div>
                    @endif
                    @if(isset($amount) && floatval($amount) > 0)
                        <div>
                            <p style="margin:0;font-size:16px;line-height:1.4;">
                                <span style="font-size: 16px; color: #fff; font-weight: bold;">Total refund amount: </span>
                                <span style="float: right;">{{ $currency ?? '$' }} {{ $amount }}</span>
                            </p>
                        </div>
                    @endif

                </td>
            </tr>
        </table>

        <p style="margin:30px 0 10px 0;line-height:1.5;">DogPack Team</p>
    </td>
</tr>

@include('marketplace.emails.footer')
