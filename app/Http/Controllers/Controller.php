<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'DogPack Marketplace Seller API',
    description: 'Seller authentication, profile, onboarding, and store management endpoints.'
)]
#[OA\SecurityScheme(
    securityScheme: 'sellerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Passport personal access token',
    description: 'Pass the token returned by login/register/otp as: Authorization: Bearer {token}'
)]
abstract class Controller
{
    //
}
