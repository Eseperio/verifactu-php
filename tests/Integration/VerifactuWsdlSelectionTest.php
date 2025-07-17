<?php

namespace eseperio\verifactu\tests\Integration;

use eseperio\verifactu\Verifactu;
use eseperio\verifactu\services\VerifactuService;
use eseperio\verifactu\services\SoapClientFactoryService;
use PHPUnit\Framework\TestCase;

class VerifactuWsdlSelectionTest extends TestCase
{
    public function testWsdlIsLocalInSandbox()
    {
        Verifactu::config('/dev/null', 'dummy', Verifactu::TYPE_CERTIFICATE, Verifactu::ENVIRONMENT_SANDBOX);
        $config = (new \ReflectionClass(VerifactuService::class))->getStaticProperties()['config'];
        $client = SoapClientFactoryService::createSoapClient(
            $config[VerifactuService::WSDL_ENDPOINT],
            $config[VerifactuService::CERT_PATH_KEY],
            $config[VerifactuService::CERT_PASSWORD_KEY],
            [],
            $config['environment']
        );
        $wsdlProperty = (new \ReflectionObject($client))->getProperty('wsdl');
        $wsdlProperty->setAccessible(true);
        $wsdlValue = $wsdlProperty->getValue($client);
        $this->assertStringContainsString('SistemaFacturacion.wsdl.xml', $wsdlValue);
    }

    public function testWsdlIsRemoteInProduction()
    {
        Verifactu::config('/dev/null', 'dummy', Verifactu::TYPE_CERTIFICATE, Verifactu::ENVIRONMENT_PRODUCTION);
        $config = (new \ReflectionClass(VerifactuService::class))->getStaticProperties()['config'];
        $client = SoapClientFactoryService::createSoapClient(
            $config[VerifactuService::WSDL_ENDPOINT],
            $config[VerifactuService::CERT_PATH_KEY],
            $config[VerifactuService::CERT_PASSWORD_KEY],
            [],
            $config['environment']
        );
        $wsdlProperty = (new \ReflectionObject($client))->getProperty('wsdl');
        $wsdlProperty->setAccessible(true);
        $wsdlValue = $wsdlProperty->getValue($client);
        $this->assertStringContainsString('agenciatributaria.gob.es', $wsdlValue);
    }
}
