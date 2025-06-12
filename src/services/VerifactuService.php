<?php

namespace eseperio\verifactu\services;

use eseperio\verifactu\models\InvoiceCancellation;
use eseperio\verifactu\models\InvoiceQuery;
use eseperio\verifactu\models\InvoiceRecord;
use eseperio\verifactu\models\InvoiceResponse;
use eseperio\verifactu\models\InvoiceSubmission;
use eseperio\verifactu\models\QueryResponse;

/**
 * Service orchestrating all high-level Verifactu operations:
 * registration, cancellation, query, QR generation.
 */
class VerifactuService
{
    /**
     * Configuración global de la clase.
     * @var array
     */
    protected static $config = [];

    /**
     * Instancia del cliente SOAP.
     * @var \SoapClient|null
     */
    protected static $client = null;

    /**
     * Establece la configuración global.
     * @param array $data
     */
    public static function config($data)
    {
        self::$config = $data;
        self::$client = null; // Resetear cliente si cambia la config
    }

    /**
     * Obtiene un parámetro de configuración.
     * @param string $param
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public static function getConfig($param)
    {
        if (!isset(self::$config[$param])) {
            throw new \InvalidArgumentException("El parámetro de configuración '$param' no está definido.");
        }
        return self::$config[$param];
    }

    /**
     * Devuelve el cliente SOAP, creándolo si es necesario.
     * @return \SoapClient
     */
    protected static function getClient()
    {
        if (self::$client === null) {
            self::$client = SoapClientFactoryService::createSoapClient(
                self::getConfig('wsdl'),
                self::getConfig('certPath'),
                self::getConfig('certPassword')
            );
        }
        return self::$client;
    }

    /**
     * Registers a new invoice with AEAT via VERI*FACTU.
     *
     * @param InvoiceSubmission $invoice
     * @return InvoiceResponse
     */
    public static function registerInvoice(InvoiceSubmission $invoice)
    {
        // 1. Validate input
        $validation = $invoice->validate();
        if ($validation !== true) {
            throw new \InvalidArgumentException('InvoiceSubmission validation failed: ' . print_r($validation, true));
        }

        // 2. Generate hash (huella)
        $invoice->hash = HashGeneratorService::generate($invoice);

        // 3. Prepare XML (you would build this as per AEAT XSD, example below is placeholder)
        $xml = self::buildInvoiceXml($invoice);

        // 4. Sign XML
        $signedXml = XmlSignerService::signXml(
            $xml,
            self::getConfig('certPath'),
            self::getConfig('certPassword')
        );

        // 5. Get SOAP client
        $client = self::getClient();

        // 6. Call AEAT web service
        $params = ['RegistroAlta' => $signedXml];
        $responseXml = $client->__soapCall('SuministroLR', [$params]);

        // 7. Parse AEAT response
        return ResponseParserService::parseInvoiceResponse($responseXml);
    }

    /**
     * Cancels an invoice with AEAT via VERI*FACTU.
     *
     * @param InvoiceCancellation $cancellation
     * @return InvoiceResponse
     */
    public static function cancelInvoice(InvoiceCancellation $cancellation)
    {
        $validation = $cancellation->validate();
        if ($validation !== true) {
            throw new \InvalidArgumentException('InvoiceCancellation validation failed: ' . print_r($validation, true));
        }
        $cancellation->hash = HashGeneratorService::generate($cancellation);
        $xml = self::buildCancellationXml($cancellation);
        $signedXml = XmlSignerService::signXml(
            $xml,
            self::getConfig('certPath'),
            self::getConfig('certPassword')
        );
        $client = self::getClient();
        $params = ['RegistroAnulacion' => $signedXml];
        $responseXml = $client->__soapCall('SuministroLR', [$params]);
        return ResponseParserService::parseInvoiceResponse($responseXml);
    }

    /**
     * Queries submitted invoices from AEAT via VERI*FACTU.
     *
     * @param InvoiceQuery $query
     * @return QueryResponse
     */
    public static function queryInvoices(InvoiceQuery $query)
    {
        $validation = $query->validate();
        if ($validation !== true) {
            throw new \InvalidArgumentException('InvoiceQuery validation failed: ' . print_r($validation, true));
        }
        $xml = self::buildQueryXml($query);
        $client = self::getClient();
        $params = ['ConsultaFactuSistemaFacturacion' => $xml];
        $responseXml = $client->__soapCall('ConsultaLR', [$params]);
        return ResponseParserService::parseQueryResponse($responseXml);
    }

