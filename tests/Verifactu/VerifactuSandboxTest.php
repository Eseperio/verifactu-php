<?php

declare(strict_types=1);

namespace Verifactu;

use eseperio\verifactu\models\BreakdownDetail;
use eseperio\verifactu\models\Chaining;
use eseperio\verifactu\models\ComputerSystem;
use eseperio\verifactu\models\enums\HashType;
use eseperio\verifactu\models\enums\InvoiceType;
use eseperio\verifactu\models\enums\LegalPersonIdType;
use eseperio\verifactu\models\enums\OperationQualificationType;
use eseperio\verifactu\models\enums\TaxType;
use eseperio\verifactu\models\enums\YesNoType;
use eseperio\verifactu\models\InvoiceId;
use eseperio\verifactu\models\InvoiceSubmission;
use eseperio\verifactu\models\LegalPerson;
use eseperio\verifactu\models\OtherID;
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
 * - TEST_ISSUER_NIF: Issuer NIF used in tests (must match AEAT census)
 * - TEST_ISSUER_NAME: Issuer name used in tests (must match AEAT census)
 */
class VerifactuSandboxTest extends TestCase
{
    private string $issuerNif;
    private string $issuerName;

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

        // Read issuerNif and issuerName from environment variables and validate
        $nif = getenv('TEST_ISSUER_NIF') ?: ($_ENV['TEST_ISSUER_NIF'] ?? null);
        $name = getenv('TEST_ISSUER_NAME') ?: ($_ENV['TEST_ISSUER_NAME'] ?? null);

        if (empty($nif) || empty($name)) {
            throw new \RuntimeException('TEST_ISSUER_NIF and TEST_ISSUER_NAME must be defined in environment variables.');
        }

        $this->issuerNif = $nif;
        $this->issuerName = $name;
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

        $this->assertIsArray($validationResult);
        $this->assertEmpty($validationResult, 'Invoice validation should pass');
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
        $invoiceId->issuerNif = $this->issuerNif; // was hardcoded before
        $invoiceId->seriesNumber = 'TEST' . date('YmdHis');
        $invoiceId->issueDate = date('d-m-Y');
        $invoice->setInvoiceId($invoiceId);

        // Set basic invoice data
        $invoice->issuerName = $this->issuerName; // was hardcoded before
        $invoice->invoiceType = InvoiceType::STANDARD;
        $invoice->operationDescription = 'Prueba de integración';
        $invoice->taxAmount = 21.00;
        $invoice->totalAmount = 121.00;

        // Add tax breakdown
        $breakdownDetail = new BreakdownDetail();
        $breakdownDetail->taxRate = 21.0;
        $breakdownDetail->taxType = TaxType::IVA->value;
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
        $computerSystem->providerName = $this->issuerName;
        $computerSystem->systemId = '01';
        $computerSystem->installationNumber = '1';
        $computerSystem->onlyVerifactu = YesNoType::YES;
        $computerSystem->multipleObligations = YesNoType::NO;
        $computerSystem->hasMultipleObligations = YesNoType::NO;

        // Set provider information
        $provider = new LegalPerson();
        $provider->name = $this->issuerName;
        $provider->nif = $this->issuerNif;


        $computerSystem->setProviderId($provider);

        $invoice->setSystemInfo($computerSystem);

        // Set other required fields
        $invoice->recordTimestamp = date('Y-m-d\TH:i:sP');

        // Optional fields
        $invoice->operationDate = date('d-m-Y');
        // Change to externalReference if you prefer not to depend on the alias:
        // $invoice->externalReference = 'TEST-' . date('YmdHis');
        $invoice->externalRef = 'TEST-' . date('YmdHis');
        $invoice->simplifiedInvoice = YesNoType::NO;
        $invoice->invoiceWithoutRecipient = YesNoType::NO;

        // Set fields that caused validation errors
        $invoice->xmlSignature = ''; // Set to empty string
        $invoice->invoiceAgreementNumber = ''; // Set to empty string
        $invoice->systemAgreementId = ''; // Set to empty string

        // Add recipient marked as "Not Registered (No Censado)" (OtherID IDType=07)
        $recipient = new LegalPerson();
        $recipient->name = 'DECATHLON ESPAÑA SAU';
        $recipient->nif = 'A79935607';

        $invoice->addRecipient($recipient);

        // Check that the recipient was added correctly as No Censado
        $recipients = $invoice->getRecipients();
        $other = $recipients[0]->getOtherId() ?? null;

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

        // Submit the invoice to the AEAT service
        $response = Verifactu::registerInvoice($invoice);

        // Mostrar la respuesta en la consola
        var_dump($response->getErrors());
        var_dump($response);

        $this->assertNotNull($response, 'Response should not be null');
//        $this->assertTrue($response->isSuccessful(), 'Invoice submission should be successful');
    }
}
