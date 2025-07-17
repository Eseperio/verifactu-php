<?php

namespace Tests\Unit;

use eseperio\verifactu\services\HashGeneratorService;
use eseperio\verifactu\models\InvoiceSubmission;
use eseperio\verifactu\models\InvoiceCancellation;
use eseperio\verifactu\models\InvoiceId;
use eseperio\verifactu\models\Breakdown;
use eseperio\verifactu\models\BreakdownDetail;
use eseperio\verifactu\models\Chaining;
use eseperio\verifactu\models\ComputerSystem;
use eseperio\verifactu\models\LegalPerson;
use eseperio\verifactu\models\enums\InvoiceType;
use eseperio\verifactu\models\enums\TaxType;
use eseperio\verifactu\models\enums\YesNoType;
use eseperio\verifactu\models\enums\HashType;
use eseperio\verifactu\models\enums\OperationQualificationType;
use PHPUnit\Framework\TestCase;

/**
 * Test case to verify that HashGeneratorService properly uses public getters
 * instead of accessing protected properties directly.
 */
class HashGeneratorEncapsulationTest extends TestCase
{
    public function testHashGeneratorUsesPublicGettersForInvoiceSubmission(): void
    {
        // Create a complete invoice submission
        $invoice = new InvoiceSubmission();

        // Set invoice ID
        $invoiceId = new InvoiceId();
        $invoiceId->issuerNif = 'B12345678';
        $invoiceId->seriesNumber = 'FA2024/001';
        $invoiceId->issueDate = '2024-07-01';
        $invoice->setInvoiceId($invoiceId);

        // Set basic invoice data
        $invoice->issuerName = 'Empresa Ejemplo SL';
        $invoice->invoiceType = InvoiceType::STANDARD;
        $invoice->operationDescription = 'Venta de productos';
        $invoice->taxAmount = 21.00;
        $invoice->totalAmount = 121.00;
        $invoice->simplifiedInvoice = YesNoType::NO;
        $invoice->invoiceWithoutRecipient = YesNoType::NO;

        // Add tax breakdown
        $breakdown = new Breakdown();
        $detail = new BreakdownDetail();
        $detail->taxType = TaxType::IVA;
        $detail->taxRate = 21.00;
        $detail->taxableBase = 100.00;
        $detail->taxAmount = 21.00;
        $detail->operationQualification = OperationQualificationType::SUBJECT_NO_EXEMPT_NO_REVERSE;
        $breakdown->addDetail($detail);
        $invoice->setBreakdown($breakdown);

        // Set chaining data
        $chaining = new Chaining();
        $chaining->firstRecord = YesNoType::YES;
        $invoice->setChaining($chaining);

        // Set system information
        $computerSystem = new ComputerSystem();
        $computerSystem->systemName = 'ERP Company';
        $computerSystem->version = '1.0';
        $computerSystem->providerName = 'Software Provider';
        $computerSystem->systemId = '01';
        $computerSystem->installationNumber = '1';
        $computerSystem->onlyVerifactu = YesNoType::YES;
        $computerSystem->multipleObligations = YesNoType::NO;

        $provider = new LegalPerson();
        $provider->name = 'Software Provider SL';
        $provider->nif = 'B87654321';
        $computerSystem->setProviderId($provider);
        $invoice->setSystemInfo($computerSystem);

        // Set other required fields
        $invoice->recordTimestamp = '2024-07-01T12:00:00+02:00';
        $invoice->hashType = HashType::SHA_256;

        // Add recipients
        $recipientPerson = new LegalPerson();
        $recipientPerson->name = 'Cliente Ejemplo SL';
        $recipientPerson->nif = 'A98765432';
        $invoice->addRecipient($recipientPerson);

        // Test: Hash generation should work without errors (no fatal error about protected properties)
        $hash = HashGeneratorService::generate($invoice);

        // Verify that a hash was generated
        $this->assertIsString($hash, 'Hash should be generated as a string');
        $this->assertNotEmpty($hash, 'Hash should not be empty');
        
        // Verify it's a proper base64 encoded hash
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]+=*$/', $hash, 'Hash should be base64 encoded');
        
