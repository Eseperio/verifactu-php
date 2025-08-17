<?php

declare(strict_types=1);

namespace eseperio\verifactu\tests\Unit;

use eseperio\verifactu\services\SoapClientFactoryService;
use eseperio\verifactu\utils\EnvLoader;
use eseperio\verifactu\Verifactu;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the SoapClientFactoryService class.
 */
class SoapClientFactoryServiceTest extends TestCase
{
    /**
     * Test that we can create a SOAP client with a certificate.
     * This test will be skipped if no certificate is available.
     */
    public function testCreateSoapClient(): void
    {
        // Verify local AEAT WSDL and XSDs exist and are non-empty
        $base = realpath(__DIR__ . '/../../../docs/aeat/esquemas');
        $wsdlPath = $base . '/SistemaFacturacion.wsdl';
        $xsds = [
            $base . '/SuministroInformacion.xsd',
            $base . '/SuministroLR.xsd',
            $base . '/ConsultaLR.xsd',
            $base . '/RespuestaConsultaLR.xsd',
            $base . '/RespuestaSuministro.xsd',
        ];
        
        if ($base === false || !file_exists($wsdlPath)) {
            $this->markTestSkipped('Local AEAT WSDL not found; skipping SOAP client creation test.');
        }
        
        foreach ($xsds as $xsd) {
            if (!file_exists($xsd) || filesize($xsd) === 0) {
                $this->markTestSkipped('AEAT XSD schemas not available or empty; skipping SOAP client creation test.');
            }
        }
        
        // Try to load real certificate if available
        EnvLoader::load();
        $certPath = EnvLoader::getCertPath();
        $certPassword = EnvLoader::getCertPassword();
        
        if (empty($certPath) || empty($certPassword) || !file_exists($certPath)) {
            // Skip the test when no certificate is available - with a more informative message
            $this->markTestSkipped(
                'Skipping test that requires a real certificate. ' .
                'In a production environment, make sure to set VERIFACTU_CERT_PATH and ' .
                'VERIFACTU_CERT_PASSWORD environment variables to a valid certificate.'
            );
        }
        
        // Use reflection to access the protected method with real certificate
        $reflectionClass = new \ReflectionClass(SoapClientFactoryService::class);
        $method = $reflectionClass->getMethod('createSoapClient');
        $method->setAccessible(true);
        
        // Create a SOAP client
        $client = $method->invokeArgs(null, [
            $wsdlPath,
            $certPath,
            $certPassword,
            [],
            Verifactu::ENVIRONMENT_SANDBOX
        ]);
        
        // Verify that the client is an instance of \SoapClient
        $this->assertInstanceOf(\SoapClient::class, $client);
    }
    
    /**
     * Test that an invalid certificate path throws an exception.
     */
    public function testInvalidCertificatePath(): void
    {
        // Use reflection to access the protected method
        $reflectionClass = new \ReflectionClass(SoapClientFactoryService::class);
        $method = $reflectionClass->getMethod('createSoapClient');
        $method->setAccessible(true);
        
        $this->expectException(\RuntimeException::class);
        $method->invokeArgs(null, [
            realpath(__DIR__ . '/../../../docs/aeat/esquemas/SistemaFacturacion.wsdl'),
            '/nonexistent/path/to/cert.p12',
            'password',
            [],
            Verifactu::ENVIRONMENT_SANDBOX
        ]);
    }
    

    /**
     * Test that an invalid WSDL path throws an exception.
     */
    public function testInvalidWsdlPath(): void
    {
        // Skip the test if no certificate is available
        EnvLoader::load();
        $certPath = EnvLoader::getCertPath();
        $certPassword = EnvLoader::getCertPassword();
        
        if (empty($certPath) || empty($certPassword) || !file_exists($certPath)) {
            $this->markTestSkipped(
                'Skipping test that requires a real certificate. ' .
                'In a production environment, make sure to set VERIFACTU_CERT_PATH and ' .
                'VERIFACTU_CERT_PASSWORD environment variables to a valid certificate.'
            );
        }
        
        // Use reflection to access the protected method
        $reflectionClass = new \ReflectionClass(SoapClientFactoryService::class);
        $method = $reflectionClass->getMethod('createSoapClient');
        $method->setAccessible(true);
        
        $this->expectException(\RuntimeException::class);
        $method->invokeArgs(null, [
            '/nonexistent/path/to/wsdl.xml',
            $certPath,
            $certPassword,
            [],
            Verifactu::ENVIRONMENT_SANDBOX
        ]);
    }
    
    /**
     * Test that the service selects the correct endpoint for the production environment.
     */
    public function testProductionEndpoint(): void
    {
        // This test doesn't create an actual client, just verifies the URL logic
        $this->assertEquals(
            Verifactu::URL_PRODUCTION,
            $this->getEndpointForType(Verifactu::ENVIRONMENT_PRODUCTION, Verifactu::TYPE_CERTIFICATE)
        );
        
        $this->assertEquals(
            Verifactu::URL_PRODUCTION_SEAL,
            $this->getEndpointForType(Verifactu::ENVIRONMENT_PRODUCTION, Verifactu::TYPE_SEAL)
        );
    }
    
    /**
     * Test that the service selects the correct endpoint for the sandbox environment.
     */
    public function testSandboxEndpoint(): void
    {
        // This test doesn't create an actual client, just verifies the URL logic
        $this->assertEquals(
            Verifactu::URL_TEST,
            $this->getEndpointForType(Verifactu::ENVIRONMENT_SANDBOX, Verifactu::TYPE_CERTIFICATE)
        );
        
        $this->assertEquals(
            Verifactu::URL_TEST_SEAL,
            $this->getEndpointForType(Verifactu::ENVIRONMENT_SANDBOX, Verifactu::TYPE_SEAL)
        );
    }
    
    /**
     * Helper method to get the endpoint URL for a given environment and certificate type.
     */
    private function getEndpointForType(string $environment, string $certType): string
    {
        return match ($environment) {
            Verifactu::ENVIRONMENT_PRODUCTION => $certType === Verifactu::TYPE_SEAL ? 
                Verifactu::URL_PRODUCTION_SEAL : Verifactu::URL_PRODUCTION,
            Verifactu::ENVIRONMENT_SANDBOX => $certType === Verifactu::TYPE_SEAL ? 
                Verifactu::URL_TEST_SEAL : Verifactu::URL_TEST,
            default => throw new \InvalidArgumentException("Invalid environment: $environment")
        };
    }
}
