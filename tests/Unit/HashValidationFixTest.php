<?php

namespace Tests\Unit;

use eseperio\verifactu\models\InvoiceSubmission;
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
 * Test case to verify that hash validation issue is fixed.
 * This reproduces the bug described in the issue where validation
 * fails because hash is required before it's generated.
 */
class HashValidationFixTest extends TestCase
{
    private function createValidInvoiceWithoutHash(): InvoiceSubmission
    {
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
        $invoice->simplifiedInvoice = YesNoType::NO; // Changed from selfEmployment 
        $invoice->invoiceWithoutRecipient = YesNoType::NO; // Changed from simplifiedRegime

        // Add tax breakdown
        $breakdown = new Breakdown();
        $detail = new BreakdownDetail();
        $detail->taxType = TaxType::IVA;
        $detail->taxRate = 21.00;
        $detail->taxableBase = 100.00; // Changed from taxBase
        $detail->taxAmount = 21.00;
        $detail->operationQualification = OperationQualificationType::SUBJECT_NO_EXEMPT_NO_REVERSE;
        $breakdown->addDetail($detail);
        $invoice->setBreakdown($breakdown);

        // Set chaining data
        $chaining = new Chaining();
        $chaining->firstRecord = YesNoType::YES; // Changed from firstInvoice
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

        return $invoice;
    }
    public function testInvoiceValidationWorksWithoutHashBeforeGeneration(): void
    {
        // Create a complete invoice without hash
        $invoice = $this->createValidInvoiceWithoutHash();

        // Test 1: validateExceptHash should pass (all fields except hash are valid)
        $validationResult = $invoice->validateExceptHash();
        $this->assertTrue($validationResult, 'validateExceptHash should pass when all fields except hash are valid');

        // Test 2: Regular validate should fail because hash is missing
        $fullValidationResult = $invoice->validate();
        $this->assertIsArray($fullValidationResult, 'Regular validate should fail when hash is missing');
        $this->assertArrayHasKey('hash', $fullValidationResult, 'Validation should specifically complain about missing hash');

        // Test 3: After setting hash, full validation should pass
        $invoice->hash = '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';
        $finalValidationResult = $invoice->validate();
        $this->assertTrue($finalValidationResult, 'Full validation should pass after hash is set');
    }

    public function testValidateExceptHashSkipsOnlyHashField(): void
    {
        // Create a minimal invoice with missing required fields
        $invoice = new InvoiceSubmission();
        
        // Only set a few fields, leaving most required fields empty
        $invoice->issuerName = 'Test Company';
        
        // validateExceptHash should still find other missing required fields
        $validationResult = $invoice->validateExceptHash();
        $this->assertIsArray($validationResult, 'validateExceptHash should find other missing required fields');
        
        // But it should NOT complain about missing hash
        $this->assertArrayNotHasKey('hash', $validationResult, 'validateExceptHash should not complain about missing hash');
    }
}
