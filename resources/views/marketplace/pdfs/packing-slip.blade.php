<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DogPack Packing Slip</title>
    <style>
        @page { margin:40px 20px 20px 20px; }
        body {
            font-family: "Be Vietnam Pro",Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            min-height: 100vh;
            position: relative;
        }
        .packing-slip {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            padding-bottom: 100px; /* Add space for footer */
        }
        .header {
            margin-bottom: 20px;
            overflow: hidden;
        }
        .header h2 {
            float: left;
            margin-top: 0;
        }
        .header-right {
            float: right;
            text-align: right;
        }
        .address-section {
            margin-bottom: 30px;
            overflow: hidden;
        }
        .address-box {
            width: 45%;
            padding: 15px;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .shipping-address {
            float: left;
        }
        .billing-address {
            float: right;
        }
        .address-box h4 {
            margin-top: 0;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .order-info {
            margin-bottom: 20px;
            background-color: #f8f8f8;
            padding: 10px;
            border-radius: 5px;
            overflow: hidden;
        }
        .order-info-left {
            float: left;
        }
        .order-info-right {
            float: right;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #108482;
            text-align: left;
            color: #ffffff;
            padding: 20px 15px;
        }
        td {
            padding: 15px 10px;
            border-bottom: 1px solid #ddd;
        }
        .product-main-details {
            overflow: hidden;
        }
        .product-image {
            float: left;
            margin-right: 22px;
            width: 55px;
            height: 55px;
        }
        .product-text {
            float: left;
            max-width: calc(100% - 122px);
        }
        .product-details {
            margin-top: 5px;
        }
        .footer {
            text-align: center;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 15px 0;
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
        }
        .store-info {
            text-align: center;
            margin-top: 40px;
            font-size: 14px;
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
            <h4>Shipped to:</h4>
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
            <h4>Billed to:</h4>
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
            <th style="width: 15%">Quantity</th>
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
                                <img class="product-image" src="{{ $item->product_snapshot['image'] ?? '' }}" alt="Product Image" onerror="this.onerror=null; this.src='{{ asset('public/website/img/marketplace/default/empty_product.webp') }}';" />
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
                        <td>{{ array_search($item->product->type, \App\Models\Marketplace\Item::TYPES) === "PHYSICAL" ? "Physical" : "Digital" }}</td>
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
            <strong>{{ $order->store?->name ?? 'DogPack Store' }}</strong><br>
            {{ $order->store->address?->address ?? '9999 Cavendish Boul, Saint-Laurent, QC, H4M 2X5' }}<br>
            {{ $order->store->seller->email ?? 'superdog@dogpackapp.com' }}
        </p>
    </div>
    <p style="font-size: 14px;">Thank you for being part of the DogPack community!</p>
</div>
</body>
</html>
