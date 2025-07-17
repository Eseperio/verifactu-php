<?php

namespace eseperio\verifactu\tests\Unit;

use eseperio\verifactu\Verifactu;
use eseperio\verifactu\services\VerifactuService;
use PHPUnit\Framework\TestCase;

class VerifactuCriticalConfigTest extends TestCase
{
    public function testConfigWithContentOverridesCertPathAndUsesInMemory()
    {
        Verifactu::config('/dev/null', 'dummy', Verifactu::TYPE_CERTIFICATE, Verifactu::ENVIRONMENT_PRODUCTION);
        Verifactu::configWithContent('inmemorycert', 'dummy', Verifactu::TYPE_CERTIFICATE, Verifactu::ENVIRONMENT_SANDBOX);
        $config = (new \ReflectionClass(VerifactuService::class))->getStaticProperties()['config'];
        $this->assertEquals('inmemorycert', $config[VerifactuService::CERT_CONTENT_KEY]);
        $this->assertNull($config[VerifactuService::CERT_PATH_KEY]);
    }

    public function testConfigWithContentSetsWsdlLocalInSandbox()
    {
        Verifactu::configWithContent('inmemorycert', 'dummy', Verifactu::TYPE_CERTIFICATE, Verifactu::ENVIRONMENT_SANDBOX);
        $config = (new \ReflectionClass(VerifactuService::class))->getStaticProperties()['config'];
        $wsdl = $config[VerifactuService::WSDL_ENDPOINT];
        $this->assertStringContainsString('SistemaFacturacion.wsdl.xml', $wsdl);
    }
}
