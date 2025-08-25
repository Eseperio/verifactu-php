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

        // 3. Get the RegistroAlta XML from the invoice via serializer
        $invoiceDom = InvoiceSerializer::toInvoiceXml($invoice);

        // die($invoiceDom->saveXML()); // eliminar debug
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
        
        // 7. Wrap the signed XML with the proper structure
        $wrappedDom = self::wrapXmlWithRegFactuStructure($signedDom, $nif, $name);
        
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
        $xml = self::buildCancellationXml($cancellation);

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
        $xml = self::buildQueryXml($query);
        $client = self::getClient();

        // Igual para consulta: enviar el XML literal
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

    /**
     * Wraps an XML element in the proper RegFactuSistemaFacturacion structure with Cabecera.
     *
     * NOTE:
     * - Uses createElementNS everywhere.
     * - Cabecera is in sfLR NS.
     * - sf:* elements are in sf NS.
     * - Consider removing RemisionRequerimiento when calling Veri*factu endpoint.
     */
    protected static function wrapXmlWithRegFactuStructure(\DOMDocument $doc, string $nif, string $name): \DOMDocument
    {
        $NS_SFLR = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';
        $NS_SF   = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';
        $NS_DS   = 'http://www.w3.org/2000/09/xmldsig#';

        $newDoc = new \DOMDocument('1.0', 'UTF-8');
        $newDoc->formatOutput = true;

        // root: sfLR:RegFactuSistemaFacturacion
        $root = $newDoc->createElementNS($NS_SFLR, 'sfLR:RegFactuSistemaFacturacion');
        // declare other namespaces on root
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sf', $NS_SF);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', $NS_DS);
        $newDoc->appendChild($root);

        // sfLR:Cabecera (element in sfLR NS, type CabeceraType from sf)
        $cabecera = $newDoc->createElementNS($NS_SFLR, 'sfLR:Cabecera');
        $root->appendChild($cabecera);

        // sf:ObligadoEmision
        $obligadoEmision = $newDoc->createElementNS($NS_SF, 'sf:ObligadoEmision');
        $cabecera->appendChild($obligadoEmision);
        $obligadoEmision->appendChild($newDoc->createElementNS($NS_SF, 'sf:NIF', $nif));
        $obligadoEmision->appendChild($newDoc->createElementNS($NS_SF, 'sf:NombreRazon', $name));

        // sf:Representante (optional)
        $representante = $newDoc->createElementNS($NS_SF, 'sf:Representante');
        $representante->appendChild($newDoc->createElementNS($NS_SF, 'sf:NIF', $nif));
        $representante->appendChild($newDoc->createElementNS($NS_SF, 'sf:NombreRazon', $name));
        $cabecera->appendChild($representante);

        // sf:RemisionRequerimiento (only for NO-VERIFACTU flows; remove for Veri*factu)
        $remReq = $newDoc->createElementNS($NS_SF, 'sf:RemisionRequerimiento');
        $remReq->appendChild($newDoc->createElementNS($NS_SF, 'sf:RefRequerimiento', 'TEST' . date('YmdHis')));
        $remReq->appendChild($newDoc->createElementNS($NS_SF, 'sf:FinRequerimiento', 'S'));
        $cabecera->appendChild($remReq);

        // sfLR:RegistroFactura
        $registroFactura = $newDoc->createElementNS($NS_SFLR, 'sfLR:RegistroFactura');
        $root->appendChild($registroFactura);

        // import original payload (must be sf:RegistroAlta or sf:RegistroAnulacion in SF NS)
        $imported = $newDoc->importNode($doc->documentElement, true);
        $registroFactura->appendChild($imported);

        return $newDoc;
    }

    /**
     * Serializes an InvoiceSubmission to AEAT-compliant XML.
     * @return string XML string
     * @throws \DOMException
     */
    protected static function buildInvoiceXml(InvoiceSubmission $invoice): string|false
    {
        // Get the RegistroAlta XML from the invoice
        $invoiceDom = $invoice->toXml();

        // Get the issuer information for the Cabecera
        $invoiceId = $invoice->getInvoiceId();
        $nif = $invoiceId->issuerNif;
        $name = $invoice->issuerName;

        // Wrap the XML with the proper structure
        $wrappedDom = self::wrapXmlWithRegFactuStructure($invoiceDom, $nif, $name);

        return $wrappedDom->saveXML();
    }

    /**
     * Serializes an InvoiceCancellation to AEAT-compliant XML.
     * @return string XML string
     * @throws \DOMException
     */
    protected static function buildCancellationXml(InvoiceCancellation $cancellation): string|false
    {
        // Get the RegistroAnulacion XML from the cancellation
        $cancellationDom = $cancellation->toXml();

        // Get the issuer information for the Cabecera
        $invoiceId = $cancellation->getInvoiceId();
        $nif = $invoiceId->issuerNif;

        // For cancellations, we don't have issuerName directly, so we'll use a placeholder or try to get it
        // This should be improved with a more accurate way to get the issuer name for cancellations
        $name = "Obligado Tributario"; // Placeholder

        // Wrap the XML with the proper structure
        $wrappedDom = self::wrapXmlWithRegFactuStructure($cancellationDom, $nif, $name);

        return $wrappedDom->saveXML();
    }

    /**
     * Serializes an InvoiceQuery to AEAT-compliant XML.
     * @return string XML string
     * @throws \DOMException
     */
    protected static function buildQueryXml(InvoiceQuery $query): string|false
    {
        $queryDom = $query->toXml();

        // For queries, we don't need to wrap with RegFactuSistemaFacturacion
        // The query structure is already complete

        return $queryDom->saveXML();
    }
}
