<?php

namespace App\Enums;

enum OnboardingStep: string
{
    case Product = 'is_product';
    case Shipping = 'is_shipping';
    case StoreSetting = 'is_store_setting';
    case Finance = 'is_finance';
    case Subscribe = 'is_subscribe';
}
