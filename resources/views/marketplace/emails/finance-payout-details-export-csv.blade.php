@include('marketplace.emails.header')

<tr>
    <td
        style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('marketplace.emails.main-title', ['content' => 'Finance Details Export CSV File'])

        <p style="margin:20px 0;line-height:1.25;">
            Hi {{ $storeName }},
        </p>

        <p>
            Your finance payout details export CSV file is ready. You can download it from the following link:
        </p>

        <ul>
            <li>File name: <strong>{{ $fileName }}</strong></li>
            <li>Status filter: {{ $statusFilter }}</li>
            <li>Total orders: {{ $orderCount }}</li>
            <li>Export date: {{ $exportDate }}</li>
            <li>File link: <a href="{{ $url }}" target="_blank">{{ $fileName }}</a></li>
        </ul>

    </td>
</tr>

@include('marketplace.emails.footer')
