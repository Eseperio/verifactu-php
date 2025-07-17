<?php

declare(strict_types=1);

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
    /** WSDL parameter name. Use the official AEAT WSDL provided in documentos api/SistemaFacturacion.wsdl.xml */
    public const WSDL_ENDPOINT = 'wsdl';
    /** Certificate path parameter name. */
    public const CERT_PATH_KEY = 'certPath';
    /** Certificate password parameter name. */
    public const CERT_PASSWORD_KEY = 'certPassword';
    /** Certificate content parameter name. */
    public const CERT_CONTENT_KEY = 'certContent';
    /** Certificate content type parameter name. */
    public const CERT_CONTENT_TYPE_KEY = 'certContentType';
    /** QR verification URL parameter name. */
    public const QR_VERIFICATION_URL = 'qrValidationUrl';

    /** Global configuration for Verifactu service. @var array */
    protected static $config = [];

    /** Soap instance for communication with AEAT. @var \SoapClient|null */
    protected static $client;

    /** Sets the global configuration. @param array $data */
    public static function config($data): void
    {
        // Use official AEAT WSDL from repo if not set in config
        if (!isset($data[self::WSDL_ENDPOINT]) || empty($data[self::WSDL_ENDPOINT])) {
            $data[self::WSDL_ENDPOINT] = __DIR__ . '/../docs/aeat/SistemaFacturacion.wsdl.xml';
        }
        self::$config = $data;
        self::$client = null;
    }

    /** Gets a configuration parameter. @param string $param @return mixed @throws \InvalidArgumentException */
    public static function getConfig($param)
    {
        if (!isset(self::$config[$param])) {
            throw new \InvalidArgumentException("Configuration parameter '$param' is not defined.");
        }

        return self::$config[$param];
    }

    /** Returns the SOAP client, creating it if necessary. @return \SoapClient */
    protected static function getClient()
    {
        if (self::$client === null) {
            $environment = self::$config['environment'] ?? null;
            $certPath = self::$config[self::CERT_PATH_KEY] ?? null;
            $certContent = self::$config[self::CERT_CONTENT_KEY] ?? null;
            // If certificate content exists, use it instead of the path
            if ($certContent) {
                $certPath = $certContent;
            }
            self::$client = SoapClientFactoryService::createSoapClient(
                self::getConfig(self::WSDL_ENDPOINT),
                $certPath,
                self::getConfig(self::CERT_PASSWORD_KEY),
                [],
                $environment
            );
        }
        return self::$client;
    }

    /**
     * Registers a new invoice with AEAT via VERI*FACTU.
     *
     * @return InvoiceResponse
     * @throws \DOMException
     * @throws \SoapFault
     */
    public static function registerInvoice(InvoiceSubmission $invoice)
    {
        // 1. Validate input (excluding hash which will be generated)
        $validation = $invoice->validateExceptHash();

        if ($validation !== true) {
            throw new \InvalidArgumentException('InvoiceSubmission validation failed: ' . print_r($validation, true));
        }

        // 2. Generate hash (huella)
        $invoice->hash = HashGeneratorService::generate($invoice);

        // 3. Final validation including hash
        $finalValidation = $invoice->validate();

        if ($finalValidation !== true) {
            throw new \InvalidArgumentException('InvoiceSubmission final validation failed: ' . print_r($finalValidation, true));
        }

        // 3. Prepare XML (you would build this as per AEAT XSD, example below is placeholder)
        $xml = self::buildInvoiceXml($invoice);

        // 4. Sign XML
        if (!empty(self::$config[self::CERT_CONTENT_KEY])) {
            $signedXml = XmlSignerService::signXmlWithContent(
                $xml,
                self::getConfig(self::CERT_CONTENT_KEY),
                self::getConfig(self::CERT_PASSWORD_KEY)
            );
        } else {
            $signedXml = XmlSignerService::signXml(
                $xml,
                self::getConfig(self::CERT_PATH_KEY),
                self::getConfig(self::CERT_PASSWORD_KEY)
            );
        }

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
     * @return InvoiceResponse
     */
    public static function cancelInvoice(InvoiceCancellation $cancellation)
    {
        // 1. Validate input (excluding hash which will be generated)
        $validation = $cancellation->validateExceptHash();

        if ($validation !== true) {
            throw new \InvalidArgumentException('InvoiceCancellation validation failed: ' . print_r($validation, true));
        }

        // 2. Generate hash (huella)
        $cancellation->hash = HashGeneratorService::generate($cancellation);

        // 3. Final validation including hash
        $finalValidation = $cancellation->validate();

        if ($finalValidation !== true) {
            throw new \InvalidArgumentException('InvoiceCancellation final validation failed: ' . print_r($finalValidation, true));
        }
        $xml = self::buildCancellationXml($cancellation);
        if (!empty(self::$config[self::CERT_CONTENT_KEY])) {
            $signedXml = XmlSignerService::signXmlWithContent(
                $xml,
                self::getConfig(self::CERT_CONTENT_KEY),
                self::getConfig(self::CERT_PASSWORD_KEY)
            );
        } else {
            $signedXml = XmlSignerService::signXml(
                $xml,
                self::getConfig(self::CERT_PATH_KEY),
                self::getConfig(self::CERT_PASSWORD_KEY)
            );
        }
        $client = self::getClient();
        $params = ['RegistroAnulacion' => $signedXml];
        $responseXml = $client->__soapCall('SuministroLR', [$params]);

        return ResponseParserService::parseInvoiceResponse($responseXml);
    }

    /**
     * Queries submitted invoices from AEAT via VERI*FACTU.
     *
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
    ) {
        $baseUrl = self::getConfig(self::QR_VERIFICATION_URL);

        return QrGeneratorService::generateQr($record, $baseUrl, $destination, $size, $engine);
    }

    /** Serializes an InvoiceSubmission to AEAT-compliant RegistroAlta XML. @return string XML string @throws \DOMException */
    protected static function buildInvoiceXml(InvoiceSubmission $invoice): string|false
    {
        $invoiceDom = $invoice->toXml();

        return $invoiceDom->saveXML();
    }

    /** Serializes an InvoiceCancellation to AEAT-compliant RegistroAnulacion XML. @return string XML string @throws \DOMException */
    protected static function buildCancellationXml(InvoiceCancellation $cancellation): string|false
    {
        // Get the XML element from the model
        $cancellationDom = $cancellation->toXml();

        return $cancellationDom->saveXML();
    }

    /** Serializes an InvoiceQuery to AEAT-compliant ConsultaFactuSistemaFacturacion XML. @return string XML string @throws \DOMException */
    protected static function buildQueryXml(InvoiceQuery $query): string|false
    {
        $queryDom = $query->toXml();

        return $queryDom->saveXML();
    }
}
