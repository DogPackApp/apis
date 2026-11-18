@include('emails.seller.header')

<tr>
    <td style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('emails.seller.main-title', ['content' => 'Product Import Complete'])

        <p style="margin:20px 0;line-height:1.25;">
            Hi {{ $storeName }},
        </p>

        <p>
            Your imported products are ready. You can now view them on the marketplace seller panel.
        </p>

        <ul>
            <li><b>Import Started At:</b> {{ $importStartedAt }}</li>
            <li><b>Import Completed At:</b> {{ $importCompletedAt }}</li>
            <li><b>Total Products Imported:</b> {{ $totalProductsImported }}</li>
            @if($totalProductsFailed > 0)
                <li><b>Total Products Failed:</b> {{ $totalProductsFailed }}</li>
            @endif
        </ul>

        <a href="{{ config('services.marketplace_fe_url').'products' }}" style="text-decoration: none;" target="_blank">
            <button style="padding:12px 30px; background:#108482; border:none; color:#fff; border-radius:8px; font-size:16px; font-weight:500; cursor:pointer; font-family:Helvetica, Avenir, sans-serif; display:inline-block;">
                View products
            </button>
        </a>

    </td>
</tr>

@include('emails.seller.footer')
