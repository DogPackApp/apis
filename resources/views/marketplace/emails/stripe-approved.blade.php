@include('marketplace.emails.header')

<!-- Main content section -->
<tr>
    <td
        style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('marketplace.emails.main-title', ['content' => 'You’re All Set to Receive Payouts!'])

        <p style="margin:20px 0;line-height:1.25;">
            Hi {{ $store_name }},
        </p>

        <p>Great news — your Stripe account has been successfully connected!</p>
        <p>You’re now fully integrated and can begin receiving payouts from DogPack.
            <br>
            To manage your store, click the button below to go to your seller panel:
        </p>

        <div style="margin-bottom:20px;text-align: center;">
            <a href="{{ config('services.marketplace_fe_url') . 'dashboard' }}" style="text-decoration: none;"
                target="_blank">
                <button
                    style="padding:12px 30px; background:#108482; border:none; color:#fff; border-radius:8px; font-size:16px; font-weight:500; cursor:pointer; font-family:Helvetica, Avenir, sans-serif; display:inline-block;">
                    Go to Seller Panel
                </button>
            </a>
        </div>

        <p>Thank you for joining the marketplace - we can’t wait to see your store thrive!</p>
        <p style="margin:30px 0 10px 0;line-height:1.5;">DogPack Team</p>

    </td>
</tr>

@include('marketplace.emails.footer')