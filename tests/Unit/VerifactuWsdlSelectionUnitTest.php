<?php

namespace eseperio\verifactu\tests\Unit;

use eseperio\verifactu\Verifactu;
use eseperio\verifactu\services\VerifactuService;
use eseperio\verifactu\services\SoapClientFactoryService;
use PHPUnit\Framework\TestCase;

class VerifactuWsdlSelectionUnitTest extends TestCase
{
    public function testWsdlIsLocalInSandbox()
    {
        Verifactu::config('/dev/null', 'dummy', Verifactu::TYPE_CERTIFICATE, Verifactu::ENVIRONMENT_SANDBOX);
        $config = (new \ReflectionClass(VerifactuService::class))->getStaticProperties()['config'];
        $wsdl = SoapClientFactoryService::getWsdlForTest(
            $config[VerifactuService::WSDL_ENDPOINT],
            $config['environment']
        );
        $this->assertStringContainsString('docs/aeat/SistemaFacturacion.wsdl.xml', $wsdl);
        $this->assertFalse(str_starts_with($wsdl, '/home/'), 'WSDL path should not be absolute');
    }

    public function testWsdlIsRemoteInProduction()
    {
        Verifactu::config('/dev/null', 'dummy', Verifactu::TYPE_CERTIFICATE, Verifactu::ENVIRONMENT_PRODUCTION);
        $config = (new \ReflectionClass(VerifactuService::class))->getStaticProperties()['config'];
        $wsdl = SoapClientFactoryService::getWsdlForTest(
            $config[VerifactuService::WSDL_ENDPOINT],
            $config['environment']
        );
        $this->assertStringContainsString('agenciatributaria.gob.es', $wsdl);
    }
}