    /**
     * Generates a base64 QR code for the provided invoice.
     *
     * @param InvoiceRecord $record
     * @param string|null $baseVerificationUrl
     * @return string
     */
    public static function generateInvoiceQr(InvoiceRecord $record, $baseVerificationUrl = null)
    {
        if ($baseVerificationUrl === null) {
            $baseVerificationUrl = self::getConfig('baseVerificationUrl');
        }
        return QrGeneratorService::generateQr($record, $baseVerificationUrl);
    }

    /**
     * Serializes an InvoiceSubmission to AEAT-compliant RegistroAlta XML.
     * @param InvoiceSubmission $invoice
     * @return string XML string
     * @throws \DOMException
     */
    protected static function buildInvoiceXml(InvoiceSubmission $invoice)
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        // Root node <RegistroAlta>
        $registroAlta = $doc->createElement('RegistroAlta');

        // <IDVersion>
        $registroAlta->appendChild($doc->createElement('IDVersion', $invoice->versionId));

        // <IDFactura>
        $idFactura = $doc->createElement('IDFactura');
        $idFactura->appendChild($doc->createElement('IDEmisorFactura', $invoice->invoiceId->issuerNif));
        $idFactura->appendChild($doc->createElement('NumSerieFactura', $invoice->invoiceId->seriesNumber));
        $idFactura->appendChild($doc->createElement('FechaExpedicionFactura', $invoice->invoiceId->issueDate));
        $registroAlta->appendChild($idFactura);

        // <RefExterna> (optional)
        if (!empty($invoice->externalRef)) {
            $registroAlta->appendChild($doc->createElement('RefExterna', $invoice->externalRef));
        }

        // <NombreRazonEmisor>
        $registroAlta->appendChild($doc->createElement('NombreRazonEmisor', $invoice->issuerName));

        // <TipoFactura>
        $registroAlta->appendChild($doc->createElement('TipoFactura', $invoice->invoiceType));

        // <TipoRectificativa> (optional)
        if (!empty($invoice->rectificationType)) {
            $registroAlta->appendChild($doc->createElement('TipoRectificativa', $invoice->rectificationType));
        }

        // <FacturasRectificadas> o <FacturasSustituidas> (optional, array)
        // Por simplicidad, aquí solo mostramos ejemplo para rectificadas
        if (!empty($invoice->rectificationData['rectified'])) {
            $facturasRectificadas = $doc->createElement('FacturasRectificadas');
            foreach ($invoice->rectificationData['rectified'] as $item) {
                $idFacturaRectificada = $doc->createElement('IDFacturaRectificada');
                $idFacturaRectificada->appendChild($doc->createElement('IDEmisorFactura', $item['issuerNif']));
                $idFacturaRectificada->appendChild($doc->createElement('NumSerieFactura', $item['seriesNumber']));
                $idFacturaRectificada->appendChild($doc->createElement('FechaExpedicionFactura', $item['issueDate']));
                $facturasRectificadas->appendChild($idFacturaRectificada);
            }
            $registroAlta->appendChild($facturasRectificadas);
        }

        // <Destinatarios> (optional, array)
        if (!empty($invoice->recipients) && is_array($invoice->recipients)) {
            $destinatarios = $doc->createElement('Destinatarios');
            foreach ($invoice->recipients as $recipient) {
                $idDestinatario = $doc->createElement('IDDestinatario');
                $idDestinatario->appendChild($doc->createElement('NIF', $recipient['nif']));
                // Puedes añadir más campos del destinatario según XSD
                $destinatarios->appendChild($idDestinatario);
            }
            $registroAlta->appendChild($destinatarios);
        }

        // <Desglose> (tax breakdown, required, array)
        if (!empty($invoice->breakdown) && is_array($invoice->breakdown)) {
            $desglose = $doc->createElement('Desglose');
            // Este nodo puede ser muy complejo; aquí solo se muestra ejemplo mínimo
            foreach ($invoice->breakdown as $tax) {
                $detalle = $doc->createElement('Detalle');
                $detalle->appendChild($doc->createElement('TipoImpositivo', $tax['rate']));
                $detalle->appendChild($doc->createElement('BaseImponibleOimporteNoSujeto', $tax['base']));
                $detalle->appendChild($doc->createElement('CuotaRepercutida', $tax['amount']));
                $desglose->appendChild($detalle);
            }
            $registroAlta->appendChild($desglose);
        }

