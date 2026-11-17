<tr>
    <!-- Product image -->
    <td width="60" valign="top">
        <img src="{{ $item->product_snapshot['image'] ?? '' }}" width="60" height="60" alt="Product Image"
             style="border-radius: 6px; display: block;" onerror="this.onerror=null; this.src='{{ asset('public/website/img/marketplace/default/empty_product.webp') }}';" />
    </td>

    <!-- Title + Options -->
    <td valign="top" style="padding-left: 10px;padding-top: 5px;">
        <p style="margin: 0; font-weight: bold; font-size: 14px; color: #000;">
            {{ $item->product_snapshot['name'] ?? "Dog Product" }}
        </p>
        @if($item->item_options)
            <p style="margin: 4px 0 0 0; font-size: 13px; color: #555;">
                @php
                    $attributeOptions = $item->__attributeOptions($item->item_options);
                    $lastIndex = count($attributeOptions) - 1;
                @endphp
                @foreach($attributeOptions as $i => $options)
                    @if($options->attribute_value != \App\Models\Product\ItemVariant::SHOPIFY_DEFAULT_VARIANT)
                    <span style="color: #666;">
                        {{ $options->attribute->name }}: {{ $options->attribute_value }}
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
        <p style="margin: 4px 0 0 0; font-size: 13px; color: #555;">
            <span style="color: #666;">
                Quantity: {{ $item->quantity ? $item->quantity - $item->refunded : 1 }}
                @if ($item->refunded > 0)
                    (Refunded: {{ $item->refunded }})
                @endif
            </span>
        </p>
    </td>
</tr>
