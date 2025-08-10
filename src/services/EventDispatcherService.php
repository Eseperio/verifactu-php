<?php

declare(strict_types=1);

namespace eseperio\verifactu\services;

use eseperio\verifactu\models\EventRecord;
use eseperio\verifactu\models\InvoiceResponse;

/**
 * Service responsible for submitting system events (RegistroEvento)
 * to the AEAT Verifactu event endpoint.
 */
class EventDispatcherService
{
    /**
     * Dispatches an event to AEAT, signing and sending the event XML,
     * then parsing the response.
     *
     * @param array $config (Required: wsdl, certPath, certPassword)
     * @return InvoiceResponse
     * @throws \SoapFault
     */
    public static function dispatch(EventRecord $event, array $config)
    {
        // 1. Validate event
        $validation = $event->validate();

        if ($validation !== true) {
            throw new \InvalidArgumentException('EventRecord validation failed: ' . print_r($validation, true));
        }

        // 2. Serialize event to XML
        $xml = self::buildEventXml($event);

        // 3. Sign XML
        $signedXml = XmlSignerService::signXml($xml, $config['certPath'], $config['certPassword']);

        // 4. Create SOAP client
        $client = SoapClientFactoryService::createSoapClient($config['wsdl'], $config['certPath'], $config['certPassword']);

        // 5. Call AEAT web service for event submission
        $params = ['RegistroEvento' => $signedXml];
        $responseXml = $client->__soapCall('EventosSIF', [$params]);

        // 6. Parse AEAT response (same as for invoice responses)
        return ResponseParserService::parseInvoiceResponse($responseXml);
    }

    /**
     * Serializes an EventRecord to AEAT-compliant RegistroEvento XML.
     *
     * @return string XML string
     */
    protected static function buildEventXml(EventRecord $event): string|false
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        // Root node <RegistroEvento>
        $registroEvento = $doc->createElement('RegistroEvento');

        // <IDVersion>
        $registroEvento->appendChild($doc->createElement('IDVersion', $event->versionId));

        // <Evento> (required, array)
        if (!empty($event->eventData) && is_array($event->eventData)) {
            $evento = $doc->createElement('Evento');

            foreach ($event->eventData as $key => $value) {
                // Si el valor es un array anidado, expandir (ejemplo para subnodos)
                if (is_array($value)) {
                    $subNode = $doc->createElement($key);

                    foreach ($value as $subKey => $subValue) {
                        $subNode->appendChild($doc->createElement($subKey, $subValue));
                    }
                    $evento->appendChild($subNode);
                } else {
                    $evento->appendChild($doc->createElement($key, $value));
                }
            }
            $registroEvento->appendChild($evento);
        }

        $doc->appendChild($registroEvento);

        return $doc->saveXML();
    }
}
