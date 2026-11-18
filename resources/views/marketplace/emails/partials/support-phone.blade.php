@php $supportPhone = config('services.support_phone'); @endphp
<p style="margin-top:20px;">Need help? You can reach us by phone at <strong><a href="tel:{{ preg_replace('/[^+\d]/', '', $supportPhone) }}" style="color:#108482;text-decoration:none;">{{ $supportPhone }}</a></strong>. We're available Monday–Friday, 9:30&nbsp;am–5:00&nbsp;pm Eastern Time.</p>
