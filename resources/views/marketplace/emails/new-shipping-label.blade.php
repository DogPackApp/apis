@include('marketplace.emails.header')

<!-- Main content section -->
<tr>
    <td
        style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('marketplace.emails.main-title', ['content' => "Your package is on its way!"])

        <p style="margin:20px 0;line-height:1.25;">
            Hi {{ $user->first_name }},
        </p>

        <p>#{{ $order->order_no }} has been processed by {{ $store->name }} with new shipping details and is now on its way to
            you.</p>

        <div style="margin-bottom:20px;text-align: center;">
            <a href="{{ isset($shipmentDetails['shipment']) ? $shipmentDetails['shipment'] : $shipmentDetails['tracker']['public_url'] ?? '#' }}" style="text-decoration: none;" target="_blank">
                <button
                    style="padding:12px 30px; background:#108482; border:none; color:#fff; border-radius:8px; font-size:16px; font-weight:500; cursor:pointer; font-family:Helvetica, Avenir, sans-serif; display:inline-block;">
                    Track Package
                </button>
            </a>
        </div>

        <p>Items in this shipment:</p>

        @include('marketplace.emails.partials.view-order-button')

        <table cellpadding="0" cellspacing="0" width="100%"
            style="font-family: Arial, sans-serif; font-size: 14px; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid #ddd;">
                    <th align="left" style="padding: 10px;">Store</th>
                    <th align="left" style="padding: 10px;">Item</th>
                </tr>
            </thead>
            <tbody>

                <!-- First store -->
                <tr>
                    <td width="140" style="padding: 10px; vertical-align: top; width: 140px;" rowspan="3">
                        <a href="{{ config('services.website_url') }}/marketplace/store/{{ $store->slug }}"
                            style="color: #1d2939; text-decoration: underline;" target="_blank">{{ $order->store->name }}</a><br><br>
                        <a href="{{ config('services.website_url') }}/marketplace/store/{{ $store->slug }}?chat"
                            style="text-decoration: none;" target="_blank">
                            <span style="display: inline-block; background-color: #108482; color: #fff; padding: 8px 14px; border-radius: 12px; font-size: 14px; white-space: nowrap; mso-line-height-rule: exactly;">Contact&nbsp;store</span>
                        </a>
                    </td>
                    <td style="padding: 10px; vertical-align: top;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            @foreach($order->items as $item)
                                @if($item->quantity == $item->refunded)
                                    @continue;
                                @else
                                    @include('marketplace.emails.partials.items')
                                @endif
                            @endforeach
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>


        <p>Please note: If you ordered from multiple sellers, your items may arrive in separate
            shipments.</p>

        <p style="margin-bottom: 0px">You can always check the latest status in your <a
                href="{{ config('services.website_url') }}/marketplace/orders" target="_blank" style="color:#108482;">Dogpack</a>
            account.</p>
        <p style="margin-top: 0px">Not a user? <a href="{{ config('services.website_url') }}/signup" target="_blank"
                style="color:#108482;">Sign-up</a> to DogPack
            with the same email that you used to place the order to track your orders</p>
        <p style="margin:30px 0 10px 0;line-height:1.5;">DogPack Team</p>
    </td>
</tr>

@include('marketplace.emails.footer')
