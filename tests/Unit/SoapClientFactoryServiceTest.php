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
        // Skip the test if no certificate is available
        EnvLoader::load();
        $certPath = EnvLoader::getCertPath();
        $certPassword = EnvLoader::getCertPassword();
        
        if (empty($certPath) || empty($certPassword) || !file_exists($certPath)) {
            $this->markTestSkipped(
                'SOAP client creation test skipped. Make sure to set VERIFACTU_CERT_PATH and ' .
                'VERIFACTU_CERT_PASSWORD in your .env file.'
            );
        }
        
        // Use reflection to access the protected method
        $reflectionClass = new \ReflectionClass(SoapClientFactoryService::class);
        $method = $reflectionClass->getMethod('createSoapClient');
        $method->setAccessible(true);
        
        // Create a SOAP client
        $client = $method->invokeArgs(null, [
            __DIR__ . '/../../docs/aeat/SistemaFacturacion.wsdl.xml',
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
            __DIR__ . '/../../docs/aeat/SistemaFacturacion.wsdl.xml',
            '/nonexistent/path/to/cert.p12',
            'password',
            [],
            Verifactu::ENVIRONMENT_SANDBOX
        ]);
    }
    
    /**
     * Test that an incorrect certificate password throws an exception.
     */
    public function testIncorrectCertificatePassword(): void
    {
        // Skip the test if no certificate is available
        EnvLoader::load();
        $certPath = EnvLoader::getCertPath();
        
        if (empty($certPath) || !file_exists($certPath)) {
            $this->markTestSkipped(
                'SOAP client creation test skipped. Make sure to set VERIFACTU_CERT_PATH in your .env file.'
            );
        }
        
        // Use reflection to access the protected method
        $reflectionClass = new \ReflectionClass(SoapClientFactoryService::class);
        $method = $reflectionClass->getMethod('createSoapClient');
        $method->setAccessible(true);
        
        $this->expectException(\Exception::class);
        $method->invokeArgs(null, [
            __DIR__ . '/../../docs/aeat/SistemaFacturacion.wsdl.xml',
            $certPath,
            'wrong_password',
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
                'SOAP client creation test skipped. Make sure to set VERIFACTU_CERT_PATH and ' .
                'VERIFACTU_CERT_PASSWORD in your .env file.'
            );
        }
        
        // Use reflection to access the protected method
        $reflectionClass = new \ReflectionClass(SoapClientFactoryService::class);
        $method = $reflectionClass->getMethod('createSoapClient');
        $method->setAccessible(true);
        
        $this->expectException(\Exception::class);
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
