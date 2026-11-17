@include('marketplace.emails.header')

<!-- Main content section -->
<tr>
    <td
        style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('marketplace.emails.main-title', ['content' => 'Shipping Label Voided'])

        <p style="margin:20px 0;line-height:1.25;">
            Hi {{ $user->first_name }},
        </p>

        <p>This is to confirm that you’ve successfully voided the shipping label for order
           #{{ $orderId }}.</p>

        <p>Since this label was not used, the cost of this label will not be deducted from any
            future payouts.</p>

        <p>If you canceled by mistake or need help generating a new label, we're here to help
            - just reach out to <a href="mailto:{{ config('services.DOGPACK_EMAIL') }}" style="color:#108482;text-decoration:none;">{{ config('services.DOGPACK_EMAIL') }}</a>.</p>

        <p style="margin:30px 0 10px 0;line-height:1.5;">
            Thank you,<br>
            DogPack Team
        </p>

    </td>
</tr>

@include('marketplace.emails.footer')
