@include('emails.seller.header')

<tr>
    <td
        style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('emails.seller.main-title', ['content' => 'Verify your Email Address'])

        <p style="margin:20px 0;line-height:1.25;">
            Hi {{ $user->first_name }},
        </p>

        <p>
            Please enter the following One-Time code to verify your email:
        </p>

        <p style="font-size: 30px; letter-spacing: 5px; text-align: center;"><strong>{{ $otp }}</strong></p>

    </td>
</tr>

@include('emails.seller.footer')
