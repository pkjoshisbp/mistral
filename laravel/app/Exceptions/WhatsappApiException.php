<?php

namespace App\Exceptions;

class WhatsappApiException extends \RuntimeException
{
    public ?int $httpStatus;
    public $errorCode; // keep untyped to avoid parent property conflicts
    public ?int $errorSubcode;

    public function __construct(string $message, ?int $status = null, $code = null, ?int $subcode = null)
    {
        parent::__construct($message);
        $this->httpStatus = $status;
        $this->errorCode = $code;
        $this->errorSubcode = $subcode;
    }
}

class WhatsappTokenExpiredException extends WhatsappApiException {}
