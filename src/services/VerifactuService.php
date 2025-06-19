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
     * WSDL parameter name.
     */
    const WSDL_ENDPOINT = 'wsdl';
    /**
     * Certificate path parameter name.
     */
    const CERT_PATH_KEY = 'certPath';
    /**
     * Certificate password parameter name.
     */
    const CERT_PASSWORD_KEY = 'certPassword';
    /**
     * Environment: production.
     */
    const QR_VERIFICATION_URL = 'qrValidationUrl';

    /**
     * Global configuration for Verifactu service.
     * @var array
     */
    protected static $config = [];

    /**
     * Soap instance for communication with AEAT.
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
        self::$client = null;
    }

    /**
     * Obtiene uns parámetro de configuración.
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
                self::getConfig(self::WSDL_ENDPOINT),
                self::getConfig(self::CERT_PATH_KEY),
                self::getConfig(self::CERT_PASSWORD_KEY)
            );
        }
        return self::$client;
    }

    /**
     * Registers a new invoice with AEAT via VERI*FACTU.
     *
     * @param InvoiceSubmission $invoice
     * @return InvoiceResponse
     * @throws \DOMException
     * @throws \SoapFault
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
            self::getConfig(self::CERT_PATH_KEY),
            self::getConfig(self::CERT_PASSWORD_KEY)
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
            self::getConfig(self::CERT_PATH_KEY),
            self::getConfig(self::CERT_PASSWORD_KEY)
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
     * @throws \SoapFault
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
     * Generates a QR code for the provided invoice.
     *
     * @param InvoiceRecord $record
     * @param string|null $baseUrl
     * @param string $destination Destination type (file or string)
     * @param int $size Resolution of the QR code
     * @param string $engine Renderer to use (gd, imagick, svg)
     * @return string QR image data or file path
     */
    public static function generateInvoiceQr(
        InvoiceRecord $record,
                      $destination = QrGeneratorService::DESTINATION_STRING,
                      $size = 300,
                      $engine = QrGeneratorService::RENDERER_GD
    )
    {
        $baseUrl = self::getConfig(self::QR_VERIFICATION_URL);
        return QrGeneratorService::generateQr($record, $baseUrl, $destination, $size, $engine);
    }

    /**
     * Serializes an InvoiceSubmission to AEAT-compliant RegistroAlta XML.
     * @param InvoiceSubmission $invoice
     * @return string XML string
     * @throws \DOMException
     */
    protected static function buildInvoiceXml(InvoiceSubmission $invoice)
    {
        $invoiceDom = $invoice->toXml();
        return $invoiceDom->saveXML();
    }

    /**
     * Serializes an InvoiceCancellation to AEAT-compliant RegistroAnulacion XML.
     *
     * @param InvoiceCancellation $cancellation
     * @return string XML string
     * @throws \DOMException
     */
    protected static function buildCancellationXml(InvoiceCancellation $cancellation)
    {
        // Get the XML element from the model
        $cancellationDom = $cancellation->toXml();
        return $cancellationDom->saveXML();
    }


    /**
     * Serializes an InvoiceQuery to AEAT-compliant ConsultaFactuSistemaFacturacion XML.
     *
     * @param InvoiceQuery $query
     * @return string XML string
     * @throws \DOMException
     */
    protected static function buildQueryXml(InvoiceQuery $query)
    {
        $queryDom = $query->toXml();
        return $queryDom->saveXML();
    }

}