        // Verify the hash has a reasonable length for SHA-256 base64
        $decodedLength = strlen(base64_decode($hash));
        $this->assertEquals(32, $decodedLength, 'Decoded hash should be 32 bytes (SHA-256)');
    }

    public function testHashGeneratorUsesPublicGettersForInvoiceCancellation(): void
    {
        // Create a complete invoice cancellation
        $cancellation = new InvoiceCancellation();

        // Set invoice ID
        $invoiceId = new InvoiceId();
        $invoiceId->issuerNif = 'B12345678';
        $invoiceId->seriesNumber = 'FA2024/001';
        $invoiceId->issueDate = '2024-07-01';
        $cancellation->setInvoiceId($invoiceId);

        // Set chaining data
        $chaining = new Chaining();
        $chaining->firstRecord = YesNoType::YES;
        $cancellation->setChaining($chaining);

        // Set system information
        $computerSystem = new ComputerSystem();
        $computerSystem->systemName = 'ERP Company';
        $computerSystem->version = '1.0';
        $computerSystem->providerName = 'Software Provider';
        $computerSystem->systemId = '01';
        $computerSystem->installationNumber = '1';
        $computerSystem->onlyVerifactu = YesNoType::YES;
        $computerSystem->multipleObligations = YesNoType::NO;

        $provider = new LegalPerson();
        $provider->name = 'Software Provider SL';
        $provider->nif = 'B87654321';
        $computerSystem->setProviderId($provider);
        $cancellation->setSystemInfo($computerSystem);

        // Set other required fields
        $cancellation->recordTimestamp = '2024-07-01T12:00:00+02:00';
        $cancellation->hashType = HashType::SHA_256;

        // Test: Hash generation should work without errors (no fatal error about protected properties)
        $hash = HashGeneratorService::generate($cancellation);

        // Verify that a hash was generated
        $this->assertIsString($hash, 'Hash should be generated as a string');
        $this->assertNotEmpty($hash, 'Hash should not be empty');
        
        // Verify it's a proper base64 encoded hash
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]+=*$/', $hash, 'Hash should be base64 encoded');
        
        // Verify the hash has a reasonable length for SHA-256 base64
        $decodedLength = strlen(base64_decode($hash));
        $this->assertEquals(32, $decodedLength, 'Decoded hash should be 32 bytes (SHA-256)');
    }

    public function testCompleteWorkflowValidationThenHashGeneration(): void
    {
        // Create a complete invoice
        $invoice = new InvoiceSubmission();

        // Set invoice ID
        $invoiceId = new InvoiceId();
        $invoiceId->issuerNif = 'B12345678';
        $invoiceId->seriesNumber = 'FA2024/001';
        $invoiceId->issueDate = '2024-07-01';
        $invoice->setInvoiceId($invoiceId);

        // Set basic invoice data
        $invoice->issuerName = 'Empresa Ejemplo SL';
        $invoice->invoiceType = InvoiceType::STANDARD;
        $invoice->operationDescription = 'Venta de productos';
        $invoice->taxAmount = 21.00;
        $invoice->totalAmount = 121.00;
        $invoice->simplifiedInvoice = YesNoType::NO;
        $invoice->invoiceWithoutRecipient = YesNoType::NO;

        // Add tax breakdown
        $breakdown = new Breakdown();
        $detail = new BreakdownDetail();
        $detail->taxType = TaxType::IVA;
        $detail->taxRate = 21.00;
        $detail->taxableBase = 100.00;
        $detail->taxAmount = 21.00;
        $detail->operationQualification = OperationQualificationType::SUBJECT_NO_EXEMPT_NO_REVERSE;
        $breakdown->addDetail($detail);
        $invoice->setBreakdown($breakdown);

        // Set chaining data
        $chaining = new Chaining();
        $chaining->firstRecord = YesNoType::YES;
        $invoice->setChaining($chaining);

        // Set system information
        $computerSystem = new ComputerSystem();
        $computerSystem->systemName = 'ERP Company';
        $computerSystem->version = '1.0';
        $computerSystem->providerName = 'Software Provider';
        $computerSystem->systemId = '01';
        $computerSystem->installationNumber = '1';
        $computerSystem->onlyVerifactu = YesNoType::YES;
        $computerSystem->multipleObligations = YesNoType::NO;

        $provider = new LegalPerson();
        $provider->name = 'Software Provider SL';
        $provider->nif = 'B87654321';
        $computerSystem->setProviderId($provider);
        $invoice->setSystemInfo($computerSystem);

        // Set other required fields
        $invoice->recordTimestamp = '2024-07-01T12:00:00+02:00';
        $invoice->hashType = HashType::SHA_256;

        // Add recipients
        $recipientPerson = new LegalPerson();
        $recipientPerson->name = 'Cliente Ejemplo SL';
        $recipientPerson->nif = 'A98765432';
        $invoice->addRecipient($recipientPerson);

        // Step 1: Validate without hash (should pass)
        $validationResult = $invoice->validateExceptHash();
        $this->assertTrue($validationResult, 'validateExceptHash should pass when all fields except hash are valid');

        // Step 2: Generate hash (should work without errors)
        $hash = HashGeneratorService::generate($invoice);
        $this->assertNotEmpty($hash, 'Hash should be generated successfully');

        // Step 3: Set the hash on the invoice
        $invoice->hash = $hash;

        // Step 4: Full validation should now pass
        $finalValidationResult = $invoice->validate();
        $this->assertTrue($finalValidationResult, 'Full validation should pass after hash is set');
    }
}
