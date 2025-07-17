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
        $this->assertStringContainsString('SistemaFacturacion.wsdl.xml', $wsdl);
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
