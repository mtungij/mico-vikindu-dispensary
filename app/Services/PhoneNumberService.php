<?php

namespace App\Services;

use InvalidArgumentException;

class PhoneNumberService
{
    public function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $value = preg_replace('/[\s-]+/', '', trim($phone));

        if (preg_match('/^0(6|7)[0-9]{8}$/', $value) === 1) {
            return '+255'.substr($value, 1);
        }

        if (preg_match('/^255(6|7)[0-9]{8}$/', $value) === 1) {
            return '+'.$value;
        }

        if (preg_match('/^\+255(6|7)[0-9]{8}$/', $value) === 1) {
            return $value;
        }

        throw new InvalidArgumentException('Namba ya simu si sahihi.');
    }

    public function isValid(?string $phone): bool
    {
        try {
            return $this->normalize($phone) !== null;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
