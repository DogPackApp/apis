@include('marketplace.emails.header')

<!-- Main content section -->
<tr>
    <td
        style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('marketplace.emails.main-title', ['content' => 'Complete Your Stripe Account Setup'])

        <p style="margin:20px 0;line-height:1.25;">
            Hi {{ $store_name }},
        </p>

        <p>Stripe requires some additional details from you in order to verify and approve your account so that you can
            receive payouts from DogPack.</p>
        <p>You can finish your setup directly in Stripe:</p>

        <div style="margin-bottom:20px;text-align: center;">
            <a href="{{ $stripe_url }}" style="text-decoration: none;"
                target="_blank">
                <button
                    style="padding:12px 30px; background:#108482; border:none; color:#fff; border-radius:8px; font-size:16px; font-weight:500; cursor:pointer; font-family:Helvetica, Avenir, sans-serif; display:inline-block;">
                    Finish setup
                </button>
            </a>
        </div>

        <p>Need help? Our team is here for you every step of the way, just reach out to <a
                href="mailto:{{ config('services.DOGPACK_EMAIL') }}"
                style="color:#108482;text-decoration:none;">{{ config('services.DOGPACK_EMAIL') }}</a> if you need any assistance!
        </p>
        <p style="margin:30px 0 10px 0;line-height:1.5;">DogPack Team</p>

    </td>
</tr>

@include('marketplace.emails.footer')
