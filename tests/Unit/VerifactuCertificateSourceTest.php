<?php

namespace eseperio\verifactu\tests\Unit;

use eseperio\verifactu\Verifactu;
use eseperio\verifactu\services\VerifactuService;
use PHPUnit\Framework\TestCase;

class VerifactuCertificateSourceTest extends TestCase
{
    public function testUsesCertificateContentWhenConfigured()
    {
        Verifactu::configWithContent('CERTDATA', 'dummy', Verifactu::TYPE_CERTIFICATE, Verifactu::ENVIRONMENT_SANDBOX);
        $config = (new \ReflectionClass(VerifactuService::class))->getStaticProperties()['config'];
        $certPath = $config[VerifactuService::CERT_PATH_KEY];
        $certContent = $config[VerifactuService::CERT_CONTENT_KEY];
        $this->assertNull($certPath);
        $this->assertEquals('CERTDATA', $certContent);
        // Simular uso: el método getClient debe usar el contenido, no la ruta
        $reflection = new \ReflectionClass(VerifactuService::class);
        $method = $reflection->getMethod('getClient');
        $method->setAccessible(true);
        $client = $method->invoke(null);
        $this->assertNotNull($client);
    }
}
