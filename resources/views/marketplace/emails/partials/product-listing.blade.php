@php use Illuminate\Support\Facades\Crypt; @endphp
@props([
    'order',
    'showStore' => true,
    'showItem' => true,
    'showQty' => true,
    'showCancelledQty' => false,
    'showPrice' => true,
    'showReview' => false,
    'showShipping' => false,
    'showContactStore' => true,
    'deliveryPerOrder' => [],
    'user' => null
    ])

<table cellpadding="0" cellspacing="0" width="100%"
       style="font-family: Arial, sans-serif; font-size: 14px; border-collapse: collapse;">
    <thead>
    <tr style="border-bottom: 2px solid #ddd;">
        @if($showStore)
            <th align="left" style="padding: 10px;">Store</th>
        @endif
        @if($showItem)
            <th align="left" style="padding: 10px;">Item</th>
        @endif
        @if($showQty)
            <th align="center" style="padding: 10px;">Qty</th>
        @endif
        @if($showCancelledQty)
            <th align="center" style="padding: 10px;">Canceled Qty</th>
        @endif
        @if($showPrice)
            <th align="right" style="padding: 10px;">Price</th>
        @endif
        @if($showShipping)
            <th align="left" style="padding: 10px;">Shipping</th>
        @endif
    </tr>
    </thead>
    <tbody>
    @foreach ($order as $ord)
        @foreach ($ord->items as $itemIndex => $item)
            @php
                $itemValue = $item->currency_details['converted']['discountedPrice'] * $item->quantity;
                $itemDiscount = $item->currency_details['converted']['applieddiscount'] * $item->quantity;
                $itemDiscountPercentage = $item->currency_details['converted']['discount'];
            @endphp
            <tr @if($loop->last) style="border-bottom: 2px solid #ddd;" @endif>
                @if ($showStore && $itemIndex === 0)
                    <td width="140" style="padding: 10px; vertical-align: top; width: 140px;" rowspan="{{ count($ord->items) }}">
                        <a href="{{ config('services.website_url') }}/marketplace/store/{{ $ord->store->slug }}"
                           style="color: #1d2939; text-decoration: underline;" target="_blank">
                            {{ $ord->store->name }}
                        </a>
                        @if($showContactStore)
                        <br><br>
                        <a href="{{ config('services.website_url') }}/marketplace/store/{{ $ord->store->slug }}?chat" style="text-decoration: none;" target="_blank">
                            <span style="display: inline-block; background-color: #108482; color: #fff; padding: 8px 14px; border-radius: 12px; font-size: 14px; white-space: nowrap; mso-line-height-rule: exactly;">Contact&nbsp;store</span>
                        </a>
                        @endif
                    </td>
                @endif

                @if($showItem)
                    <td style="padding: 10px; vertical-align: top;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                            <tr>
                                <td width="60" valign="top">
                                    <img src="{{ $item->product_snapshot['image'] ?? '' }}" width="60" height="60"
                                         alt="Product Image" style="border-radius: 6px; display: block;" onerror="this.onerror=null; this.src='{{ asset('public/website/img/marketplace/default/empty_product.webp') }}';" />
                                </td>

                                <td valign="top" style="padding-left: 10px;padding-top: 5px;">
                                    <p style="margin: 0; font-weight: bold; font-size: 14px; color: #000;">
                                        {{ $item->product_snapshot['name'] ?? "Dog Product" }}
                                    </p>
                                    @if($item->item_options)
                                        <p style="margin: 4px 0 0 0; font-size: 13px; color: #555;">
                                            @foreach($item->__attributeOptions($item->item_options) as $options)
                                                @if($options->attribute_value != \App\Models\Product\ItemVariant::SHOPIFY_DEFAULT_VARIANT)
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
                @endif

                @if($showQty)
                    <td align="center" style="padding: 10px; vertical-align: top;">
                        {{ $item->quantity }}
                    </td>
                @endif

                @if($showCancelledQty)
                    <td align="center" style="padding: 10px; vertical-align: top;">
                        {{ $item->refundedForEmail }}
                    </td>
                @endif

                @if($showPrice)
                    <td align="right" style="padding: 10px; vertical-align: top;">
                    <div class="item-price">
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
                                                    ({{ $currency}}{{ round($item->currency_details['converted']['discountedPrice'], 2) ?? "NA"}} each)
                                                </div>
                                            @endif
                                        </div>
                    </td>
                @endif

                @if($showReview)
                    <td align="center" style="padding: 10px; vertical-align: center;">
                        <div style="margin:30px 0; text-align: center;">
                            @if($user && $ord)
                                <a href="{{ config('services.website_url') }}/marketplace/orders/add-review/{{ $item->uuid }}/{{ $ord->uuid }}/{{ $user->email }}"
                                   style="text-decoration:none;" target="_blank">
                                   <button
                                   style="padding:12px 30px;background:#108482;border:none;color:#ffffff;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;font-family:Helvetica, Avenir, sans-serif;display:inline-block;">
                                   Write a Review
                                    </button>
                                </a>
                            @endif
                        </div>
                    </td>
                @endif

                @if($showShipping && $itemIndex === 0)
                    <td style="padding: 10px; vertical-align: top; max-width: 150px; word-wrap: break-word; white-space: normal;" rowspan="{{ count($ord->items) }}">
                        @php
                            $delivery = $deliveryPerOrder[$ord->id] ?? null;
                        @endphp
                        @if($delivery)
                            @if($ord->shipping_method === \App\Models\Order\StoreOrder::DIGITAL_DELIVERY)
                                <span style="color: #667085; font-size: 14px;">
                                    {{ $delivery['description'] ?? 'Digital Delivery' }}
                                </span>
                            @else
                                <span style="color: #1d2939; font-weight: 500; font-size: 14px;">
                                    {{ $delivery['name'] ?? 'N/A' }}
                                </span>
                                <br>
                                <span style="color: #667085; font-size: 13px;">
                                    {{ $delivery['description'] ?? '' }}
                                </span>
                                @if(isset($delivery['rate']) && $delivery['rate'] !== 'NA')
                                    <br>
                                    <span style="color: #1d2939; font-size: 14px;">
                                        {{$currency}}{{ $delivery['rate'] }}
                                    </span>
                                @endif
                            @endif
                        @else
                            <span style="color: #667085; font-size: 14px;">N/A</span>
                        @endif
                    </td>
                @endif
            </tr>
        @endforeach
    @endforeach
    </tbody>
</table>
