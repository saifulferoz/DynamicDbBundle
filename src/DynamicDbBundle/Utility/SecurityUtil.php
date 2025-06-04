<?php

/*
 * This file is part of the Report Engine.
 *
 * Copyright (c) 2025-2025, BRAC IT SERVICES LIMITED <https://www.bracits.com>
 */

namespace Feroz\DynamicDbBundle\Utility;

class SecurityUtil
{
    private const SECRETE_IV = '1fa1b273b7a8699f';
    private const ENCRYPTION_METHOD = 'AES-256-CBC';

    public static function encrypt($string, $key): string
    {
        return base64_encode(
            openssl_encrypt(
                $string,
                self::ENCRYPTION_METHOD,
                hash('sha256', $key),
                0,
                self::SECRETE_IV
            )
        );
    }

    public static function decrypt($msg, $key): false|string
    {
        return openssl_decrypt(
            base64_decode($msg),
            self::ENCRYPTION_METHOD,
            hash('sha256', $key),
            0,
            self::SECRETE_IV
        );
    }

    private static function nonceTick($timeout): float
    {
        return ceil(time() / ($timeout / 2));
    }

    private static function createNonceHash($tick, $str, $salt, $timeout): string
    {
        return substr(md5($tick.$salt.self::SECRETE_IV.$str.$timeout), -12, 10).$timeout;
    }

    public static function getNonce($str, $salt = '', $timeout = 10): string
    {
        return self::createNonceHash(self::nonceTick($timeout), $str, $salt, $timeout);
    }

    public static function isValidNonce($str, $nonce, $salt = ''): bool
    {
        $timeout = (int)(substr($nonce, 10));
        $nonceTick = self::nonceTick($timeout);

        // Nonce generated on first half.
        $expected = self::createNonceHash($nonceTick, $str, $salt, $timeout);
        if (hash_equals($expected, $nonce)) {
            return true;
        }

        // Nonce generated on second half.
        $expected = self::createNonceHash($nonceTick - 1, $str, $salt, $timeout);
        if (hash_equals($expected, $nonce)) {
            return true;
        }

        return false;
    }

    public static function mercureHash($value): string
    {
        return strtoupper(md5($value.'mercure'));
    }
}
