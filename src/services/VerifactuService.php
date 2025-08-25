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
    /** WSDL parameter name. Use the official AEAT WSDL provided in schemas api/SistemaFacturacion.wsdl.xml */
    public const WSDL_ENDPOINT = 'wsdl';
    /** SOAP endpoint URL parameter name. */
    public const SOAP_ENDPOINT = 'soapEndpoint';
    /** Certificate path parameter name. */
    public const CERT_PATH_KEY = 'certPath';
    /** Certificate password parameter name. */
    public const CERT_PASSWORD_KEY = 'certPassword';
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
            $data[self::WSDL_ENDPOINT] = __DIR__ . '/../schemes/SistemaFacturacion.wsdl';
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
            $certPath = self::getConfig(self::CERT_PATH_KEY);
            $certPassword = self::getConfig(self::CERT_PASSWORD_KEY);

            // Generar PEM temporal compatible con SoapClient (cert + clave)
            $soapPemPath = CertificateManagerService::createSoapCompatiblePemTemp($certPath, $certPassword);

            // Use SOAP_ENDPOINT if defined, otherwise default to null
            $soapEndpoint = isset(self::$config[self::SOAP_ENDPOINT]) ? self::getConfig(self::SOAP_ENDPOINT) : null;

            $options = [];
            if ($soapEndpoint !== null) {
                $options['location'] = $soapEndpoint;
            }

            self::$client = SoapClientFactoryService::createSoapClient(
                self::getConfig(self::WSDL_ENDPOINT),
                $soapPemPath,
                $certPassword,
                $options,
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
        $validation = $invoice->validate();

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

        // 3. Get the RegistroAlta XML from the invoice using InvoiceSerializer
        $invoiceDom = InvoiceSerializer::toInvoiceXml($invoice);

        // 4. Sign the RegistroAlta XML first (so signature is inside RegistroAlta)
        $signedInvoiceXml = XmlSignerService::signXml(
            $invoiceDom->saveXML(),
            self::getConfig(self::CERT_PATH_KEY),
            self::getConfig(self::CERT_PASSWORD_KEY)
        );
        
        // 5. Create a temporary DOM document with the signed XML
        $signedDom = new \DOMDocument();
        $signedDom->loadXML($signedInvoiceXml);
        
        // 6. Get the issuer information for the Cabecera
        $invoiceId = $invoice->getInvoiceId();
        $nif = $invoiceId->issuerNif;
        $name = $invoice->issuerName;
        
        // 7. Wrap the signed XML with the proper structure using InvoiceSerializer
        $wrappedDom = InvoiceSerializer::wrapXmlWithRegFactuStructure($signedDom, $nif, $name);
        
        // Get XML without the XML declaration to avoid issues in SOAP body
        $dom_xpath = new \DOMXPath($wrappedDom);
        $root = $dom_xpath->query('/')->item(0)->firstChild;
        $xml = $wrappedDom->saveXML($root);

        // 8. Get SOAP client
        $client = self::getClient();

        // 9. Call AEAT web service using SoapVar to avoid XML declaration issues
        try {
            $soapVar = new \SoapVar($xml, XSD_ANYXML);
            $responseXml = $client->__soapCall('RegFactuSistemaFacturacion', [$soapVar]);
        } catch (\SoapFault $e) {
            // Handle SOAP faults gracefully
            error_log('SOAP Fault: ' . $e->getMessage());
            error_log('Xml enviado: '.PHP_EOL . $xml);
            error_log('Última petición SOAP: ' . $client->__getLastRequest());
            error_log('Última respuesta SOAP: ' . $client->__getLastResponse());
            error_log('ültima reuqest headers: ' . print_r($client->__getLastRequestHeaders(), true));
            error_log('última response headers: ' . print_r($client->__getLastResponseHeaders(), true));
            error_log(<<<TXT
| Code | Description                                                                           |
| ---- | ------------------------------------------------------------------------------------- |
| 100  | The SOAP request signature is not valid                                               |
| 101  | The SOAP request is empty                                                             |
| 102  | The SOAP request is not well-formed: SOAP Envelope not found                          |
| 103  | The SOAP request is not well-formed: SOAP Body not found                              |
| 104  | The SOAP request is not well-formed: SOAP Header not found                            |
| 106  | The certificate used in the SOAP signature is on a blocklist or is a test certificate |

TXT
);
//            error_log('Último XML enviado: ' . $signedXml);
            throw new \RuntimeException('Error calling AEAT service: ' . $e->getMessage());
        }

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
        $validation = $cancellation->validate();

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
        
        // Get the RegistroAnulacion XML from the cancellation using InvoiceSerializer
        $cancellationDom = InvoiceSerializer::toCancellationXml($cancellation);
        
        // Get the issuer information for the Cabecera
        $invoiceId = $cancellation->getInvoiceId();
        $nif = $invoiceId->issuerNif;
        $name = "Obligado Tributario"; // Placeholder for cancellations
        
        // Wrap the XML with the proper structure using InvoiceSerializer
        $wrappedDom = InvoiceSerializer::wrapXmlWithRegFactuStructure($cancellationDom, $nif, $name);
        
        // Get XML without the XML declaration to avoid issues in SOAP body
        $dom_xpath = new \DOMXPath($wrappedDom);
        $root = $dom_xpath->query('/')->item(0)->firstChild;
        $xml = $wrappedDom->saveXML($root);

        $signedXml = XmlSignerService::signXml(
            $xml,
            self::getConfig(self::CERT_PATH_KEY),
            self::getConfig(self::CERT_PASSWORD_KEY)
        );
        $client = self::getClient();

        // Envío como ANYXML (evita "object has no 'Cabecera' property")
        $soapVar = new \SoapVar($signedXml, XSD_ANYXML);
        $responseXml = $client->__soapCall('RegFactuSistemaFacturacion', [$soapVar]);

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
        
        // Get the XML from the query using InvoiceSerializer
        $queryDom = InvoiceSerializer::toQueryXml($query);
        $xml = $queryDom->saveXML();
        
        $client = self::getClient();

        // Enviar el XML literal
        $soapVar = new \SoapVar($xml, XSD_ANYXML);
        $responseXml = $client->__soapCall('ConsultaFactuSistemaFacturacion', [$soapVar]);

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
    )
    {
        $baseUrl = self::getConfig(self::QR_VERIFICATION_URL);

        return QrGeneratorService::generateQr($record, $baseUrl, $destination, $size, $engine);
    }

    // The XML helper methods (wrapXmlWithRegFactuStructure, buildInvoiceXml, buildCancellationXml, buildQueryXml)
    // have been moved to the InvoiceSerializer service
}
