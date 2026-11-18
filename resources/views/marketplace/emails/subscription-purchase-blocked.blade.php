@include('marketplace.emails.header')

<tr>
    <td
        style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('marketplace.emails.main-title', ['content' => "We couldn't place your Subscribe & Save order"])

        <p style="margin:20px 0;line-height:1.25;">Hi {{ $firstName }},</p>

        <p>We weren't able to place your Subscribe &amp; Save order for <strong>{{ $productTitle }}</strong>,
            scheduled for {{ $dueDate }}. <strong>You have not been charged.</strong></p>
        
        <p>Your subscription hasn't been cancelled, but this order was skipped and the next one is
            scheduled in {{ $frequencyDays }} days. Until we're able to resolve this, upcoming orders may be skipped too.</p>
        
        <p>If you'd like help, please contact
            <a href="mailto:support@dogpackapp.com" style="color:#108482;">support@dogpackapp.com</a>
            and our team will look into it with you.</p>
        
        <p style="margin:30px 0 10px 0;line-height:1.5;">The DogPack Team</p>
    </td>
</tr>

@include('marketplace.emails.footer')
