@include('emails.email_header')
<table align="center" cellpadding="0" cellspacing="0" class="inner-body" style="font-family:Avenir, Helvetica, sans-serif;background-color:#FFFFFF;margin:0 auto;padding:0;width:570px;" width="570">
    <tbody>
        <tr>
            <td class="content-cell" style="font-family:Avenir, Helvetica, sans-serif;padding:35px;">
                <h1 style="font-family:Avenir, Helvetica, sans-serif;color:#2F3133;font-size:19px;font-weight:bold;margin-top:0;text-align:left;">{{__('api_common.dear')}} SupportDog,</h1>

                <p style="font-family:Avenir, Helvetica, sans-serif;color:#74787E;font-size:16px;line-height:1.5em;margin-top:0;text-align:left;text-indent: 50px;">
                    Payment Failed: {{ $data['reason'] }}<br/>
                <table align="center" cellpadding="3" cellspacing="3" class="inner-body" style="font-family:Avenir, Helvetica, sans-serif;background-color:#FFFFFF;margin:0 auto;padding:10px;">
                    <tbody>
                    <tr><td style="width: 100%">Entity Type</td><td style="width: 100%">{{ $data['entity_type']}}</td></tr>
                    <tr><td style="width: 100%">Name</td><td style="width: 100%">{{ $data['entity_name']}}</td></tr>
                    @if(isset($data['entity_email']) && !empty($data['entity_email']))
                        <tr><td style="width: 100%">Email</td><td style="width: 100%">{{ $data['entity_email'] }}</td></tr>
                    @endif
                    @if(isset($data['entity_uuid']) && !empty($data['entity_uuid']))
                        <tr><td style="width: 100%">ID</td><td style="width: 100%">{{ $data['entity_uuid'] }}</td></tr>
                    @endif
                    <tr><td style="width: 100%">Failure Message</td><td style="width: 100%">{{ $data['failure_message'] }}</td></tr>
                    </tbody>
                </table>
                </p>
                <p style="font-family:Avenir, Helvetica, sans-serif;color:#74787E;font-size:16px;line-height:1.5em;margin-top:0;text-align:left;">Regards,  <br> Team DogPack
                    <br />
                    <a href="https://www.dogpackapp.com/">www.dogpackapp.com</a>
                </p>
                @include('marketplace.emails.partials.support-phone')
            </td>
        </tr>
    </tbody>
</table>
@include('emails.email_footer')