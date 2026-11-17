</table>

                <div style="text-align:center;padding:0 20px;max-width:800px;width:100%;">
                    <table cellpadding="0" cellspacing="0" border="0" style="width:100%;font-family:Avenir, Helvetica, sans-serif;">
                        <tr>
                            <td style="text-align:center;padding:0 40px;color:#666666;">
                                <div style="text-align:left;font-size:16px;line-height:1.5;color:#333333;">
                                    @include('emails.seller.partials.support-phone')
                                </div>

                                <div style="margin:20px 0;height:1px;background-color:#e0e0e0;"></div>

                                <p style="margin:20px 0;">
                                    <a href="https://www.facebook.com/officialdogpack" target="_blank" style="text-decoration:none;margin:0 8px;">
                                        <img src="{{ config('services.website_assets_url') . 'backend-assets/images/facebook.png' }}" alt="Facebook" width="35" height="35">
                                    </a>
                                    <a href="https://www.instagram.com/officialdogpack" target="_blank" style="text-decoration:none;margin:0 8px;">
                                        <img src="{{ config('services.website_assets_url') . 'backend-assets/images/instagram.png' }}" alt="Instagram" width="35" height="35">
                                    </a>
                                    <a href="https://www.tiktok.com/@officialdogpack" target="_blank" style="text-decoration:none;margin:0 8px;">
                                        <img src="{{ config('services.website_assets_url') . 'backend-assets/images/tiktok.png' }}" alt="TikTok" width="35" height="35">
                                    </a>
                                    <a href="https://twitter.com/officialdogpack" target="_blank" style="text-decoration:none;margin:0 8px;">
                                        <img src="{{ config('services.website_assets_url') . 'backend-assets/images/twitter.png' }}" alt="Twitter" width="35" height="35">
                                    </a>
                                    <a href="https://www.youtube.com/@dogpackapp" target="_blank" style="text-decoration:none;margin:0 8px;">
                                        <img src="{{ config('services.website_assets_url') . 'backend-assets/images/youtube.png' }}" alt="YouTube" width="35" height="35">
                                    </a>
                                </p>

                                <p style="margin:20px 0;font-size:14px;color:#666666;">
                                    <a style="color:#666666;text-decoration:underline;" href="{{ config('services.website_url') }}" target="_blank">DogPack App</a>
                                    <span style="margin:0 8px;">|</span>
                                    <a style="color:#666666;text-decoration:underline;" href="{{ config('services.website_url') }}/marketplace" target="_blank">Marketplace</a>
                                    <span style="margin:0 8px;">|</span>
                                    <a style="color:#666666;text-decoration:underline;" href="{{ config('services.website_url') }}/contact-us" target="_blank">Contact us</a>
                                </p>

                                <div style="margin:20px 0;height:1px;background-color:#e0e0e0;"></div>
                            </td>
                        </tr>
                    </table>
                </div>

            </td>
        </tr>
    </table>
</body>
</html>
