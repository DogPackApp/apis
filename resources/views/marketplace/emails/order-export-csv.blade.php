@use('Carbon\Carbon')
@include('marketplace.emails.header')

<!-- Main content section -->
<tr>
    <td
        style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('marketplace.emails.main-title', ['content' => 'Order Export CSV File'])

        <p style="margin:20px 0;line-height:1.25;">
            Hi {{ $storeName }},
        </p>

        <p>
            Your order export CSV file is ready. You can now download it from the following link:
        </p>

        @php
            $startDate = $filters['start_date'] ?? null;
            $endDate = $filters['end_date'] ?? null;
            $search = $filters['search'] ?? null;
            $fulfilmentStatus = $filters['fulfilment_status'] ?? null;
            $orderStatus = $filters['order_status'] ?? null;
            $paymentStatus = $filters['payment_status'] ?? null;
        @endphp

        <ul>
            <li>File name: <strong>{{ $fileName }}</strong></li>
            @if(!empty($endDate) && !empty($startDate))
                <li>Start date: {{ Carbon::parse($startDate)->format('Y-m-d H:i') }}</li>
                <li>End date: {{ Carbon::parse($endDate)->format('Y-m-d H:i') }}</li>
            @endif
            @if(!empty($search))
                <li>Search: {{ $search }}</li>
            @endif
            <li>Fulfilment status: {{ $fulfilmentStatus ?? "All" }}</li>
            <li>Order status: {{ $orderStatus ?? "All" }}</li>
            <li>Payment status: {{ $paymentStatus ?? "All" }}</li>
            <li>Total orders: {{ $ordersCount }}</li>
            <li>Total items: {{ $itemsCount }}</li>
            <li>Export date: {{ Carbon::parse($exportDate)->format('Y-m-d H:i') }}</li>
            <li>File link: <a href="{{ $url }}" target="_blank">{{ $fileName }}</a></li>
        </ul>

    </td>
</tr>

@include('marketplace.emails.footer')