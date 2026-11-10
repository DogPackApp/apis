@php use Illuminate\Support\Facades\Crypt; @endphp
@include('marketplace.emails.header')

<!-- Main content section -->
<tr>
    <td
        style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('marketplace.emails.main-title', ['content' => 'How was your order? Leave a quick review!'])

        <p style="margin:20px 0;line-height:1.25;">
            Hi {{ $user->name ?? "User" }},
        </p>

        <p>We hope you and your pet are loving your recent order from DogPack Marketplace!
        <br>
            If you have a moment, we’d love to hear what you think. It only takes a few seconds
            - and means a lot to our sellers.
        </p>

        <table cellpadding="0" cellspacing="0" width="100%"
            style="font-family: Arial, sans-serif; font-size: 16px; border-collapse: collapse;">
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td align="center" style="padding: 10px; vertical-align: center;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <!-- Product image -->
                                    <td width="60" valign="center">
                                        <img src="{{ $item->product_snapshot['image'] ?? '' }}" width="60" height="60"
                                             alt="Product Image"
                                             style="border-radius: 6px; display: block;" onerror="this.onerror=null; this.src='{{ asset('public/website/img/marketplace/default/empty_product.webp') }}';"/>
                                    </td>

                                    <!-- Title + Options -->
                                    <td valign="center" style="padding-left: 10px;padding-top: 5px;">
                                        <p style="margin: 0; font-weight: bold; font-size: 16px; color: #000;">
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
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td align="center" style="padding: 10px; vertical-align: center;">
                            <div style="margin:30px 0; text-align: center;">
                                <a href="{{ config('services.website_url') }}/marketplace/orders/add-review/{{ $item->product_snapshot['uuid'] }}/{{ $order->uuid }}/{{ $user->email }}"
                                   style="text-decoration:none;" target="_blank">
                                    <button
                                            style="padding:12px 30px;background:#108482;border:none;color:#ffffff;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;font-family:Helvetica, Avenir, sans-serif;display:inline-block;">
                                        Write a Review
                                    </button>
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p>Your feedback helps our sellers grow and helps other pet lovers shop with
            confidence.</p>

        <p style="margin:30px 0 10px 0;line-height:1.5;">
            Thanks for being part of the DogPack community,<br>
            DogPack Team
        </p>

    </td>
</tr>

@include('marketplace.emails.footer')
