<?php

namespace eseperio\verifactu\tests\Integration;

use eseperio\verifactu\Verifactu;
use eseperio\verifactu\services\VerifactuService;
use eseperio\verifactu\services\SoapClientFactoryService;
use PHPUnit\Framework\TestCase;

class VerifactuIntegrationEnvironmentTest extends TestCase
{
    public function testSoapClientFactoryUsesLocalWsdlInSandbox()
    {
        Verifactu::config('/dev/null', 'dummy', Verifactu::TYPE_CERTIFICATE, Verifactu::ENVIRONMENT_SANDBOX);
        $config = (new \ReflectionClass(VerifactuService::class))->getStaticProperties()['config'];
        $wsdl = SoapClientFactoryService::createSoapClient(
            $config[VerifactuService::WSDL_ENDPOINT],
            $config[VerifactuService::CERT_PATH_KEY],
            $config[VerifactuService::CERT_PASSWORD_KEY],
            [],
            $config['environment']
        );
        $this->assertStringContainsString('SistemaFacturacion.wsdl.xml', $wsdl->__getWsdl());
    }

    public function testSoapClientFactoryUsesRemoteWsdlInProduction()
    {
        Verifactu::config('/dev/null', 'dummy', Verifactu::TYPE_CERTIFICATE, Verifactu::ENVIRONMENT_PRODUCTION);
        $config = (new \ReflectionClass(VerifactuService::class))->getStaticProperties()['config'];
        $wsdl = SoapClientFactoryService::createSoapClient(
            $config[VerifactuService::WSDL_ENDPOINT],
            $config[VerifactuService::CERT_PATH_KEY],
            $config[VerifactuService::CERT_PASSWORD_KEY],
            [],
            $config['environment']
        );
        $this->assertStringContainsString('agenciatributaria.gob.es', $wsdl->__getWsdl());
    }
}
