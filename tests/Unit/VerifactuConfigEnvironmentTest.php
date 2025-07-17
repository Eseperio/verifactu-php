<?php

namespace eseperio\verifactu\tests\Unit;

use eseperio\verifactu\Verifactu;
use eseperio\verifactu\services\VerifactuService;
use PHPUnit\Framework\TestCase;

class VerifactuConfigEnvironmentTest extends TestCase
{
    public function testConfigStoresEnvironmentCorrectly()
    {
        Verifactu::config('/dev/null', 'dummy', Verifactu::TYPE_CERTIFICATE, Verifactu::ENVIRONMENT_SANDBOX);
        $config = (new \ReflectionClass(VerifactuService::class))->getStaticProperties()['config'];
        $this->assertEquals('sandbox', $config['environment']);
    }

    public function testConfigWithContentStoresEnvironmentCorrectly()
    {
        Verifactu::configWithContent('dummycontent', 'dummy', Verifactu::TYPE_CERTIFICATE, Verifactu::ENVIRONMENT_PRODUCTION);
        $config = (new \ReflectionClass(VerifactuService::class))->getStaticProperties()['config'];
        $this->assertEquals('production', $config['environment']);
    }

    public function testSoapClientFactoryReceivesEnvironmentSandbox()
    {
        Verifactu::config('/dev/null', 'dummy', Verifactu::TYPE_CERTIFICATE, Verifactu::ENVIRONMENT_SANDBOX);
        $config = (new \ReflectionClass(VerifactuService::class))->getStaticProperties()['config'];
        $environment = $config['environment'];
        $this->assertEquals('sandbox', $environment);
    }

    public function testSoapClientFactoryReceivesEnvironmentProduction()
    {
        Verifactu::config('/dev/null', 'dummy', Verifactu::TYPE_CERTIFICATE, Verifactu::ENVIRONMENT_PRODUCTION);
        $config = (new \ReflectionClass(VerifactuService::class))->getStaticProperties()['config'];
        $environment = $config['environment'];
        $this->assertEquals('production', $environment);
    }
}
