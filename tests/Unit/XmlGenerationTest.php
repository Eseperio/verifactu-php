<?php

declare(strict_types=1);

namespace eseperio\verifactu\tests\Unit;

use eseperio\verifactu\models\Breakdown;
use eseperio\verifactu\models\BreakdownDetail;
use eseperio\verifactu\models\InvoiceId;
use eseperio\verifactu\models\InvoiceSubmission;
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
        $invoiceId->issueDate = '2025-08-19';
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
        $xml = $invoice->toXml();
        
        // Output XML for inspection
        $xmlString = $xml->saveXML();
        echo "Generated XML:\n" . $xmlString . "\n";
        
        // Check if the Desglose element exists (use namespace-aware search)
        $ns = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';
        $desgloseElements = $xml->getElementsByTagNameNS($ns, 'Desglose');
        if ($desgloseElements->length === 0) {
            // Fallback: try XPath if namespace lookup fails
            $xpath = new \DOMXPath($xml);
            $xpath->registerNamespace('sf', $ns);
            $nodeList = $xpath->query('//sf:Desglose');
            $this->assertNotFalse($nodeList, 'XPath query for sf:Desglose failed');
            $this->assertEquals(1, $nodeList->length, 'There should be exactly one sf:Desglose element');
            $desgloseElement = $nodeList->item(0);
        } else {
            $this->assertEquals(1, $desgloseElements->length, 'There should be exactly one sf:Desglose element');
            $desgloseElement = $desgloseElements->item(0);
        }
        
        // Check if the Desglose element has content
        $desgloseElement = $desgloseElements->item(0);
        $this->assertTrue($desgloseElement->hasChildNodes(), 'The sf:Desglose element should have child nodes');
    }
}
