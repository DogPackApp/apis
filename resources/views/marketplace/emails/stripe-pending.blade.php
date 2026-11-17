@include('marketplace.emails.header')

<!-- Main content section -->
<tr>
    <td
        style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('marketplace.emails.main-title', ['content' => 'Your Stripe Account is Pending Verification'])

        <p style="margin:20px 0;line-height:1.25;">
            Hi {{ $store_name }},
        </p>

        <p>Stripe is currently reviewing your account information. </p>
        <p>This is part of their standard verification process to ensure everything is in order.
            You’ll receive another email as soon as there’s an update regarding your account status.</p>

        <p>If you have any questions in the meantime, feel free to reach out to us at  <a
                href="mailto:{{ config('services.DOGPACK_EMAIL') }}"
                style="color:#108482;text-decoration:none;">{{ config('services.DOGPACK_EMAIL') }}</a>.
        </p>
        <p style="margin:30px 0 10px 0;line-height:1.5;">
            Thanks,<br>
            DogPack Team
        </p>

    </td>
</tr>

@include('marketplace.emails.footer')