        // <CuotaTotal>
        $registroAlta->appendChild($doc->createElement('CuotaTotal', $invoice->taxAmount));
        // <ImporteTotal>
        $registroAlta->appendChild($doc->createElement('ImporteTotal', $invoice->totalAmount));

        // <Encadenamiento> (required, array)
        if (!empty($invoice->chaining)) {
            $encadenamiento = $doc->createElement('Encadenamiento');
            if (isset($invoice->chaining['previousHash'])) {
                $encadenamiento->appendChild($doc->createElement('RegistroAnterior', $invoice->chaining['previousHash']));
            } else {
                $encadenamiento->appendChild($doc->createElement('PrimerRegistro', 'S'));
            }
            $registroAlta->appendChild($encadenamiento);
        }

        // <SistemaInformatico> (required, array)
        if (!empty($invoice->systemInfo)) {
            $sistema = $doc->createElement('SistemaInformatico');
            foreach ($invoice->systemInfo as $key => $value) {
                $sistema->appendChild($doc->createElement($key, $value));
            }
            $registroAlta->appendChild($sistema);
        }

        // <FechaHoraHusoGenRegistro>
        $registroAlta->appendChild($doc->createElement('FechaHoraHusoGenRegistro', $invoice->recordTimestamp));

        // <TipoHuella>
        $registroAlta->appendChild($doc->createElement('TipoHuella', $invoice->hashType));

        // <Huella>
        $registroAlta->appendChild($doc->createElement('Huella', $invoice->hash));

