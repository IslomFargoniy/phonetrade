<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Biznes qoidasi buzilganda tashlanadi (kassada pul yetmaydi, dona allaqachon sotilgan ...).
 * Foydalanuvchiga matn, mobil ilovaga esa o'zgarmas kod qaytadi.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly string $field = 'business',
    ) {
        parent::__construct($message);
    }
}
