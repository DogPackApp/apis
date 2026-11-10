@include('marketplace.emails.header')

<!-- Main content section -->
<tr>
    <td
        style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('marketplace.emails.main-title', ['content' => $isUk ? "Your order on the DogPack marketplace has been confirmed" : "Thank you for your purchase!"])

        <h3 style="text-align: center; margin:10px 0;"><span
                style="font-size: 24px; font-weight: 500; line-height: 35px;">Order: {{ $orders->first()->order_no }}</span></h3>

        <p style="margin:20px 0;line-height:1.25;">
            Hi {{ $user["first_name"] ?? "Dog Owner" }},
        </p>

        @if($isReplacement ?? false)
        <p>Your previous order has been replaced with a new order. You have not been charged again, and no additional payment is required.</p>
        <p>Your replacement order details are provided below.</p>
        <p>If you have any questions, need assistance, or would like to make changes to your order, simply <strong>reply to this email</strong> and our DogPack Support team will be happy to help.</p>
        <p><strong>Here is your replacement order summary:</strong></p>
        <p><i>Please note: The prices shown below reflect the products' listed prices on our marketplace. These are for reference only, and you have not been charged again for this replacement order.</i></p>
        @elseif($isRenewal ?? false)
        <p>Your Subscribe &amp; Save order has been automatically placed as part of your active subscription.</p>
        <p>Your recurring discount has been applied, and your order is now being processed.</p>
        <p>If you'd like to make any changes to your subscription, you can manage it directly from the <strong>"My Orders"</strong> section of your DogPack account or contact <a href="mailto:support@dogpackapp.com" style="color:#108482;">support@dogpackapp.com</a> for assistance.</p>
        <p>Here's your order summary:</p>
        @else
        <p>Thank you for ordering from the
            DogPack marketplace! </p>
        <p></p>
        <p>If you need to make any changes to your order or have questions, please
            <strong>contact the seller directly</strong> from the DogPack marketplace.
        </p>
        <p></p>
        <p>Here is your {{ $isUk ? "order details (provided by the seller)" : "order summary" }}:</p>
        @endif
        @include('marketplace.emails.partials.product-listing', [
            'currency' => $currency ?? "$", 
            'order' => $orders, 
            'showQty' => true, 
            'showStore' => true, 
            'showReview' => false,
            'showShipping' => true,
            'showContactStore' => ! ($isRenewal ?? false),
            'deliveryPerOrder' => $deliveryPerOrder ?? []
        ])
        <!-- Total Summary -->
        <table align="right" cellpadding="0" cellspacing="0"
            style="margin-top: 20px; margin-bottom: 30px; font-size: 14px; width: 300px; border:1px solid #e9ecef;border-radius:8px;padding:10px;">
            <tr>
                <td style="padding: 8px;">Subtotal</td>
                <td align="right" style="padding: 8px;">{{$currency}}{{ $summary['subtotal'] + $summary['discount'] }}</td>
            </tr>
            @if($summary['discount'] > 0)
            <tr>
                <td style="padding: 8px;">Discount</td>
                <td align="right" style="padding: 8px;">- {{$currency}}{{ $summary['discount']}}</td>
            </tr>
            @endif
            @if(($summary['wallet_discount'] ?? 0) > 0)
            <tr>
                <td style="padding: 8px;">Rewards discount</td>
                <td align="right" style="padding: 8px;">- {{$currency}}{{ $summary['wallet_discount'] }}</td>
            </tr>
            @endif
            <tr>
                <td style="padding: 8px;">Delivery fees</td>
                <td align="right" style="padding: 8px;">{{$currency}}{{ $summary['delivery'] }}</td>
            </tr>
            @if(!$isUk)
                <tr>
                    <td style="padding: 8px;">Taxes</td>
                    <td align="right" style="padding: 8px;">{{$currency}}{{ $summary['tax'] }}</td>
                </tr>
            @endif
            <tr style="font-weight: bold;">
                <td colspan="2" style="padding: 0;">
                    <table width="100%" cellpadding="0" cellspacing="0" style="border-top: 1px solid #ddd;">
                        <tr style="font-weight: bold;">
                            <td style="padding: 8px;">Total</td>
                            <td align="right" style="padding: 8px;">{{$currency}}{{ $summary['total'] }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table cellpadding="0" cellspacing="0" border="0"
            style="width:100%;margin:20px 0;border:1px solid #e9ecef;border-radius:8px;padding:20px;">
            <tr>
                <td style="width:33.33%;padding-right:15px;vertical-align:top;">
                    <div>
                        <h4
                            style="margin:0 0 15px 0;font-family:Helvetica, Avenir, sans-serif;font-size:16px;font-weight:600;color:#333333;">
                            Shipping Information</h4>
                        <p style="line-height:1.5;color:#667085;">
                            {{ $orders->first()->customer->name ?? "Customer" }}<br>
                            {{ $orders->first()->customer->shipping_address['street1'] ?? "N/A" }}<br>
                            @if(!empty($orders->first()->customer->shipping_address['street2']))
                                {{ $orders->first()->customer->shipping_address['street2'] }}<br>
                            @endif
                            {{ $orders->first()->customer->shipping_address['city'] ?? "N/A" }},
                            {{ $orders->first()->customer->shipping_address['province'] ?? "N/A" }},
                            {{ $orders->first()->customer->shipping_address['zipcode'] ?? "N/A" }}<br>
                            {{ $orders->first()->customer->shipping_address['country'] ?? "N/A" }}
                        </p>
                    </div>
                </td>

                <td style="width:33.33%;padding:0 15px;vertical-align:top;">
                    <div>
                        <h4
                            style="margin:0 0 15px 0;font-family:Helvetica, Avenir, sans-serif;font-size:16px;font-weight:600;color:#333333;">
                            Billing Information</h4>
                        @if($orders->first()->customer->billing_address)
                            <p style="line-height:1.5;color:#667085;">
                                {{ $orders->first()->customer->name ?? "Customer" }}<br>
                                {{ $orders->first()->customer->billing_address['street1'] ?? "N/A" }}<br>
                                @if(!empty($orders->first()->customer->billing_address['street2']))
                                    {{ $orders->first()->customer->billing_address['street2'] }}<br>
                                @endif
                                {{ $orders->first()->customer->billing_address['city'] ?? "N/A" }},
                                {{ $orders->first()->customer->billing_address['province'] ?? "N/A" }},
                                {{ $orders->first()->customer->billing_address['zipcode'] ?? "N/A" }}<br>
                                {{ $orders->first()->customer->billing_address['country'] ?? "N/A" }}
                            </p>
                        @else
                            <p style="line-height:1.5;color:#667085;">
                                {{ $orders->first()->customer->name ?? "Customer" }}<br>
                                {{ $orders->first()->customer->shipping_address['street1'] ?? "N/A" }}<br>
                                @if(!empty($orders->first()->customer->shipping_address['street2']))
                                    {{ $orders->first()->customer->shipping_address['street2'] }}<br>
                                @endif
                                {{ $orders->first()->customer->shipping_address['city'] ?? "N/A" }},
                                {{ $orders->first()->customer->shipping_address['province'] ?? "N/A" }},
                                {{ $orders->first()->customer->shipping_address['zipcode'] ?? "N/A" }}<br>
                                {{ $orders->first()->customer->shipping_address['country'] ?? "N/A" }}
                            </p>
                        @endif
                    </div>
                </td>

                <td style="width:33.33%;padding-left:15px;vertical-align:top;">
                    <div>
                        <h4
                            style="margin:0 0 15px 0;font-family:Helvetica, Avenir, sans-serif;font-size:16px;font-weight:600;color:#333333;">
                            Payment Method</h4>
                        <p style="line-height:1.5;color:#667085;">
                            @php
                                $paymentDetail = $orders->first()->paymentDetail;
                                $card = null;
                                if ($paymentDetail && $paymentDetail->card) {
                                    $card = is_string($paymentDetail->card) ? json_decode($paymentDetail->card, true) : $paymentDetail->card;
                                }
                            @endphp
                            @if(!empty($card['brand']) && !empty($card['last4']))
                                {{ ucfirst($card['brand']) }} ending in {{ $card['last4'] }}
                            @else
                                Card
                            @endif
                        </p>
                    </div>
                </td>
            </tr>
        </table>

        @if($isUk)
            <p style="font-size: 14px; color: #667085; line-height: 1.5; margin-bottom: 10px;">
                The seller listed above is an independent seller on the DogPack marketplace and is responsible for fulfilling your order, issuing any applicable invoice or receipt, and handling VAT or other taxes where applicable.
            </p>
            <p style="font-size: 14px; color: #667085; line-height: 1.5; margin-bottom: 20px;">
                Payment was processed securely by DogPack on behalf of the seller.
            </p>
        @endif

        <p>If you are a DogPack user you can view the status of all items anytime from your
            DogPack account.</p>
        <p>Not a user? <a href="{{ config('services.website_url') }}/signup" target="_blank" style="color:#108482;">Sign-up</a> now to
            DogPack to track your orders.</p>
        <p>Thank you for supporting local pet-friendly businesses through DogPack!</p>

        @include('marketplace.emails.partials.support-phone')

    </td>
</tr>

{{--@include('marketplace.emails.footer')--}}
