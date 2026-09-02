@include('emails.seller.header')

<tr>
    <td style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('emails.seller.main-title', ['content' => 'Forgot your password?'])

        <p style="margin:20px 0;line-height:1.25;">
            Hi {{ $name }},
        </p>

        <p>
            We received a request to reset your DogPack Marketplace seller password. You can create a new one by clicking the link below:
        </p>

        <div style="margin:30px 0;">
            <a href="{{ $resetUrl }}" style="text-decoration:none;">
                <button style="padding:12px 30px;background:#108482;border:none;color:#ffffff;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;font-family:Helvetica, Avenir, sans-serif;display:inline-block;">
                    Reset Password
                </button>
            </a>
        </div>

        <p>
            If you didn't request this, please contact us immediately at <a href="mailto:{{ config('services.DOGPACK_EMAIL') }}" style="color:#108482;text-decoration:none;">{{ config('services.DOGPACK_EMAIL') }}</a>.
        </p>

        <p style="margin:30px 0 10px 0;line-height:1.5;">
            Thanks,<br>
            DogPack Team
        </p>
    </td>
</tr>

@include('emails.seller.footer')
