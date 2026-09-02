@include('emails.seller.header')

<tr>
    <td style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('emails.seller.main-title', ['content' => 'Your password was changed'])

        <p style="margin:20px 0;line-height:1.25;">
            Hi {{ $name }},
        </p>

        <p>
            This is a confirmation that the password for your DogPack Marketplace seller account was just changed.
        </p>

        <p>
            If you didn't make this change, please contact us immediately at <a href="mailto:{{ config('services.DOGPACK_EMAIL') }}" style="color:#108482;text-decoration:none;">{{ config('services.DOGPACK_EMAIL') }}</a>.
        </p>

        <p style="margin:30px 0 10px 0;line-height:1.5;">
            Thanks,<br>
            DogPack Team
        </p>
    </td>
</tr>

@include('emails.seller.footer')