        // Finalizar
        $doc->appendChild($registroAlta);
        return $doc->saveXML();
    }

    /**
     * Serializes an InvoiceCancellation to AEAT-compliant RegistroAnulacion XML.
     *
     * @param InvoiceCancellation $cancellation
     * @return string XML string
     */
    protected static function buildCancellationXml(InvoiceCancellation $cancellation)
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        // Root node <RegistroAnulacion>
        $registroAnulacion = $doc->createElement('RegistroAnulacion');

        // <IDVersion>
        $registroAnulacion->appendChild($doc->createElement('IDVersion', $cancellation->versionId));

        // <IDFacturaAnulada>
        $idFacturaAnulada = $doc->createElement('IDFacturaAnulada');
        $idFacturaAnulada->appendChild($doc->createElement('IDEmisorFacturaAnulada', $cancellation->invoiceId->issuerNif));
        $idFacturaAnulada->appendChild($doc->createElement('NumSerieFacturaAnulada', $cancellation->invoiceId->seriesNumber));
        $idFacturaAnulada->appendChild($doc->createElement('FechaExpedicionFacturaAnulada', $cancellation->invoiceId->issueDate));
        $registroAnulacion->appendChild($idFacturaAnulada);

        // <RefExterna> (optional)
        if (!empty($cancellation->externalRef)) {
            $registroAnulacion->appendChild($doc->createElement('RefExterna', $cancellation->externalRef));
        }

        // <SinRegistroPrevio> (optional)
        if (!empty($cancellation->noPreviousRecord)) {
            $registroAnulacion->appendChild($doc->createElement('SinRegistroPrevio', $cancellation->noPreviousRecord));
        }

        // <RechazoPrevio> (optional)
        if (!empty($cancellation->previousRejection)) {
            $registroAnulacion->appendChild($doc->createElement('RechazoPrevio', $cancellation->previousRejection));
        }

        // <GeneradoPor> (optional)
        if (!empty($cancellation->generator)) {
            $registroAnulacion->appendChild($doc->createElement('GeneradoPor', $cancellation->generator));
        }

        // <Generador> (optional, array)
        if (!empty($cancellation->generatorData) && is_array($cancellation->generatorData)) {
            $generador = $doc->createElement('Generador');
            foreach ($cancellation->generatorData as $key => $value) {
                $generador->appendChild($doc->createElement($key, $value));
            }
            $registroAnulacion->appendChild($generador);
        }

        // <Encadenamiento> (required, array)
        if (!empty($cancellation->chaining)) {
            $encadenamiento = $doc->createElement('Encadenamiento');
            if (isset($cancellation->chaining['previousHash'])) {
                $encadenamiento->appendChild($doc->createElement('RegistroAnterior', $cancellation->chaining['previousHash']));
            } else {
                $encadenamiento->appendChild($doc->createElement('PrimerRegistro', 'S'));
            }
            $registroAnulacion->appendChild($encadenamiento);
        }

        // <SistemaInformatico> (required, array)
        if (!empty($cancellation->systemInfo)) {
            $sistema = $doc->createElement('SistemaInformatico');
            foreach ($cancellation->systemInfo as $key => $value) {
                $sistema->appendChild($doc->createElement($key, $value));
            }
            $registroAnulacion->appendChild($sistema);
        }

        // <FechaHoraHusoGenRegistro>
        $registroAnulacion->appendChild($doc->createElement('FechaHoraHusoGenRegistro', $cancellation->recordTimestamp));

        // <TipoHuella>
        $registroAnulacion->appendChild($doc->createElement('TipoHuella', $cancellation->hashType));

        // <Huella>
        $registroAnulacion->appendChild($doc->createElement('Huella', $cancellation->hash));

        // Finalizar
        $doc->appendChild($registroAnulacion);
        return $doc->saveXML();
    }


    /**
     * Serializes an InvoiceQuery to AEAT-compliant ConsultaFactuSistemaFacturacion XML.
     *
     * @param InvoiceQuery $query
     * @return string XML string
     */
    protected static function buildQueryXml(InvoiceQuery $query)
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        // Root node <ConsultaFactuSistemaFacturacion>
        $consulta = $doc->createElement('ConsultaFactuSistemaFacturacion');

        // <Cabecera>
        $cabecera = $doc->createElement('Cabecera');
        $cabecera->appendChild($doc->createElement('Ejercicio', $query->year));
        $cabecera->appendChild($doc->createElement('Periodo', $query->period));
        $consulta->appendChild($cabecera);

        // <FiltroConsulta>
        $filtro = $doc->createElement('FiltroConsulta');

        // <PeriodoImputacion>
        $periodoImputacion = $doc->createElement('PeriodoImputacion');
        $periodoImputacion->appendChild($doc->createElement('Ejercicio', $query->year));
        $periodoImputacion->appendChild($doc->createElement('Periodo', $query->period));
        $filtro->appendChild($periodoImputacion);

        // <NumSerieFactura> (optional)
        if (!empty($query->seriesNumber)) {
            $filtro->appendChild($doc->createElement('NumSerieFactura', $query->seriesNumber));
        }

        // <Contraparte> (optional, array)
        if (!empty($query->counterparty) && is_array($query->counterparty)) {
            $contraparte = $doc->createElement('Contraparte');
            foreach ($query->counterparty as $key => $value) {
                $contraparte->appendChild($doc->createElement($key, $value));
            }
            $filtro->appendChild($contraparte);
        }

        // <FechaExpedicionFactura> (optional)
        if (!empty($query->issueDate)) {
            $filtro->appendChild($doc->createElement('FechaExpedicionFactura', $query->issueDate));
        }

        // <SistemaInformatico> (optional, array)
        if (!empty($query->systemInfo) && is_array($query->systemInfo)) {
            $sistema = $doc->createElement('SistemaInformatico');
            foreach ($query->systemInfo as $key => $value) {
                $sistema->appendChild($doc->createElement($key, $value));
            }
            $filtro->appendChild($sistema);
        }

        // <RefExterna> (optional)
        if (!empty($query->externalRef)) {
            $filtro->appendChild($doc->createElement('RefExterna', $query->externalRef));
        }

        // <ClavePaginacion> (optional, array)
        if (!empty($query->paginationKey) && is_array($query->paginationKey)) {
            $clavePag = $doc->createElement('ClavePaginacion');
            foreach ($query->paginationKey as $key => $value) {
                $clavePag->appendChild($doc->createElement($key, $value));
            }
            $filtro->appendChild($clavePag);
        }

        $consulta->appendChild($filtro);

        // Puedes añadir <DatosAdicionalesRespuesta> si tu modelo lo contempla y el XSD lo permite.

        $doc->appendChild($consulta);
        return $doc->saveXML();
    }

}
