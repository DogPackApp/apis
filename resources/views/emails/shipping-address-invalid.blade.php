<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shipping Address Invalid</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
</head>
<body style="font-family: Arial, sans-serif; color:#222;">
    <h2>Shipping Address Verification Failed</h2>
    <p>The following shipping address could not be verified via EasyPost.</p>
    <p>Store: {{ $shipping->store->name }}</p>

    @if(isset($shipping))
        <p>
            <strong>Address:</strong><br>
            {{ $shipping->street1 }}<br>
            {{ $shipping->city }}, {{ $shipping->province }} {{ $shipping->zipcode }}<br>
            {{ $shipping->country }}
        </p>
        @if(!empty($shipping->street2))
            <p><strong>Street 2:</strong> {{ $shipping->street2 }}</p>
        @endif
        @if(!empty($shipping->easypost_id))
            <p><strong>EasyPost ID:</strong> {{ $shipping->easypost_id }}</p>
        @endif
    @else
        <p>No shipping address record was created.</p>
    @endif

    <p>Please review and correct the address details.</p>
</body>
</html>
