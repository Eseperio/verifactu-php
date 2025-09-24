<?php

namespace eseperio\verifactu\tests\Integration;

use eseperio\verifactu\models\Breakdown;
use eseperio\verifactu\models\BreakdownDetail;
use eseperio\verifactu\models\Chaining;
use eseperio\verifactu\models\ComputerSystem;
use eseperio\verifactu\models\enums\InvoiceType;
use eseperio\verifactu\models\enums\OperationQualificationType;
use eseperio\verifactu\models\enums\TaxType;
use eseperio\verifactu\models\enums\YesNoType;
use eseperio\verifactu\models\InvoiceId;
use eseperio\verifactu\models\InvoiceSubmission;
use eseperio\verifactu\models\LegalPerson;
use eseperio\verifactu\models\Recipient;
use eseperio\verifactu\Verifactu;
use PHPUnit\Framework\TestCase;

class ReadmeAltaExampleTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $certPath = getenv('VERIFACTU_CERT_PATH');
        $certPass = getenv('VERIFACTU_CERT_PASS');

        if (empty($certPath) || empty($certPass)) {
            $this->markTestSkipped(
                'Skipping integration test that requires a real certificate for connecting to the AEAT sandbox. ' .
                'To run this test, set the following environment variables: ' .
                'VERIFACTU_CERT_PATH (path to your .p12 certificate file) and ' .
                'VERIFACTU_CERT_PASS (password for your certificate)'
            );
        }

        Verifactu::config(
            $certPath,
            $certPass,
            Verifactu::TYPE_CERTIFICATE,
            Verifactu::ENVIRONMENT_SANDBOX
        );
    }

    public function testRegisterInvoiceSandbox()
    {
        $invoice = new InvoiceSubmission();

        // Set invoice ID (using object-oriented approach)
        $invoiceId = new InvoiceId();
        $invoiceId->issuerNif = 'B12345678';
        $invoiceId->seriesNumber = 'FA2025/' . uniqid();
        $invoiceId->issueDate = date('Y-m-d');
        $invoice->setInvoiceId($invoiceId);

        // Set basic invoice data
        $invoice->issuerName = 'Empresa Ejemplo SL';
        $invoice->invoiceType = InvoiceType::STANDARD;
        $invoice->operationDescription = 'Venta de productos';
        $invoice->taxAmount = 21.00;
        $invoice->totalAmount = 121.00;
        $invoice->simplifiedInvoice = YesNoType::NO;
        $invoice->invoiceWithoutRecipient = YesNoType::NO;

        // Add tax breakdown (using object-oriented approach)
        $breakdown = new Breakdown();
        $detail = new BreakdownDetail();
        $detail->taxType = TaxType::IVA;
        $detail->taxRate = 21.00;
        $detail->taxableBase = 100.00;
        $detail->taxAmount = 21.00;
        $detail->operationQualification = OperationQualificationType::SUBJECT_NO_EXEMPT_NO_REVERSE;
        $breakdown->addDetail($detail);
        $invoice->setBreakdown($breakdown);

        // Set chaining data (using object-oriented approach)
        $chaining = new Chaining();
        $chaining->firstRecord = YesNoType::YES;
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
        $invoice->recordTimestamp = date('c');

        // Optional fields
        $invoice->operationDate = date('Y-m-d');
        $invoice->externalRef = 'REF' . time();

        // Add recipients (using object-oriented approach)
        $recipient = new Recipient();
        $recipientPerson = new LegalPerson();
        $recipientPerson->name = 'Cliente Ejemplo SL';
        $recipientPerson->nif = 'A98765432';
        $recipient->setLegalPerson($recipientPerson);
        $invoice->setRecipient($recipient);

        // Validate the invoice before submission
        $validationResult = $invoice->validate();
        $this->assertIsArray($validationResult);
        $this->assertEmpty($validationResult, 'Invoice validation failed: ' . print_r($validationResult, true));

        // Submit the invoice
        $response = Verifactu::registerInvoice($invoice);

        $this->assertEquals(\eseperio\verifactu\models\InvoiceResponse::STATUS_OK, $response->submissionStatus, 'The invoice submission failed. Errors: ' . print_r($response->lineResponses, true));
        $this->assertNotEmpty($response->csv);
    }
}
