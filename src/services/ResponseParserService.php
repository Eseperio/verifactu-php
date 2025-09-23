<?php

declare(strict_types=1);

namespace eseperio\verifactu\services;

use eseperio\verifactu\dictionaries\ErrorRegistry;
use eseperio\verifactu\models\InvoiceResponse;
use eseperio\verifactu\models\QueryResponse;

/**
 * Service responsible for parsing AEAT SOAP/XML responses into
 * strongly-typed PHP model objects and mapping errors.
 */
class ResponseParserService
{
    /**
     * Parses an XML response string from AEAT for invoice registration/cancellation.
     *
     * @param string $xmlResponse
     */
    public static function parseInvoiceResponse($xmlResponse): InvoiceResponse
    {
        // Set internal errors handling to throw exceptions on XML parsing errors
        $previousErrorSetting = libxml_use_internal_errors(true);
        
        $doc = new \DOMDocument();
        $result = $doc->loadXML($xmlResponse);
        
        // If loading failed, throw an exception
        if ($result === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
            
            $errorMsg = 'XML parsing failed';
            if (!empty($errors)) {
                $firstError = reset($errors);
                $errorMsg .= ': ' . $firstError->message . ' at line ' . $firstError->line;
            }
            
            throw new \RuntimeException($errorMsg);
        }
        
        // Restore previous error handling setting
        libxml_use_internal_errors($previousErrorSetting);

        // Map DOM to model
        $model = new InvoiceResponse();

        // Example: you can use DOMXPath or SimpleXML for real field mapping
        $xpath = new \DOMXPath($doc);

        // CSV (if present)
        $csvNode = self::firstNode($xpath, '//*[local-name()="CSV"]');

        if ($csvNode) {
            $model->csv = trim((string) $csvNode->nodeValue);
        }

        // Header
        $headerNode = self::firstNode($xpath, '//*[local-name()="Cabecera"]');

        if ($headerNode) {
            $model->header = self::xmlNodeToArray($headerNode);
        }

        // Wait time (TiempoEsperaEnvio)
        $waitNode = self::firstNode($xpath, '//*[local-name()="TiempoEsperaEnvio"]');

        if ($waitNode) {
            $model->waitTime = trim((string) $waitNode->nodeValue);
        }

        // Submission status (EstadoEnvio)
        $statusNode = self::firstNode($xpath, '//*[local-name()="EstadoEnvio"]');

        if ($statusNode) {
            $model->submissionStatus = trim((string) $statusNode->nodeValue);
        }

        // SubmissionData
        $dataNode = self::firstNode($xpath, '//*[local-name()="DatosPresentacion"]');

        if ($dataNode) {
            $model->submissionData = self::xmlNodeToArray($dataNode);
        }

        // Line responses (RespuestaLinea)
        $model->lineResponses = [];

        foreach ($xpath->query('//*[local-name()="RespuestaLinea"]') as $lineNode) {
            // Extrae los campos según el XSD
            $line = [];
            foreach (['IDFactura', 'Operacion', 'RefExterna', 'EstadoRegistro', 'CodigoErrorRegistro', 'DescripcionErrorRegistro', 'RegistroDuplicado'] as $field) {
                $fieldNode = self::firstNode(
                    new \DOMXPath($lineNode->ownerDocument),
                    './/*[local-name()="' . $field . '"]'
                );
                if ($fieldNode) {
                    if (in_array($field, ['IDFactura', 'RegistroDuplicado'])) {
                        $line[$field] = self::xmlNodeToArray($fieldNode);
                    } else {
                        $line[$field] = trim((string)$fieldNode->nodeValue);
                    }
                } else {
                    $line[$field] = null;
                }
            }
            // Map AEAT error codes to human-readable messages
            if (isset($line['CodigoErrorRegistro']) && $line['CodigoErrorRegistro'] !== null) {
                $line['ErrorDescription'] = ErrorRegistry::getErrorMessage($line['CodigoErrorRegistro']);
            }
            $model->lineResponses[] = $line;
        }

        return $model;
    }

    /**
     * Parses an XML response string from AEAT for queries.
     *
     * @param string $xmlResponse
     */
    public static function parseQueryResponse($xmlResponse): QueryResponse
    {
        // Set internal errors handling to throw exceptions on XML parsing errors
        $previousErrorSetting = libxml_use_internal_errors(true);
        
        $doc = new \DOMDocument();
        $result = $doc->loadXML($xmlResponse);
        
        // If loading failed, throw an exception
        if ($result === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
            
            $errorMsg = 'XML parsing failed';
            if (!empty($errors)) {
                $firstError = reset($errors);
                $errorMsg .= ': ' . $firstError->message . ' at line ' . $firstError->line;
            }
            
            throw new \RuntimeException($errorMsg);
        }
        
        // Restore previous error handling setting
        libxml_use_internal_errors($previousErrorSetting);

        $model = new QueryResponse();
        $xpath = new \DOMXPath($doc);

        $headerNode = self::firstNode($xpath, '//*[local-name()="Cabecera"]');

        if ($headerNode) {
            $model->header = self::xmlNodeToArray($headerNode);
        }

        $periodNode = self::firstNode($xpath, '//*[local-name()="PeriodoImputacion"]');

        if ($periodNode) {
            $model->period = self::xmlNodeToArray($periodNode);
        }

        $model->paginationIndicator = self::findText($xpath, '//*[local-name()="IndicadorPaginacion"]');
        $model->queryResult = self::findText($xpath, '//*[local-name()="ResultadoConsulta"]');

        $model->foundRecords = [];

        foreach ($xpath->query('//*[local-name()="RegistroRespuestaConsultaFactuSistemaFacturacion"]') as $recordNode) {
            $model->foundRecords[] = self::xmlNodeToArray($recordNode);
        }

        $paginationKeyNode = self::firstNode($xpath, '//*[local-name()="ClavePaginacion"]');

        if ($paginationKeyNode) {
            $model->paginationKey = self::xmlNodeToArray($paginationKeyNode);
        }

        return $model;
    }

    /**
     * Converts a DOMNode and its children to a PHP associative array.
     *
     * @param \DOMNode $node
     */
    protected static function xmlNodeToArray($node): string|array
    {
        $result = [];

        if ($node->hasChildNodes()) {
            $hasElementNodes = false;
            $textContent = '';
            
            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $hasElementNodes = true;
                    $key = $child->localName ?: $child->nodeName;
                    $result[$key] = self::xmlNodeToArray($child);
                } elseif ($child->nodeType === XML_TEXT_NODE) {
                    $textContent .= $child->nodeValue;
                }
            }
            
            // If we only have text nodes (no element nodes), return the trimmed text content
            if (!$hasElementNodes && !empty(trim($textContent))) {
                return trim($textContent);
            }
        }

        return $result;
    }

    /**
     * Helper for quickly fetching text content of a single XML node via XPath.
     *
     * @param \DOMXPath $xpath
     * @param string $query
     */
    protected static function findText($xpath, $query): ?string
    {
        $node = self::firstNode($xpath, $query);

        return $node ? trim((string) $node->nodeValue) : null;
    }

    /**
     * Returns the first DOMNode matched by the XPath query, or null if none.
     */
    protected static function firstNode(\DOMXPath $xpath, string $query): ?\DOMNode
    {
        $nodes = $xpath->query($query);
        if ($nodes === false) {
            return null;
        }
        foreach ($nodes as $n) {
            return $n;
        }
        return null;
    }
}
