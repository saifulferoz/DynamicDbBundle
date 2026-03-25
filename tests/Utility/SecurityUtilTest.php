<?php

namespace Feroz\DynamicDbBundle\Tests\Utility;

use Feroz\DynamicDbBundle\Utility\SecurityUtil;
use PHPUnit\Framework\TestCase;

class SecurityUtilTest extends TestCase
{
    public function testEncryptionDecryption(): void
    {
        $key = 'secret_key';
        $string = 'my_secret_password';
        
        $encrypted = SecurityUtil::encrypt($string, $key);
        $this->assertNotEquals($string, $encrypted);
        
        $decrypted = SecurityUtil::decrypt($encrypted, $key);
        $this->assertEquals($string, $decrypted);
    }
    
    public function testNonce(): void
    {
        $nonce = SecurityUtil::getNonce('action', 'salt', 10);
        $this->assertTrue(SecurityUtil::isValidNonce('action', $nonce, 'salt'));
        
        $this->assertFalse(SecurityUtil::isValidNonce('action', $nonce, 'wrong_salt'));
    }
    
    public function testMercureHash(): void
    {
        $hash = SecurityUtil::mercureHash('value');
        $this->assertEquals(strtoupper(md5('value'.'mercure')), $hash);
    }
}
