<?php

declare(strict_types=1);

namespace eseperio\verifactu\tests\Unit\Examples;

use eseperio\verifactu\models\Breakdown;
use eseperio\verifactu\models\BreakdownDetail;
use eseperio\verifactu\models\Chaining;
use eseperio\verifactu\models\ComputerSystem;
use eseperio\verifactu\models\enums\GeneratorType;
use eseperio\verifactu\models\enums\HashType;
use eseperio\verifactu\models\enums\InvoiceType;
use eseperio\verifactu\models\enums\OperationQualificationType;
use eseperio\verifactu\models\enums\YesNoType;
use eseperio\verifactu\models\InvoiceCancellation;
use eseperio\verifactu\models\InvoiceId;
use eseperio\verifactu\models\InvoiceSubmission;
use eseperio\verifactu\models\LegalPerson;
use PHPUnit\Framework\TestCase;

/**
 * Test cases based on README.md examples to ensure they work correctly.
 */
class ReadmeExamplesTest extends TestCase
{
    /**
     * Test the invoice registration example from README.md.
     */
    public function testInvoiceRegistrationExample(): void
    {
        // Create invoice exactly as shown in README example
        $invoice = new InvoiceSubmission();

        // Set invoice ID (using object-oriented approach)
        $invoiceId = new InvoiceId();
        $invoiceId->issuerNif = 'B12345678';
        $invoiceId->seriesNumber = 'FA2024/001';
        $invoiceId->issueDate = '01-07-2024';
        $invoice->setInvoiceId($invoiceId);

        // Set basic invoice data
        $invoice->issuerName = 'Empresa Ejemplo SL';
        $invoice->invoiceType = InvoiceType::STANDARD; // Using enum instead of string
        $invoice->operationDescription = 'Venta de productos';
        $invoice->taxAmount = 21.00; // Calculate total tax amount
        $invoice->totalAmount = 121.00; // Total invoice amount

        // Add tax breakdown (using object-oriented approach)
        $breakdownDetail = new BreakdownDetail();
        $breakdownDetail->taxRate = 21.0;
        $breakdownDetail->taxableBase = 100.00;
        $breakdownDetail->taxAmount = 21.00;
        $breakdownDetail->operationQualification = OperationQualificationType::SUBJECT_NO_EXEMPT_NO_REVERSE;
        $invoice->addBreakdownDetail($breakdownDetail);

        // Set chaining data (using object-oriented approach)
        $chaining = new Chaining();
        $chaining->setAsFirstRecord(); // For the first invoice in a chain
        $invoice->setChaining($chaining);

        // Set system information (using object-oriented approach)
        $computerSystem = new ComputerSystem();
        $computerSystem->systemName = 'ERP Company';
        $computerSystem->version = '1.0';
        $computerSystem->providerName = 'Software Provider';
        $computerSystem->systemId = '01';
        $computerSystem->installationNumber = '1';
        $computerSystem->onlyVerifactu = YesNoType::YES;
        $computerSystem->multipleObligations = YesNoType::NO;

        // Set provider information
        $provider = new LegalPerson();
        $provider->name = 'Software Provider SL';
        $provider->nif = 'B87654321';
        $computerSystem->setProviderId($provider);

        $invoice->setSystemInfo($computerSystem);

        // Set other required fields
        $invoice->recordTimestamp = '2024-07-01T12:00:00+02:00'; // Date and time with timezone
        $invoice->hashType = HashType::SHA_256;
        $invoice->hash = '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'; // Calculated hash

        // Optional fields
        $invoice->operationDate = '01-07-2024'; // Operation date
        $invoice->externalRef = 'REF123'; // External reference
        $invoice->simplifiedInvoice = YesNoType::NO; // Is not a simplified invoice
        $invoice->invoiceWithoutRecipient = YesNoType::NO; // Has identified recipient

        // Needed fields to pass validation (data format as in sandbox)
        $invoice->xmlSignature = '';
        $invoice->invoiceAgreementNumber = '';
        $invoice->systemAgreementId = '';

        // Add recipients (using object-oriented approach)
        $recipient = new LegalPerson();
        $recipient->name = 'Cliente Ejemplo SL';
        $recipient->nif = '12345678Z';
        $invoice->addRecipient($recipient);

        // Validate the invoice before submission
        $validationResult = $invoice->validate();

        // If validation fails, output the errors for debugging
        if (!empty($validationResult)) {
            $this->fail('Invoice validation failed: ' . print_r($validationResult, true));
        }

        // Assertions to verify the example works
        $this->assertIsArray($validationResult);
        $this->assertEmpty($validationResult, 'Invoice validation should pass');
        $this->assertInstanceOf(InvoiceId::class, $invoice->getInvoiceId());
        $this->assertEquals('B12345678', $invoice->getInvoiceId()->issuerNif);
        $this->assertEquals('FA2024/001', $invoice->getInvoiceId()->seriesNumber);
        $this->assertEquals('01-07-2024', $invoice->getInvoiceId()->issueDate);
        $this->assertEquals('Empresa Ejemplo SL', $invoice->issuerName);
        $this->assertEquals(InvoiceType::STANDARD, $invoice->invoiceType);
        $this->assertEquals('Venta de productos', $invoice->operationDescription);
        $this->assertEquals(21.00, $invoice->taxAmount);
        $this->assertEquals(121.00, $invoice->totalAmount);
    }

