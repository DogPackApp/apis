<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DogPack Packing Slip</title>
    <style>
        @page { 
            margin: 20px; 
            size: 4in 7in;
        }
        body {
            font-family: "Be Vietnam Pro", Arial, sans-serif;
            margin: 0;
            padding: 10px;
            color: #333;
            font-size: 9px;
            min-height: calc(6in - 20px); /* 6in height minus padding */
            position: relative;
            box-sizing: border-box;
        }
        .packing-slip {
            width: 100%;
            max-width: 4in;
            margin: 0 auto;
            padding: 8px;
            box-sizing: border-box;
            padding-bottom: 40px; /* Space for footer */
        }
        .header {
            margin-bottom: 8px;
            overflow: hidden;
        }
        .header h2 {
            float: left;
            margin-top: 0;
            margin-bottom: 5px;
            font-size: 12px;
        }
        .header-right {
            float: right;
            text-align: right;
            font-size: 9px;
        }
        .address-section {
            margin-bottom: 10px;
            overflow: hidden;
        }
        .address-box {
            width: 48%;
            padding: 5px;
            border-radius: 3px;
            box-sizing: border-box;
        }
        .shipping-address {
            float: left;
        }
        .billing-address {
            float: right;
        }
        .address-box h3 {
            margin-top: 0;
            margin-bottom: 3px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 2px;
            font-size: 10px;
        }
        .address-box p {
            margin: 5px 0;
            line-height: 1.2;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8px;
        }
        th {
            background-color: #108482;
            text-align: left;
            color: #ffffff;
            padding: 5px;
            font-size: 9px;
        }
        td {
            padding: 5px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        .product-main-details {
            overflow: hidden;
        }
        .product-image {
            float: left;
            margin-right: 5px;
            width: 30px;
            height: 30px;
        }
        .product-text {
            float: left;
            max-width: calc(100% - 35px);
        }
        .product-details {
            margin-top: 2px;
            font-size: 7px;
        }
        .footer {
            text-align: center;
            position: absolute;
            bottom: 10px;
            left: 0;
            right: 0;
            width: 100%;
            max-width: 4in;
            margin: 0 auto;
            padding-top: 5px;
        }
        .store-info {
            text-align: center;
            margin-top: 10px;
            font-size: 8px;
        }
        .footer p {
            font-size: 10px;
            margin: 5px 0;
        }
        /* Clearfix to handle floats */
        .clearfix:after {
            content: "";
            display: table;
            clear: both;
        }
    </style>
</head>
<body>
<div class="packing-slip">
    <div class="header clearfix">
        <h2>{{ $order->store->name }}</h2>
        <div class="header-right">
            <div>#{{$order->order_no}}</div>
            <div>{{ now()->parse($order->created_at)->format('D m, Y') }}</div>
        </div>
    </div>

    <div class="address-section clearfix">
        <div class="address-box shipping-address">
            <h3>Shipped to:</h3>
            <p>
                {{ $order->customer->name ?? "Customer" }}<br>
                {{ $order->customer->shipping_address['street1'] ?? "N/A" }}<br>
                {{ $order->customer->shipping_address['street2'] ?? "" }}<br>
                {{ $order->customer->shipping_address['city'] ?? "N/A" }}<br>
                {{ $order->customer->shipping_address['province'] ?? "N/A" }}<br>
                {{ $order->customer->shipping_address['zipcode'] ?? "N/A" }}<br>
                {{ $order->customer->shipping_address['country'] ?? "N/A" }}
            </p>
        </div>

        <div class="address-box billing-address">
            <h3>Billed to:</h3>
            @if($order->customer->billing_address)
                <p>
                    {{ $order->customer->name ?? "Customer" }}<br>
                    {{ $order->customer->billing_address['street1'] ?? "N/A" }}<br>
                    {{ $order->customer->billing_address['street2'] ?? "" }}<br>
                    {{ $order->customer->billing_address['city'] ?? "N/A" }}<br>
                    {{ $order->customer->billing_address['province'] ?? "N/A" }}<br>
                    {{ $order->customer->billing_address['zipcode'] ?? "N/A" }}<br>
                    {{ $order->customer->billing_address['country'] ?? "N/A" }}
                </p>
            @else
                <p>
                    {{ $order->customer->name ?? "Customer" }}<br>
                    {{ $order->customer->shipping_address['street1'] ?? "N/A" }}<br>
                    {{ $order->customer->shipping_address['street2'] ?? "" }}<br>
                    {{ $order->customer->shipping_address['city'] ?? "N/A" }}<br>
                    {{ $order->customer->shipping_address['province'] ?? "N/A" }}<br>
                    {{ $order->customer->shipping_address['zipcode'] ?? "N/A" }}<br>
                    {{ $order->customer->shipping_address['country'] ?? "N/A" }}
                </p>
            @endif
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width: 65%">Products</th>
            <th style="width: 15%">Type</th>
            <th style="width: 15%">Qty</th>
        </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                @if($item->quantity == $item->refunded)
                    @continue;
                @else
                    <tr>
                        <td>
                            <div class="product-main-details clearfix">
                                <img class="product-image" src="{{ $item->product_snapshot['image'] ?? '' }}" alt="Product Image" onerror="this.onerror=null; this.src='{{ asset('public/website/img/marketplace/default/empty_product.webp') }}';"/>
                                <div class="product-text">
                                    <strong style="font-weight: 500">{{ $item->product_snapshot['name'] ?? "Dog Product" }}</strong><br>
                                    @if(isset($item->product_snapshot['customization']))
                                    <div class="product-details">
                                        {{ $item->product_snapshot['customization'] }}
                                    </div>
                                    @endif
                                    @if($item->item_options)
                                        @foreach($item->__attributeOptions($item->item_options) as $options)
                                            <div class="product-details">
                                                @if($options->attribute_value != \App\Models\Marketplace\ItemVariant::SHOPIFY_DEFAULT_VARIANT)
                                                    {{ $options->attribute->name }} {{ $options->attribute_value }}
                                                @endif
                                        </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>{{ array_search($item->product->type, \App\Models\Marketplace\Item::TYPES) }}</td>
                        <td style="text-align: center">{{ $item->quantity ? $item->quantity - $item->refunded : "1" }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>

<div class="footer">
    <div class="store-info">
        <p>
            <strong>{{ $order->store?->name ?? 'N/A' }}</strong><br>
            {{ $order->store->address?->address ?? 'N/A' }}<br>
            {{ $order->store->seller->email ?? 'support@dogpackapp.com' }}
        </p>
    </div>
    <p style="font-size: 8px;">Thank you for being part of the DogPack community!</p>
</div>
</body>
</html>
