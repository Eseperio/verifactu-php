<?php

declare(strict_types=1);

namespace eseperio\verifactu\tests\Unit;

use BaconQrCode\Writer;
use PHPUnit\Framework\MockObject\MockObject;
use eseperio\verifactu\models\InvoiceId;
use eseperio\verifactu\models\InvoiceRecord;
use eseperio\verifactu\services\QrGeneratorService;
use PHPUnit\Framework\TestCase;

class QrGeneratorServiceTest extends TestCase
{
    /**
     * Test that the QrGeneratorService::buildQrContent method builds the correct URL.
     */
    public function testBuildQrContent(): void
    {
        // Create a mock InvoiceRecord
        $mockInvoiceRecord = $this->getMockBuilder(InvoiceRecord::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getInvoiceId'])
            ->getMockForAbstractClass();

        // Create an InvoiceId
        $invoiceId = new InvoiceId();
        $invoiceId->issuerNif = 'B12345678';
        $invoiceId->seriesNumber = 'FACT-001';
        $invoiceId->issueDate = '2023-01-01';

        // Set up the mock InvoiceRecord
        $mockInvoiceRecord->method('getInvoiceId')->willReturn($invoiceId);
        $mockInvoiceRecord->hash = 'abcdef1234567890';

        // Use reflection to access the protected method
        $reflectionClass = new \ReflectionClass(QrGeneratorService::class);
        $method = $reflectionClass->getMethod('buildQrContent');
        $method->setAccessible(true);

        // Call the method
        $baseUrl = 'https://example.com/verify';
        $result = $method->invoke(null, $mockInvoiceRecord, $baseUrl);

        // Verify the result
        $expectedParams = http_build_query([
            'nif' => 'B12345678',
            'num' => 'FACT-001',
            'fecha' => '2023-01-01',
            'huella' => 'abcdef1234567890',
        ]);
        $expected = $baseUrl . '?' . $expectedParams;

        $this->assertEquals($expected, $result);
    }

    /**
     * Test that the QrGeneratorService::getFileExtension method returns the correct extension.
     */
    public function testGetFileExtension(): void
    {
        // Use reflection to access the protected method
        $reflectionClass = new \ReflectionClass(QrGeneratorService::class);
        $method = $reflectionClass->getMethod('getFileExtension');
        $method->setAccessible(true);

        // Test GD renderer
        $result = $method->invoke(null, QrGeneratorService::RENDERER_GD);
        $this->assertEquals('.png', $result);

        // Test Imagick renderer
        $result = $method->invoke(null, QrGeneratorService::RENDERER_IMAGICK);
        $this->assertEquals('.png', $result);

        // Test SVG renderer
        $result = $method->invoke(null, QrGeneratorService::RENDERER_SVG);
        $this->assertEquals('.svg', $result);

        // Test default case
        $result = $method->invoke(null, 'unknown');
        $this->assertEquals('.png', $result);
    }

    /**
     * Test that the QrGeneratorService::createWriter method creates the correct writer.
     */
    public function testCreateWriter(): void
    {
        // Use reflection to access the protected method
        $reflectionClass = new \ReflectionClass(QrGeneratorService::class);
        $method = $reflectionClass->getMethod('createWriter');
        $method->setAccessible(true);

        // Test GD renderer
        $writer = $method->invoke(null, QrGeneratorService::RENDERER_GD, 300);
        $this->assertInstanceOf(Writer::class, $writer);

        // Test Imagick renderer
        $writer = $method->invoke(null, QrGeneratorService::RENDERER_IMAGICK, 300);
        $this->assertInstanceOf(Writer::class, $writer);

        // Test SVG renderer
        $writer = $method->invoke(null, QrGeneratorService::RENDERER_SVG, 300);
        $this->assertInstanceOf(Writer::class, $writer);

        // Test invalid renderer
        $this->expectException(\RuntimeException::class);
        $method->invoke(null, 'invalid', 300);
    }

    /**
     * Test the main generateQr method with default parameters.
     */
    public function testGenerateQrWithDefaultParameters(): void
    {
        // Create a mock InvoiceRecord
        $mockInvoiceRecord = $this->createMockInvoiceRecord();

        // Call the method with default parameters
        $baseUrl = 'https://example.com/verify';
        $result = QrGeneratorService::generateQr($mockInvoiceRecord, $baseUrl);

        // Verify the result is a string (binary data)
        $this->assertIsString($result);
        // Verify it's not empty
        $this->assertNotEmpty($result);
    }

    /**
     * Test the generateQr method with file destination.
     */
    public function testGenerateQrWithFileDestination(): void
    {
        // Create a mock InvoiceRecord
        $mockInvoiceRecord = $this->createMockInvoiceRecord();

        // Call the method with file destination
        $baseUrl = 'https://example.com/verify';
        $result = QrGeneratorService::generateQr(
            $mockInvoiceRecord,
            $baseUrl,
            QrGeneratorService::DESTINATION_FILE
        );

        // Verify the result is a string (file path)
        $this->assertIsString($result);
        // Verify it's a file path
        $this->assertStringContainsString('/qr_', $result);
        $this->assertStringEndsWith('.png', $result);
        // Verify the file exists
        $this->assertFileExists($result);

        // Clean up
        if (file_exists($result)) {
            unlink($result);
        }
    }

    /**
     * Test the generateQr method with SVG renderer.
     */
    public function testGenerateQrWithSvgRenderer(): void
    {
        // Create a mock InvoiceRecord
        $mockInvoiceRecord = $this->createMockInvoiceRecord();

        // Call the method with SVG renderer
        $baseUrl = 'https://example.com/verify';
        $result = QrGeneratorService::generateQr(
            $mockInvoiceRecord,
            $baseUrl,
            QrGeneratorService::DESTINATION_STRING,
            300,
            QrGeneratorService::RENDERER_SVG
        );

        // Verify the result is a string (SVG data)
        $this->assertIsString($result);
        // Verify it contains SVG tags
        $this->assertStringContainsString('<svg', $result);
        $this->assertStringContainsString('</svg>', $result);
    }

    /**
     * Test the generateQr method with different resolutions.
     */
    public function testGenerateQrWithDifferentResolutions(): void
    {
        // Create a mock InvoiceRecord
        $mockInvoiceRecord = $this->createMockInvoiceRecord();
        $baseUrl = 'https://example.com/verify';

        // Generate QR with small resolution
        $smallQr = QrGeneratorService::generateQr(
            $mockInvoiceRecord,
            $baseUrl,
            QrGeneratorService::DESTINATION_STRING,
            100
        );

        // Generate QR with large resolution
        $largeQr = QrGeneratorService::generateQr(
            $mockInvoiceRecord,
            $baseUrl,
            QrGeneratorService::DESTINATION_STRING,
            300
        );

        // Verify both are strings
        $this->assertIsString($smallQr);
        $this->assertIsString($largeQr);

        // The larger resolution should produce a larger file
        $this->assertGreaterThan(strlen($smallQr), strlen($largeQr));
    }

    /**
     * Helper method to create a mock InvoiceRecord.
     */
    private function createMockInvoiceRecord(): MockObject
    {
        $mockInvoiceRecord = $this->getMockBuilder(InvoiceRecord::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getInvoiceId'])
            ->getMockForAbstractClass();

        $invoiceId = new InvoiceId();
        $invoiceId->issuerNif = 'B12345678';
        $invoiceId->seriesNumber = 'FACT-001';
        $invoiceId->issueDate = '2023-01-01';

        $mockInvoiceRecord->method('getInvoiceId')->willReturn($invoiceId);
        $mockInvoiceRecord->hash = 'abcdef1234567890';

        return $mockInvoiceRecord;
    }
}
