<?php

declare(strict_types=1);

namespace eseperio\verifactu\tests\Integration;

use eseperio\verifactu\models\Breakdown;
use eseperio\verifactu\models\BreakdownDetail;
use eseperio\verifactu\models\Chaining;
use eseperio\verifactu\models\ComputerSystem;
use eseperio\verifactu\models\enums\HashType;
use eseperio\verifactu\models\enums\InvoiceType;
use eseperio\verifactu\models\enums\OperationQualificationType;
use eseperio\verifactu\models\enums\YesNoType;
use eseperio\verifactu\models\InvoiceId;
use eseperio\verifactu\models\InvoiceSubmission;
use eseperio\verifactu\models\LegalPerson;
use eseperio\verifactu\services\HashGeneratorService;
use eseperio\verifactu\utils\EnvLoader;
use eseperio\verifactu\Verifactu;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Verifactu in the sandbox environment.
 * 
 * These tests require a valid certificate to run.
 * Make sure to set the following environment variables in your .env file:
 * - VERIFACTU_CERT_PATH: Path to your certificate file
 * - VERIFACTU_CERT_PASSWORD: Password for your certificate
 * - VERIFACTU_CERT_TYPE: Certificate type (certificate or seal)
 * - VERIFACTU_ENVIRONMENT: Environment (sandbox or production)
 */
class VerifactuSandboxTest extends TestCase
{
    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip tests if sandbox configuration is not available
        if (!EnvLoader::hasSandboxConfig()) {
            $this->markTestSkipped(
                'Sandbox tests skipped. Make sure to set VERIFACTU_CERT_PATH, ' .
                'VERIFACTU_CERT_PASSWORD, VERIFACTU_CERT_TYPE, and VERIFACTU_ENVIRONMENT in your .env file.'
            );
        }
        
        // Configure Verifactu with the environment variables
        Verifactu::config(
            EnvLoader::getCertPath(),
            EnvLoader::getCertPassword(),
            EnvLoader::getCertType(),
            EnvLoader::getEnvironment()
        );
    }
    
    /**
     * Test creating and validating an invoice.
     * 
     * This test only validates the invoice locally, without submitting it to the AEAT service.
     */
    public function testCreateAndValidateInvoice(): void
    {
        $invoice = $this->createTestInvoice();
        
        // Validate the invoice
        $validationResult = $invoice->validate();
        
        $this->assertTrue($validationResult, 'Invoice validation should pass');
    }
    
    /**
     * Test generating a QR code for an invoice.
     * 
     * This test generates a QR code for an invoice without submitting it to the AEAT service.
     */
    public function testGenerateInvoiceQr(): void
    {
        $invoice = $this->createTestInvoice();
        
        // Generate a QR code
        $qrCode = Verifactu::generateInvoiceQr($invoice);
        
        $this->assertNotEmpty($qrCode, 'QR code should not be empty');
        $this->assertIsString($qrCode, 'QR code should be a string');
    }
    
    /**
     * Helper method to create a test invoice with realistic data.
     * 
     * @return InvoiceSubmission
     */
    private function createTestInvoice(): InvoiceSubmission
    {
        $invoice = new InvoiceSubmission();
        
        // Set invoice ID
        $invoiceId = new InvoiceId();
        $invoiceId->issuerNif = 'B12345678';
        $invoiceId->seriesNumber = 'TEST' . date('YmdHis');
        $invoiceId->issueDate = date('d-m-Y');
        $invoice->setInvoiceId($invoiceId);
        
        // Set basic invoice data
        $invoice->issuerName = 'Empresa Test SL';
        $invoice->invoiceType = InvoiceType::STANDARD;
        $invoice->operationDescription = 'Prueba de integración';
        $invoice->taxAmount = 21.00;
        $invoice->totalAmount = 121.00;
        
        // Add tax breakdown
        $breakdownDetail = new BreakdownDetail();
        $breakdownDetail->taxRate = 21.0;
        $breakdownDetail->taxableBase = 100.00;
        $breakdownDetail->taxAmount = 21.00;
        $breakdownDetail->operationQualification = OperationQualificationType::SUBJECT_NO_EXEMPT_NO_REVERSE;
        $invoice->addBreakdownDetail($breakdownDetail);
        
        // Set chaining data
        $chaining = new Chaining();
        $chaining->setAsFirstRecord();
        $invoice->setChaining($chaining);
        
        // Set system information
        $computerSystem = new ComputerSystem();
        $computerSystem->systemName = 'ERP Test';
        $computerSystem->version = '1.0';
        $computerSystem->providerName = 'Test Provider';
        $computerSystem->systemId = '01';
        $computerSystem->installationNumber = '1';
        $computerSystem->onlyVerifactu = YesNoType::YES;
        $computerSystem->multipleObligations = YesNoType::NO;
        
        // Set provider information
        $provider = new LegalPerson();
        $provider->name = 'Test Provider SL';
        $provider->nif = 'B87654321';
        $computerSystem->setProviderId($provider);
        
        $invoice->setSystemInfo($computerSystem);
        
        // Set other required fields
        $invoice->recordTimestamp = date('Y-m-d\TH:i:sP');
        $invoice->hashType = HashType::SHA_256;
        
        // Generate the hash
        $invoice->hash = HashGeneratorService::generate($invoice);
        
        // Optional fields
        $invoice->operationDate = date('Y-m-d');
        // Cambia a externalReference si prefieres no depender del alias:
        // $invoice->externalReference = 'TEST-' . date('YmdHis');
        $invoice->externalRef = 'TEST-' . date('YmdHis');
        $invoice->simplifiedInvoice = YesNoType::NO;
        $invoice->invoiceWithoutRecipient = YesNoType::NO;
        
        // Set fields that caused validation errors
        $invoice->xmlSignature = ''; // Set to empty string
        $invoice->invoiceAgreementNumber = ''; // Set to empty string
        $invoice->systemAgreementId = ''; // Set to empty string
        
        // Add recipients with proper structure
        $recipient = new LegalPerson();
        $recipient->name = 'Cliente Test SL';
        $recipient->nif = '12345678Z'; // Make sure NIF is set properly
        $invoice->addRecipient($recipient);
        
        // Check that the recipient was added correctly
        $recipients = $invoice->getRecipients();
        if (empty($recipients) || !isset($recipients[0]->nif) || $recipients[0]->nif !== '12345678Z') {
            throw new \RuntimeException('Failed to add recipient with proper NIF');
        }

        return $invoice;
    }
    
    /**
     * Optional: Test submitting an invoice to the AEAT sandbox service.
     * 
     * This test is commented out because it would make real API calls to the AEAT service.
     * Uncomment and run manually when needed.
     */
    public function testSubmitInvoiceToSandbox(): void
    {
        $invoice = $this->createTestInvoice();
        
        // Si no hay certificado real o no puede leerse (p. ej. OpenSSL legacy), saltar el test
        try {
            $response = Verifactu::registerInvoice($invoice);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Unable to read PFX/P12 certificate')) {
                $this->markTestSkipped('Sandbox submit skipped: certificate not readable in this environment.');
            }
            throw $e; // re-lanzar otras RuntimeException
        }
        
//        $this->assertNotNull($response, 'Response should not be null');
//        $this->assertTrue($response->isSuccessful(), 'Invoice submission should be successful');
    }
}
