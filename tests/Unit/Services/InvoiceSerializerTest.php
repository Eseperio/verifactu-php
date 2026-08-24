<?php

namespace eseperio\verifactu\tests\Unit\Services;

use eseperio\verifactu\models\InvoiceCancellation;
use eseperio\verifactu\models\InvoiceId;
use eseperio\verifactu\models\InvoiceQuery;
use eseperio\verifactu\models\InvoiceSubmission;
use eseperio\verifactu\models\LegalPerson;
use eseperio\verifactu\models\enums\HashType;
use eseperio\verifactu\models\enums\InvoiceType;
use eseperio\verifactu\models\enums\YesNoType;
use eseperio\verifactu\services\InvoiceSerializer;
use PHPUnit\Framework\TestCase;

class InvoiceSerializerTest extends TestCase
{
    /**
     * Test that the InvoiceSerializer can generate XML for an InvoiceSubmission.
     */
    public function testToInvoiceXml(): void
    {
        // Create a basic InvoiceSubmission object
        $invoice = $this->createBasicInvoiceSubmission();

        // Generate XML using the serializer
        $dom = InvoiceSerializer::toInvoiceXml($invoice, false); // Skip validation

        // Verify basic structure
        $this->assertInstanceOf(\DOMDocument::class, $dom);
        $this->assertEquals('sf:RegistroAlta', $dom->documentElement->nodeName);

        // Verify IDVersion element
        $idVersions = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'IDVersion');
        $this->assertEquals(1, $idVersions->length);
        $this->assertEquals('1.0', $idVersions->item(0)->textContent);

