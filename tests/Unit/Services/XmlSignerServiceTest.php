<?php

declare(strict_types=1);

namespace eseperio\verifactu\tests\Unit;

use eseperio\verifactu\services\XmlSignerService;
use eseperio\verifactu\utils\EnvLoader;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the XmlSignerService class.
 */
class XmlSignerServiceTest extends TestCase
{
    private $sampleXml = '<SampleData><Item>Test Data</Item></SampleData>';
    
    /**
     * Test that we can sign XML with a certificate.
     * This test will be skipped if no certificate is available.
     */
    public function testSignXml(): void
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
        
        // Sign the XML
        $signedXml = XmlSignerService::signXml($this->sampleXml, $certPath, $certPassword);
        
        // Verify that the signed XML contains signature elements
        $this->assertStringContainsString('<ds:Signature', $signedXml, 'Signed XML should contain a signature element');
        $this->assertStringContainsString('<ds:SignatureValue', $signedXml, 'Signed XML should contain a signature value');
        $this->assertStringContainsString('<ds:Reference', $signedXml, 'Signed XML should contain a reference');
        
        // Verify that the original content is preserved (root content remains)
        $this->assertStringContainsString('<Item>Test Data</Item>', $signedXml, 
            'Signed XML should contain the original content');
    }
    
    /**
     * Test that an invalid certificate path throws an exception.
     */
    public function testInvalidCertificatePath(): void
    {
        $this->expectException(\RuntimeException::class);
        XmlSignerService::signXml($this->sampleXml, '/nonexistent/path/to/cert.p12', 'password');
    }
    
    /**
     * Test that invalid XML throws an exception.
     */
    public function testInvalidXml(): void
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
        
        $invalidXml = '<InvalidXml><UnclosedTag>';
        
        $this->expectException(\Exception::class);
        XmlSignerService::signXml($invalidXml, $certPath, $certPassword);
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
                'Skipping test that requires a real certificate. ' .
                'In a production environment, make sure to set VERIFACTU_CERT_PATH ' .
                'environment variable to a valid certificate.'
            );
        }
        
        $this->expectException(\Exception::class);
        XmlSignerService::signXml($this->sampleXml, $certPath, 'wrong_password');
    }
}
