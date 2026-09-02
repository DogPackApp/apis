@include('emails.seller.header')

<tr>
    <td style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('emails.seller.main-title', ['content' => 'Welcome to DogPack Marketplace!'])

        <p style="margin:20px 0;line-height:1.25;">
            Hi {{ $name }},
        </p>

        <p>
            Your store is set up and ready to go. You can now continue completing your onboarding steps from your seller dashboard.
        </p>

        <p style="margin:30px 0 10px 0;line-height:1.5;">
            Thanks,<br>
            DogPack Team
        </p>
    </td>
</tr>

@include('emails.seller.footer')