        // Verify IDFactura element and its children
        $idFacturas = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'IDFactura');
        $this->assertEquals(1, $idFacturas->length);

        $idEmisor = $idFacturas->item(0)->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'IDEmisorFactura');
        $this->assertEquals(1, $idEmisor->length);
        $this->assertEquals('12345678Z', $idEmisor->item(0)->textContent);

        $numSerie = $idFacturas->item(0)->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'NumSerieFactura');
        $this->assertEquals(1, $numSerie->length);
        $this->assertEquals('TEST001', $numSerie->item(0)->textContent);

        $fechaExpedicion = $idFacturas->item(0)->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'FechaExpedicionFactura');
        $this->assertEquals(1, $fechaExpedicion->length);
        $this->assertEquals('01-01-2023', $fechaExpedicion->item(0)->textContent);

        // Verify issuer name
        $nombreRazon = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'NombreRazonEmisor');
        $this->assertEquals(1, $nombreRazon->length);
        $this->assertEquals('Test Company', $nombreRazon->item(0)->textContent);

        // Verify subsanation is not present
        $correction = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'Subsanacion');
        $this->assertEquals(YesNoType::NO->value, $correction->item(0)->textContent);

        // Verify invoice type
        $tipoFactura = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'TipoFactura');
        $this->assertEquals(1, $tipoFactura->length);
        $this->assertEquals(InvoiceType::STANDARD->value, $tipoFactura->item(0)->textContent);

        // Verify operation description
        $descripcionOperacion = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'DescripcionOperacion');
        $this->assertEquals(1, $descripcionOperacion->length);
        $this->assertEquals('Test operation', $descripcionOperacion->item(0)->textContent);

        // Verify recipients
        $destinatarios = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'Destinatarios');
        $this->assertEquals(1, $destinatarios->length);

        $idDestinatario = $destinatarios->item(0)->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'IDDestinatario');
        $this->assertEquals(1, $idDestinatario->length);

        $nif = $idDestinatario->item(0)->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'NIF');
        $this->assertEquals(1, $nif->length);
        $this->assertEquals('87654321X', $nif->item(0)->textContent);

        // Verify amounts
        $cuotaTotal = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'CuotaTotal');
        $this->assertEquals(1, $cuotaTotal->length);
        $this->assertEquals('21.00', $cuotaTotal->item(0)->textContent);

        $importeTotal = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'ImporteTotal');
        $this->assertEquals(1, $importeTotal->length);
        $this->assertEquals('121.00', $importeTotal->item(0)->textContent);

        // Verify hash information
        $tipoHuella = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'TipoHuella');
        $this->assertEquals(1, $tipoHuella->length);
        $this->assertEquals(HashType::SHA_256->value, $tipoHuella->item(0)->textContent);

        $huella = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'Huella');
        $this->assertEquals(1, $huella->length);
        $this->assertEquals(str_repeat('a', 64), $huella->item(0)->textContent);
    }

    /**
     * Test that the InvoiceSerializer can generate a XML for InvoiceSubmission with the subsanation info
     */
    public function testToInvoiceXmlWithIsSubsanation()
    {
         // Create a basic InvoiceSubmission object
        $invoice = $this->createBasicInvoiceSubmission();
        $invoice->isCorrection = YesNoType::YES;

        // Generate XML using the serializer
        $dom = InvoiceSerializer::toInvoiceXml($invoice, false); // Skip validation

        // Verify subsanation is present
        $subsanation = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'Subsanacion');
        $this->assertEquals(YesNoType::YES->value, $subsanation->item(0)->textContent);
    }

    /**
     * Test that the InvoiceSerializer can generate XML for an InvoiceCancellation.
     */
    public function testToCancellationXml(): void
    {
        // Create a basic InvoiceCancellation object
        $cancellation = $this->createBasicInvoiceCancellation();

        // Generate XML using the serializer
        $dom = InvoiceSerializer::toCancellationXml($cancellation, false); // Skip validation

        // Verify basic structure
        $this->assertInstanceOf(\DOMDocument::class, $dom);
        $this->assertEquals('sf:RegistroAnulacion', $dom->documentElement->nodeName);

        // Verify IDVersion element
        $idVersions = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'IDVersion');
        $this->assertEquals(1, $idVersions->length);
        $this->assertEquals('1.0', $idVersions->item(0)->textContent);

        // Verify IDFactura element and its children
        $idFacturas = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'IDFactura');
        $this->assertEquals(1, $idFacturas->length);

        $idEmisor = $idFacturas->item(0)->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'IDEmisorFacturaAnulada');
        $this->assertEquals(1, $idEmisor->length);
        $this->assertEquals('12345678Z', $idEmisor->item(0)->textContent);

        $numSerie = $idFacturas->item(0)->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'NumSerieFacturaAnulada');
        $this->assertEquals(1, $numSerie->length);
        $this->assertEquals('TEST001', $numSerie->item(0)->textContent);

        $fechaExpedicion = $idFacturas->item(0)->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'FechaExpedicionFacturaAnulada');
        $this->assertEquals(1, $fechaExpedicion->length);
        $this->assertEquals('01-01-2023', $fechaExpedicion->item(0)->textContent);

        // Verify previous rejection
        $rechazoPrevio = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'RechazoPrevio');
        $this->assertEquals(1, $rechazoPrevio->length);
        $this->assertEquals(YesNoType::NO->value, $rechazoPrevio->item(0)->textContent);

        // Verify encadenamiento
        $encadenamiento = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'Encadenamiento');
        $this->assertEquals(1, $encadenamiento->length);

        $primerRegistro = $encadenamiento->item(0)->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'PrimerRegistro');
        $this->assertEquals(1, $primerRegistro->length);
        $this->assertEquals('S', $primerRegistro->item(0)->textContent);

        // Verify hash information
        $tipoHuella = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'TipoHuella');
        $this->assertEquals(1, $tipoHuella->length);
        $this->assertEquals(HashType::SHA_256->value, $tipoHuella->item(0)->textContent);

        $huella = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'Huella');
        $this->assertEquals(1, $huella->length);
        $this->assertEquals(str_repeat('a', 64), $huella->item(0)->textContent);
    }

    /**
     * Test that the InvoiceSerializer can generate XML for an InvoiceQuery.
     */
    public function testToQueryXml(): void
    {
        // Create a basic InvoiceQuery object
        $query = $this->createBasicInvoiceQuery();

        // Generate XML using the serializer
        $dom = InvoiceSerializer::toQueryXml($query, false); // Skip validation

        // Verify basic structure
        $this->assertInstanceOf(\DOMDocument::class, $dom);
        $this->assertEquals('sf:ConsultaFactuSistemaFacturacion', $dom->documentElement->nodeName);

        // Verify required elements
        $ejercicio = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'Ejercicio');
        $this->assertEquals(1, $ejercicio->length);
        $this->assertEquals('2023', $ejercicio->item(0)->textContent);

        $periodo = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'Periodo');
        $this->assertEquals(1, $periodo->length);
        $this->assertEquals('01', $periodo->item(0)->textContent);

        // Verify optional elements
        $numSerieFactura = $dom->getElementsByTagNameNS(InvoiceSerializer::QUERY_NAMESPACE, 'NumSerieFactura');
        $this->assertEquals(1, $numSerieFactura->length);
        $this->assertEquals('TEST001', $numSerieFactura->item(0)->textContent);

        $contraparte = $dom->getElementsByTagNameNS(InvoiceSerializer::QUERY_NAMESPACE, 'Contraparte');
        $this->assertEquals(1, $contraparte->length);

        $nif = $contraparte->item(0)->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'NIF');
        $this->assertEquals(1, $nif->length);
        $this->assertEquals('87654321X', $nif->item(0)->textContent);

        $nombreRazon = $contraparte->item(0)->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'NombreRazon');
        $this->assertEquals(1, $nombreRazon->length);
        $this->assertEquals('Test Counterparty', $nombreRazon->item(0)->textContent);
    }

    /**
     * Test that the XML validation works for InvoiceSubmission.
     */
    public function testValidateInvoiceXml(): void
    {
        // Create a basic InvoiceSubmission object
        $invoice = $this->createBasicInvoiceSubmission();

        // Generate XML using the serializer with validation enabled
        try {
            $dom = InvoiceSerializer::toInvoiceXml($invoice, true);
            // If we get here, validation passed
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // If validation fails, the test should fail
            $this->fail('XML validation failed: ' . $e->getMessage());
        }
    }

    /**
     * Test that the XML validation works for InvoiceCancellation.
     */
    public function testValidateCancellationXml(): void
    {
        // Create a basic InvoiceCancellation object
        $cancellation = $this->createBasicInvoiceCancellation();

        // Generate XML using the serializer with validation enabled
        try {
            $dom = InvoiceSerializer::toCancellationXml($cancellation, true);
            // If we get here, validation passed
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // If validation fails, the test should fail
            $this->fail('XML validation failed: ' . $e->getMessage());
        }
    }

    /**
     * Test that the XML validation works for InvoiceQuery.
     */
    public function testValidateQueryXml(): void
    {
        // Create a basic InvoiceQuery object
        $query = $this->createBasicInvoiceQuery();

        // Generate XML using the serializer with validation enabled
        try {
            $dom = InvoiceSerializer::toQueryXml($query, true);
            // If we get here, validation passed
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // If validation fails, the test should fail
            $this->fail('XML validation failed: ' . $e->getMessage());
        }
    }

    /**
     * Test that the InvoiceSerializer can wrap an XML document with the proper structure.
     */
    public function testWrapXmlWithRegFactuStructure(): void
    {
        // Create a simple DOM document
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElementNS(InvoiceSerializer::SF_NAMESPACE, 'sf:RegistroAlta');
        $doc->appendChild($root);

        // Wrap it
        $wrapped = InvoiceSerializer::wrapXmlWithRegFactuStructure($doc, '12345678Z', 'Test Name');

        // Verify structure
        $this->assertInstanceOf(\DOMDocument::class, $wrapped);
        $this->assertEquals('sfLR:RegFactuSistemaFacturacion', $wrapped->documentElement->nodeName);

        // Check for Cabecera element
        $cabeceras = $wrapped->getElementsByTagNameNS(InvoiceSerializer::SFLR_NAMESPACE, 'Cabecera');
        $this->assertEquals(1, $cabeceras->length);

        // Check for ObligadoEmision element
        $obligados = $wrapped->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'ObligadoEmision');
        $this->assertEquals(1, $obligados->length);

        // Check NIF and NombreRazon
        $nifs = $wrapped->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'NIF');
        $this->assertGreaterThanOrEqual(1, $nifs->length);
        $this->assertEquals('12345678Z', $nifs->item(0)->textContent);

        $nombres = $wrapped->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'NombreRazon');
        $this->assertGreaterThanOrEqual(1, $nombres->length);
        $this->assertEquals('Test Name', $nombres->item(0)->textContent);

        // Without FechaFinVeriFactu, RemisionVoluntaria must not be present
        $remision = $wrapped->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'RemisionVoluntaria');
        $this->assertEquals(0, $remision->length);
    }

    /**
     * Test that wrapXmlWithRegFactuStructure correctly includes FechaFinVeriFactu
     * when provided (AEAT validation 31.1.3).
     */
    public function testWrapXmlWithRegFactuStructureWithFechaFinVeriFactu(): void
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElementNS(InvoiceSerializer::SF_NAMESPACE, 'sf:RegistroAlta');
        $doc->appendChild($root);

        $fechaFin = '31-12-2025';
        $wrapped = InvoiceSerializer::wrapXmlWithRegFactuStructure($doc, '12345678Z', 'Test Name', $fechaFin);

        // RemisionVoluntaria must be present
        $remision = $wrapped->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'RemisionVoluntaria');
        $this->assertEquals(1, $remision->length);

        // FechaFinVeriFactu must contain the correct date
        $fechaFinElements = $wrapped->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'FechaFinVeriFactu');
        $this->assertEquals(1, $fechaFinElements->length);
        $this->assertEquals($fechaFin, $fechaFinElements->item(0)->textContent);
    }

    /**
     * Test that InvoiceRecord validates FechaFinVeriFactu format (AEAT 31.1.3).
     * Must be 31-12-YYYY (December 31st).
     */
    public function testFechaFinVeriFactuValidation(): void
    {
        $invoice = $this->createBasicInvoiceSubmission();

        // Valid: December 31st
        $invoice->fechaFinVeriFactu = '31-12-2025';
        $errors = $invoice->validate();
        $hasError = (bool) array_filter(array_keys($errors), fn($k) => str_contains($k, 'fechaFinVeriFactu'));
        $this->assertFalse($hasError, 'Valid FechaFinVeriFactu should not produce validation errors');

        // Invalid: not December 31st
        $invoice->fechaFinVeriFactu = '01-01-2025';
        $errors = $invoice->validate();
        $hasError = (bool) array_filter(array_keys($errors), fn($k) => str_contains($k, 'fechaFinVeriFactu'));
        $this->assertTrue($hasError, 'Non-December-31 date should produce a validation error');

        // Invalid: wrong format (ISO instead of DD-MM-YYYY)
        $invoice->fechaFinVeriFactu = '2025-12-31';
        $errors = $invoice->validate();
        $hasError = (bool) array_filter(array_keys($errors), fn($k) => str_contains($k, 'fechaFinVeriFactu'));
        $this->assertTrue($hasError, 'ISO format date should produce a validation error');

        // Null is allowed (optional field)
        $invoice->fechaFinVeriFactu = null;
        $errors = $invoice->validate();
        $hasError = (bool) array_filter(array_keys($errors), fn($k) => str_contains($k, 'fechaFinVeriFactu'));
        $this->assertFalse($hasError, 'Null FechaFinVeriFactu should not produce validation errors');
    }

    /**
     * Test that OtherID with Netherlands ('NL') country code is correctly serialised.
     * AEAT v1.2.1 (23/02/2026): 'NL' remains the correct code for Netherlands
     * (denomination changed from 'Holanda' to 'Países Bajos'; code unchanged).
     */
    public function testOtherIdNetherlandsCountryCode(): void
    {
        $invoice = $this->createBasicInvoiceSubmission();

        // Replace the domestic recipient with a Netherlands (NL) foreign entity
        $otherIdNL = new \eseperio\verifactu\models\OtherID();
        $otherIdNL->countryCode = 'NL';
        $otherIdNL->idType = '04'; // ID in country of residence
        $otherIdNL->id = 'NL123456789B01';

        $recipient = new LegalPerson();
        $recipient->name = 'Dutch Company BV';
        $recipient->setOtherId($otherIdNL);

        // Reset recipients and add NL one
        $invoice2 = $this->createBasicInvoiceSubmission();
        // Build a fresh invoice without the existing recipient to avoid validation errors
        $invoiceNL = new InvoiceSubmission();
        $invoiceId = new InvoiceId();
        $invoiceId->issuerNif = '12345678Z';
        $invoiceId->seriesNumber = 'TEST-NL-001';
        $invoiceId->issueDate = '2023-01-01';
        $invoiceNL->setInvoiceId($invoiceId);
        $invoiceNL->issuerName = 'Test Company';
        $invoiceNL->invoiceType = InvoiceType::STANDARD;
        $invoiceNL->operationDescription = 'Test NL operation';
        $invoiceNL->taxAmount = 21.00;
        $invoiceNL->totalAmount = 121.00;
        $invoiceNL->addRecipient($recipient);
        $invoiceNL->hashType = \eseperio\verifactu\models\enums\HashType::SHA_256;
        $invoiceNL->hash = str_repeat('b', 64);
        $invoiceNL->recordTimestamp = date('Y-m-d\TH:i:s');
        $invoiceNL->setAsFirstRecord();

        $dom = InvoiceSerializer::toInvoiceXml($invoiceNL, false);

        // Verify CodigoPais is 'NL'
        $codigoPais = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'CodigoPais');
        $this->assertGreaterThanOrEqual(1, $codigoPais->length);
        $this->assertEquals('NL', $codigoPais->item(0)->textContent);

        // Verify IDType is '04'
        $idType = $dom->getElementsByTagNameNS(InvoiceSerializer::SF_NAMESPACE, 'IDType');
        $this->assertGreaterThanOrEqual(1, $idType->length);
        $this->assertEquals('04', $idType->item(0)->textContent);
    }

    /**
     * Creates a basic InvoiceSubmission for testing.
     *
     * @return InvoiceSubmission
     */
    private function createBasicInvoiceSubmission(): InvoiceSubmission
    {
        $invoice = new InvoiceSubmission();

        // Set required properties
        $invoiceId = new InvoiceId();
        $invoiceId->issuerNif = '12345678Z';
        $invoiceId->seriesNumber = 'TEST001';
        $invoiceId->issueDate = '2023-01-01';
        $invoice->setInvoiceId($invoiceId);

        $invoice->issuerName = 'Test Company';
        $invoice->invoiceType = InvoiceType::STANDARD;
        $invoice->operationDescription = 'Test operation';
        $invoice->taxAmount = 21.00;
        $invoice->totalAmount = 121.00;
        $invoice->isCorrection = YesNoType::NO;

        // Add a recipient
        $recipient = new LegalPerson();
        $recipient->name = 'Test Client';
        $recipient->nif = '87654321X';
        $invoice->addRecipient($recipient);

        // Set hash-related properties
        $invoice->hashType = HashType::SHA_256;
        $invoice->hash = str_repeat('a', 64); // 64 chars for SHA-256

        // Set other required properties
        $invoice->recordTimestamp = date('Y-m-d\TH:i:s');
        $invoice->setAsFirstRecord();

        return $invoice;
    }

    /**
     * Creates a basic InvoiceCancellation for testing.
     *
     * @return InvoiceCancellation
     */
    private function createBasicInvoiceCancellation(): InvoiceCancellation
    {
        $cancellation = new InvoiceCancellation();

        // Set required properties
        $invoiceId = new InvoiceId();
        $invoiceId->issuerNif = '12345678Z';
        $invoiceId->seriesNumber = 'TEST001';
        $invoiceId->issueDate = '2023-01-01';
        $cancellation->setInvoiceId($invoiceId);

        // Set hash-related properties
        $cancellation->hashType = HashType::SHA_256;
        $cancellation->hash = str_repeat('a', 64); // 64 chars for SHA-256

        // Set other required properties
        $cancellation->recordTimestamp = date('Y-m-d\TH:i:s');
        $cancellation->setAsFirstRecord();
        $cancellation->previousRejection = YesNoType::NO;

        return $cancellation;
    }

    /**
     * Creates a basic InvoiceQuery for testing.
     *
     * @return InvoiceQuery
     */
    private function createBasicInvoiceQuery(): InvoiceQuery
    {
        $query = new InvoiceQuery();

        // Set required properties
        $query->year = '2023';
        $query->period = '01';

        // Set optional properties
        $query->seriesNumber = 'TEST001';
        $query->issueDate = '2023-01-01';
        $query->externalRef = 'TEST-REF-001';

        // Set counterparty
        $query->setCounterparty('87654321X', 'Test Counterparty');

        // Set issuerparty
        $query->setIssuerparty('98765432M', 'Test Issuer');

        // Set system info
        $query->setSystemInfo('Test System', '1.0');

        // Set pagination key
        $query->setPaginationKey(1, 10);

        return $query;
    }
}
