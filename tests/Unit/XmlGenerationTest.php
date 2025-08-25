<?php

declare(strict_types=1);

namespace eseperio\verifactu\tests\Unit;

use eseperio\verifactu\models\Breakdown;
use eseperio\verifactu\models\BreakdownDetail;
use eseperio\verifactu\models\InvoiceId;
use eseperio\verifactu\models\InvoiceSubmission;
use eseperio\verifactu\services\InvoiceSerializer;
use eseperio\verifactu\models\enums\InvoiceType;
use eseperio\verifactu\models\enums\OperationQualificationType;
use PHPUnit\Framework\TestCase;

class XmlGenerationTest extends TestCase
{
    public function testBreakdownXmlGeneration(): void
    {
        // Create a basic invoice
        $invoice = new InvoiceSubmission();
        
        // Set invoice ID
        $invoiceId = new InvoiceId();
        $invoiceId->issuerNif = 'B12345678';
        $invoiceId->seriesNumber = 'TEST-001';
        // XSD expects DD-MM-YYYY pattern
        $invoiceId->issueDate = '19-08-2025';
        $invoice->setInvoiceId($invoiceId);
        
        // Set basic invoice data
        $invoice->issuerName = 'Empresa Test SL';
        $invoice->invoiceType = InvoiceType::STANDARD;
        $invoice->operationDescription = 'Test XML Generation';
        $invoice->taxAmount = 21.00;
        $invoice->totalAmount = 121.00;
        
        // Add tax breakdown
        $breakdownDetail = new BreakdownDetail();
        $breakdownDetail->taxRate = 21.0;
        $breakdownDetail->taxableBase = 100.00;
        $breakdownDetail->taxAmount = 21.00;
        $breakdownDetail->operationQualification = OperationQualificationType::SUBJECT_NO_EXEMPT_NO_REVERSE;
        $invoice->addBreakdownDetail($breakdownDetail);
        
        // Generate XML
        $xml = InvoiceSerializer::toInvoiceXml($invoice);
        
        // Output XML for inspection
        $xmlString = $xml->saveXML();
        echo "Generated XML:\n" . $xmlString . "\n";
        
        // Check if the Desglose element exists (namespace-aware)
        $desgloseElements = $xml->getElementsByTagNameNS(InvoiceSubmission::SF_NAMESPACE, 'Desglose');
        $this->assertEquals(1, $desgloseElements->length, 'There should be exactly one Desglose element');
        
        // Check if the Desglose element has content
        $desgloseElement = $desgloseElements->item(0);
        $this->assertTrue($desgloseElement->hasChildNodes(), 'The sf:Desglose element should have child nodes');
    }
}
