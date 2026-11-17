@include('emails.seller.header')

<tr>
    <td style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('emails.seller.main-title', ['content' => 'Product Export CSV File'])

        <p style="margin:20px 0;line-height:1.25;">
            Hi {{ $store_name }},
        </p>

        <p>
            Your product export CSV file is ready. Please download it from the following link:
        </p>

        <ul>
            <li>File name: <strong>{{ $file_name }}</strong></li>
            <li>Total products: {{ $number_of_products }}</li>
            <li>Export date: {{ $export_date }}</li>
            <li>File link: <a href="{{ $url }}" target="_blank">{{ $file_name }}</a></li>
        </ul>

    </td>
</tr>

@include('emails.seller.footer')
