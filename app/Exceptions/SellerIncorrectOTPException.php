<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class SellerIncorrectOTPException extends Exception
{
    protected $message = 'Invalid or expired OTP';

    protected $code = Response::HTTP_BAD_REQUEST;

    public function __construct(string $message = '')
    {
        $message = $message ?: $this->message;

        parent::__construct($message, $this->code);
    }
}
