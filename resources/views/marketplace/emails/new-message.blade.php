@include('marketplace.emails.header')

<!-- Main content section -->
<tr>
    <td
        style="padding:0 40px 20px 40px;font-family:Helvetica, Avenir, sans-serif;font-size:16px;line-height:1.5;color:#333333;">

        @include('marketplace.emails.main-title', ['content' => "New message from " . (isset($messageData->name) ? $messageData->name : 'Unknown User')])

        <p style="margin:20px 0;line-height:1.25;">
            Hi {{ $name ?? 'User' }},
        </p>
        <p>You've received a new message{{ isset($messageData->name) ? ' from ' . $messageData->name : '' }}:</p>

        @if(isset($messageData))
        <table width="100%" cellspacing="0" cellpadding="0" border="0" role="presentation" align="center"
            style="max-width: 400px; background-color: #ffffff; border-collapse: collapse;border-left: 3px solid #e0e0e0;">
            <tr>
                <td width="20" style="background-color: #ffffff;">&nbsp;</td>
                <td align="left"
                    style="background-color: #ffffff; padding: 10px; font-family: Arial, sans-serif; color: #555555; font-size: 14px; line-height: 20px;">
                    <table width="100%" cellspacing="0" cellpadding="0" border="0" role="presentation"
                        style="border-collapse: collapse;">
                        <tr>
                            <!-- Avatar -->
                            <td width="48" align="right" valign="top"
                                style="border-bottom: 1px solid #e0e0e0; padding: 10px;">
                                @if ($receiverType == "customer" && isset($messageData->avatar) && !empty($messageData->avatar))
                                    <img src="{{ $messageData->avatar }}" width="48" height="48" alt="User"
                                        style="border-radius: 50%; display: block;">
                                @else
                                    <table width="48" height="48" cellspacing="0" cellpadding="0" border="0" role="presentation" style="border-radius: 50%; background-color: #108482; border-collapse: collapse;">
                                        <tr>
                                            <td align="center" valign="middle" style="width: 48px; height: 48px; border-radius: 50%; font-family: Arial, sans-serif; font-size: 20px; font-weight: bold; color: white; text-transform: uppercase; line-height: 48px;">
                                                {{ isset($messageData->name) && !empty($messageData->name) ? substr($messageData->name, 0, 1) : 'U' }}
                                            </td>
                                        </tr>
                                    </table>
                                @endif
                            </td>
                            <!-- User Info -->
                            <td align="left" valign="center"
                                style="border-bottom: 1px solid #e0e0e0;">
                                <p style="margin: 0 0 4px 0; font-weight: bold; color: #333333;">
                                    {{ $messageData->name ?? 'Unknown User' }}
                                </p>
                            </td>
                        </tr>
                    </table>

                    <!-- Spacer -->
                    <div style="font-size: 24px; line-height: 24px;">&nbsp;</div>

                    <!-- Message -->
                    <p style="margin: 0 0 10px 0;">{{ $messageData->message ?? 'No message content available.' }}</p>
                </td>
            </tr>
        </table>
        @endif
        <br>
        <div style="margin-bottom:20px;text-align: center;">
            <a href="{{ $receiverType == 'seller' ? config('services.marketplace_fe_url') . 'messages' : config('services.website_url') . '/marketplace/store/' . $store?->slug . '?chat'. (isset($messageData->customerEmail) ? '&email=' . $messageData->customerEmail : '') }}" style="text-decoration: none;"
                target="_blank">
                <button
                    style="padding:12px 30px; background:#108482; border:none; color:#fff; border-radius:8px; font-size:16px; font-weight:500; cursor:pointer; font-family:Helvetica, Avenir, sans-serif; display:inline-block;">
                    Reply to message
                </button>
            </a>
        </div>
        <p> You can view and reply to this message in the {{ $receiverType == 'seller' ? 'seller panel' : 'store page' }}.</p>

        <p style="margin:30px 0 10px 0;line-height:1.5;">
            DogPack Team
        </p>

    </td>
</tr>

@include('marketplace.emails.footer')
