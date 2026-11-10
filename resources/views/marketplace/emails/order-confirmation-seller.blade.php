@include('marketplace.emails.header')

<!-- Main content section -->
<tr>
    <td
        style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('marketplace.emails.main-title', ['content' => "New Order Received – Order #{$order->order_no}"])

        <p style="margin:20px 0;line-height:1.25;">
            Hello {{ $user->first_name }},
        </p>

        <p>You have received a new order through the DogPack Marketplace.</p>

        <table cellpadding="0" cellspacing="0" role="presentation" style="margin: 30px 0; width: 100%;">
            <tr>
                <td style="padding-right: 10px; width: 50%; text-align: center;">
                    <a href="{{ config('services.marketplace_fe_url') . 'orders/' . $order->uuid }}"
                        style="text-decoration: none;" target="_blank">
                        <button
                            style="padding:12px 30px; background:#108482; border:none; color:#ffffff; border-radius:8px; font-size:16px; font-weight:500; cursor:pointer; font-family:Helvetica, Avenir, sans-serif; display:inline-block;">
                            View Order
                        </button>
                    </a>
                </td>
                <td style="padding-left: 10px; width: 50%; text-align: center;">
                    <a href="{{ config('services.marketplace_fe_url') . 'orders/' . $order->uuid }}"
                        style="text-decoration: none;" target="_blank">
                        <button
                            style="padding:12px 30px; background:#cfe6e6; border:none; color:#1d2939; border-radius:8px; font-size:16px; font-weight:500; cursor:pointer; font-family:Helvetica, Avenir, sans-serif; display:inline-block;">
                            Create shipping label
                        </button>
                    </a>
                </td>
            </tr>
        </table>



        <table cellpadding="0" cellspacing="0" width="100%"
               style="font-family: Arial, sans-serif; font-size: 14px; border-collapse: collapse;">
            <thead>
            <tr style="border-bottom: 2px solid #ddd;">

                <th align="left" style="padding: 10px;">Store</th>


                <th align="left" style="padding: 10px;">Item</th>


                <th align="center" style="padding: 10px;">Qty</th>


                <th align="right" style="padding: 10px;">Price</th>

            </tr>
            </thead>
            <tbody>
{{--            @foreach ($order as $ord)--}}
                @foreach ($order->items as $itemIndex => $item)
                    @php
                        $itemValue = $item->price * $item->quantity / 100;
                        $itemDiscount = $item->discount / 100;
                        $itemDiscountPercentage = round(($item->discount/($item->original_price * $item->quantity))*100, 2);
                    @endphp
                    <tr @if($loop->last) style="border-bottom: 2px solid #ddd;" @endif>
                        @if ($itemIndex === 0)
                            <td style="padding: 10px; vertical-align: top;" rowspan="{{ count($order->items) }}">
                                <a href="{{ config('services.website_url') }}/marketplace/store/{{ $order->store->slug }}"
                                   style="color: #1d2939; text-decoration: underline;" target="_blank">
                                    {{ $order->store->name }}
                                </a>
                                <br><br>
                            </td>
                        @endif


                            <td style="padding: 10px; vertical-align: top;">
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                                    <tr>
                                        <td width="60" valign="top">
                                            <img src="{{ $item->product_snapshot['image'] ?? '' }}" width="60" height="60"
                                                 alt="Product Image" style="border-radius: 6px; display: block;" onerror="this.onerror=null; this.src='{{ asset('public/website/img/marketplace/default/empty_product.webp') }}';"/>
                                        </td>

                                        <td valign="top" style="padding-left: 10px;padding-top: 5px;">
                                            <p style="margin: 0; font-weight: bold; font-size: 14px; color: #000;">
                                                {{ $item->product_snapshot['name'] ?? "Dog Product" }}
                                            </p>
                                            @if($item->item_options)
                                                <p style="margin: 4px 0 0 0; font-size: 13px; color: #555;">
                                                    @foreach($item->__attributeOptions($item->item_options) as $options)
                                                        @if($options->attribute_value != \App\Models\Marketplace\ItemVariant::SHOPIFY_DEFAULT_VARIANT)
                                                            <span style="color: #666;">{{ $options->attribute->name }}: {{ $options->attribute_value }} 
                                                                @if(!$loop->last)
                                                                    &nbsp;|&nbsp;
                                                                @endif
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                </p>
                                            @endif
                                            @if($item->product_snapshot['customization'])
                                                <p style="margin: 4px 0 0 0; font-size: 13px; color: #555;">
                                                Text Customization: "{{ $item->product_snapshot['customization'] }}"
                                                </p>
                                            @endif
                                            @if(!empty($item->product_snapshot['customization_media']))
                                                <p  style="margin: 4px 0 0 0; font-size: 13px; color: #555;">
                                                    Media Customization:
                                                    @foreach($item->product_snapshot['customization_media'] as $media)
                                                        <img src="{{ $media }}" alt="Customization Media" style="width: 25px; height: 25px; object-fit: contain; border: 1px solid #ccc; border-radius: 5px;">
                                                    @endforeach
                                                </p>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </td>

                            <td align="center" style="padding: 10px; vertical-align: top;">
                                {{ $item->quantity }}
                            </td>

                            <td align="right" style="padding: 10px; vertical-align: top;">
                            <div style="font-size:16px; font-weight:500;">
                                {{$currency}}{{ round($itemValue, 2) ?? "NA"}}
                                @if($itemDiscount > 0)
                                    <div>
                                        <del style="color:#888;">
                                            {{$currency}}{{ round($itemValue + $itemDiscount, 2) ?? "NA"}}
                                        </del>
                                        <span style="background-color:#198754; color:#fff; border-radius:4px; padding:2px 8px; font-size:13px; margin-left:8px; display:inline-block;">
                                            {{ $itemDiscountPercentage }}% off
                                        </span>
                                    </div>
                                @endif
                                @if($item->quantity > 1)
                                    <div style="font-weight:400; color:#888; font-size:16px;">
                                        ({{ $currency}}{{ round($item->price/100, 2) ?? "NA"}} each)
                                    </div>
                                @endif
                            </div>
                            </td>



                    </tr>
                @endforeach
{{--            @endforeach--}}
            </tbody>
        </table>

        <!-- Total Summary -->
        <table align="right" cellpadding="0" cellspacing="0"
               style="margin-top: 20px; margin-bottom: 30px; font-size: 14px; width: 300px; border:1px solid #e9ecef;border-radius:8px;padding:10px;">
            <tr>
                <td style="padding: 8px;">Subtotal</td>
                <td align="right" style="padding: 8px;">{{$currency}}{{ ($summary['subtotal'] + $summary['discount']) / 100 }}</td>
            </tr>
            @if(($summary['display_discount'] ?? $summary['discount']) > 0)
            <tr>
                <td style="padding: 8px;">Discount</td>
                <td align="right" style="padding: 8px;">- {{$currency}}{{ ($summary['display_discount'] ?? $summary['discount']) / 100}}</td>
            </tr>
            @endif
            @if(($summary['wallet_discount'] ?? 0) > 0)
            <tr>
                <td style="padding: 8px;">DogPack contribution</td>
                <td align="right" style="padding: 8px;">{{$currency}}{{ $summary['wallet_discount'] / 100 }}</td>
            </tr>
            @endif
            @if($summary['dogpack_contribution'] > 0)
                <tr>
                    <td style="padding: 8px;">Dogpack Contribution</td>
                    <td align="right" style="padding: 8px;">{{$currency}}{{ $summary['dogpack_contribution'] / 100}}</td>
                </tr>
            @endif
            <tr>
                <td style="padding: 8px;">Delivery fees</td>
                <td align="right" style="padding: 8px;">{{$currency}}{{ $summary['delivery'] }}</td>
            </tr>
            @if(!$isUk)
                <tr>
                    <td style="padding: 8px;">Taxes</td>
                    <td align="right" style="padding: 8px;">{{$currency}}{{ $summary['tax'] / 100 }}</td>
                </tr>
            @endif
            <tr style="font-weight: bold;">
                <td colspan="2" style="padding: 0;">
                    <table width="100%" cellpadding="0" cellspacing="0" style="border-top: 1px solid #ddd;">
                        <tr style="font-weight: bold;">
                            <td style="padding: 8px;">Total</td>
                            <td align="right" style="padding: 8px;">{{$currency}}{{ $summary['total'] / 100}}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table cellpadding="0" cellspacing="0" border="0"
            style="width:100%;margin:20px 0;border:1px solid #e9ecef;border-radius:8px;padding:20px;">
            <tr>
                <td style="width:50%;padding-right:15px;vertical-align:top;">
                    <div>
                        <h4
                            style="margin:0 0 15px 0;font-family:Helvetica, Avenir, sans-serif;font-size:16px;font-weight:600;color:#333333;">
                            Shipping Information</h4>
                        <p style="line-height:1.5;color:#667085;">
                            {{ $order?->customer->name ?? "Customer" }}<br>
                            {{ $order?->customer->shipping_address['street1'] ?? "N/A" }}<br>
                            @if(!empty($order?->customer->shipping_address['street2']))
                                {{ $order?->customer->shipping_address['street2'] }}<br>
                            @endif
                            {{ $order?->customer->shipping_address['city'] ?? "N/A" }},
                            {{ $order?->customer->shipping_address['province'] ?? "N/A" }},
                            {{ $order?->customer->shipping_address['zipcode'] ?? "N/A" }}<br>
                            {{ $order?->customer->shipping_address['country'] ?? "N/A" }}
                        </p>
                    </div>
                </td>

                <td style="width:50%;padding-left:15px;vertical-align:top;">
                    <div>
                        <h4
                            style="margin:0 0 15px 0;font-family:Helvetica, Avenir, sans-serif;font-size:16px;font-weight:600;color:#333333;">
                            Billing Information</h4>
                        @if($order?->customer->billing_address)
                            <p style="line-height:1.5;color:#667085;">
                                {{ $order?->customer->name ?? "Customer" }}<br>
                                {{ $order?->customer->billing_address['street1'] ?? "N/A" }}<br>
                                @if(!empty($order?->customer->billing_address['street2']))
                                    {{ $order?->customer->billing_address['street2'] }}<br>
                                @endif
                                {{ $order?->customer->billing_address['city'] ?? "N/A" }},
                                {{ $order?->customer->billing_address['province'] ?? "N/A" }},
                                {{ $order?->customer->billing_address['zipcode'] ?? "N/A" }}<br>
                                {{ $order?->customer->billing_address['country'] ?? "N/A" }}
                            </p>
                        @else
                            <p style="line-height:1.5;color:#667085;">
                                {{ $order?->customer->name ?? "Customer" }}<br>
                                {{ $order?->customer->shipping_address['street1'] ?? "N/A" }}<br>
                                @if(!empty($order?->customer->shipping_address['street2']))
                                    {{ $order?->customer->shipping_address['street2'] }}<br>
                                @endif
                                {{ $order?->customer->shipping_address['city'] ?? "N/A" }},
                                {{ $order?->customer->shipping_address['province'] ?? "N/A" }},
                                {{ $order?->customer->shipping_address['zipcode'] ?? "N/A" }}<br>
                                {{ $order?->customer->shipping_address['country'] ?? "N/A" }}
                            </p>
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        <table cellpadding="0" cellspacing="0" border="0"
            style="width:100%;margin:20px 0;border:1px solid #e9ecef;border-radius:8px;padding:20px;">
            <tr>
                <td style="width:50%;padding-right:15px;vertical-align:top;">
                    <div>
                        <h4
                            style="margin:0 0 15px 0;font-family:Helvetica, Avenir, sans-serif;font-size:16px;font-weight:600;color:#333333;">
                            Payment Method</h4>
                        <p style="line-height:1.5;color:#667085;">
                            @php
                                $paymentDetail = $order->paymentDetail;
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

                <td style="width:50%;padding-left:15px;vertical-align:top;">
                    <div>
                        <h4
                            style="margin:0 0 15px 0;font-family:Helvetica, Avenir, sans-serif;font-size:16px;font-weight:600;color:#333333;">
                            Shipping Method</h4>
                        <p style="line-height:1.5;color:#667085;">
                            {{ $delivery['name'] }} ({{$delivery['description']}}) ${{$delivery['rate']}}
                        </p>
                    </div>
                </td>
            </tr>
        </table>

    </td>
</tr>

@include('marketplace.emails.footer')