    /**
     * Test the invoice cancellation example from README.md.
     */
    public function testInvoiceCancellationExample(): void
    {
        // Create cancellation exactly as shown in README example
        $cancellation = new InvoiceCancellation();

        // Set invoice ID (using object-oriented approach)
        $invoiceId = new InvoiceId();
        $invoiceId->issuerNif = 'B12345678';
        $invoiceId->seriesNumber = 'FA2024/001';
        $invoiceId->issueDate = '01-07-2024';
        $cancellation->setInvoiceId($invoiceId);

        // Set chaining data (using object-oriented approach)
        $chaining = new Chaining();
        // For subsequent invoices in a chain:
        $chaining->setPreviousInvoice([
            'seriesNumber' => 'FA2024/000',
            'issuerNif' => 'B12345678',
            'issueDate' => '30-06-2024',
            'hash' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
        ]);
        $cancellation->setChaining($chaining);

        // Set system information (using object-oriented approach)
        $computerSystem = new ComputerSystem();
        $computerSystem->systemName = 'ERP Company';
        $computerSystem->version = '1.0';
        $computerSystem->providerName = 'Software Provider';
        $computerSystem->systemId = '01';
        $computerSystem->installationNumber = '1';
        $computerSystem->onlyVerifactu = YesNoType::YES;
        $computerSystem->multipleObligations = YesNoType::NO;

        // Set provider information
        $provider = new LegalPerson();
        $provider->name = 'Software Provider SL';
        $provider->nif = 'B87654321';
        $computerSystem->setProviderId($provider);

        $cancellation->setSystemInfo($computerSystem);

        // Set other required fields
        $cancellation->recordTimestamp = '2024-07-01T12:00:00+02:00'; // Date and time with timezone
        $cancellation->hashType = HashType::SHA_256;
        $cancellation->hash = '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'; // Calculated hash

        // Needed fields to pass validation (data format as in sandbox)
        $cancellation->xmlSignature = '';

        // Optional fields
        $cancellation->noPreviousRecord = YesNoType::NO; // Is not a cancellation without previous record
        $cancellation->previousRejection = YesNoType::NO; // Is not a cancellation due to previous rejection
        $cancellation->generator = GeneratorType::ISSUER; // Generate by issuer
        $cancellation->externalRef = 'REF-CANCEL-123'; // External reference

        // Validate the cancellation before submission
        $validationResult = $cancellation->validate();

        // If validation fails, output the errors for debugging
        if (!empty($validationResult)) {
            $this->fail('Cancellation validation failed: ' . print_r($validationResult, true));
        }

        // Assertions to verify the example works
        $this->assertIsArray($validationResult);
        $this->assertEmpty($validationResult, 'Cancellation validation should pass');
        $this->assertInstanceOf(InvoiceId::class, $cancellation->getInvoiceId());
        $this->assertEquals('B12345678', $cancellation->getInvoiceId()->issuerNif);
        $this->assertEquals('FA2024/001', $cancellation->getInvoiceId()->seriesNumber);
        $this->assertEquals('01-07-2024', $cancellation->getInvoiceId()->issueDate);
        $this->assertEquals(YesNoType::NO, $cancellation->noPreviousRecord);
        $this->assertEquals(YesNoType::NO, $cancellation->previousRejection);
        $this->assertEquals(GeneratorType::ISSUER, $cancellation->generator);
        $this->assertEquals('REF-CANCEL-123', $cancellation->externalRef);
    }
}
