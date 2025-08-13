<?php

declare(strict_types=1);

namespace eseperio\verifactu\models;

use eseperio\verifactu\models\enums\GeneratorType;
use eseperio\verifactu\models\enums\YesNoType;

/**
 * Model representing an invoice cancellation ("Anulación").
 * Based on: RegistroAnulacion (SuministroInformacion.xsd.xml)
 * Original schema: RegistroAnulacionType.
 * @see docs/aeat/esquemas/SuministroInformacion.xsd.xml
 */
class InvoiceCancellation extends InvoiceRecord
{
    public $externalReference;
    /**
     * No previous record found indicator (SinRegistroPrevio, optional).
     * @var YesNoType|null
     */
    public $noPreviousRecord;

    /**
     * Previous rejection indicator (RechazoPrevio, optional).
     * @var YesNoType|null
     */
    public $previousRejection;

    /**
     * Generator (GeneradoPor, optional).
     * @var GeneratorType|null
     */
    public $generator;

    /**
     * Generator data (Generador, optional).
     * @var LegalPerson|null
     */
    private $generatorData;

    /**
     * Get the generator data.
     * @return LegalPerson|null
     */
    public function getGeneratorData()
    {
        return $this->generatorData;
    }

    /**
     * Set the generator data.
     * @param LegalPerson|array $generatorData Generator data
     * @return $this
     */
    public function setGeneratorData($generatorData): static
    {
        if (is_array($generatorData)) {
            $legalPerson = new LegalPerson();
            $legalPerson->name = $generatorData['name'] ?? null;

            if (isset($generatorData['nif'])) {
                $legalPerson->nif = $generatorData['nif'];
            } elseif (isset($generatorData['otherId'])) {
                $legalPerson->setOtherId($generatorData['otherId']);
            }

            $this->generatorData = $legalPerson;
        } else {
            $this->generatorData = $generatorData;
        }

        return $this;
    }

    /**
     * Returns validation rules for invoice cancellation.
     * Merges parent rules with specific rules for cancellation.
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            ['noPreviousRecord', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return ($value instanceof YesNoType) ? true : 'Must be an instance of YesNoType.';
            }],
            ['previousRejection', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return ($value instanceof YesNoType) ? true : 'Must be an instance of YesNoType.';
            }],
            ['generator', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return ($value instanceof GeneratorType) ? true : 'Must be an instance of GeneratorType.';
            }],
            ['generatorData', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return ($value instanceof LegalPerson) ? true : 'Must be an instance of LegalPerson.';
            }],
        ]);
    }

    /**
     * Helper method to rename a DOM element by creating a new one with the desired tag name
     * and copying all children and attributes. This works around PHP 8.3's read-only tagName property.
     *
     * @param \DOMDocument $doc The document
     * @param \DOMElement $originalNode The original node to rename
     * @param string $newTagName The new tag name
     * @return \DOMElement The new element with the desired tag name
     */
    private function renameElement(\DOMDocument $doc, \DOMElement $originalNode, string $newTagName)
    {
        // Create new element with correct tag name
        $newElement = $doc->createElement($newTagName);

        // Copy all child nodes
        foreach ($originalNode->childNodes as $child) {
            $newElement->appendChild($child->cloneNode(true));
        }

        // Copy all attributes
        foreach ($originalNode->attributes as $attr) {
            $newElement->setAttribute($attr->nodeName, $attr->nodeValue);
        }

        return $newElement;
    }

    /**
     * Serializes the invoice cancellation to XML.
     *
     * @return \DOMDocument The root element of this model's XML representation
     * @throws \DOMException
     */
    public function toXml(): \DOMDocument
    {
        // Create the XML document
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        // Create root element: RegistroAnulacion
        $root = $doc->createElement('RegistroAnulacion');
        $doc->appendChild($root);

        // IDVersion (required, hardcoded as '1.0')
        $idVersion = $doc->createElement('IDVersion', '1.0');
        $root->appendChild($idVersion);

        // IDFactura (required)
        if (method_exists($this, 'getInvoiceId')) {
            $invoiceId = $this->getInvoiceId();

            if (method_exists($invoiceId, 'toXml')) {
                $originalNode = $invoiceId->toXml($doc);
                $idFacturaNode = $this->renameElement($doc, $originalNode, 'IDFactura');
                $root->appendChild($idFacturaNode);
            }
        }

        // RefExterna (optional)
        if (!empty($this->externalReference)) {
            $root->appendChild($doc->createElement('RefExterna', $this->externalReference));
        }

        // SinRegistroPrevio (optional)
        if (!empty($this->noPreviousRecord)) {
            $root->appendChild($doc->createElement('SinRegistroPrevio', $this->noPreviousRecord?->value));
        }

        // RechazoPrevio (optional)
        if (!empty($this->previousRejection)) {
            $root->appendChild($doc->createElement('RechazoPrevio', $this->previousRejection?->value));
        }

        // GeneradoPor (optional)
        if (!empty($this->generator)) {
            $root->appendChild($doc->createElement('GeneradoPor', $this->generator?->value));
        }

        // Generador (optional)
        if (!empty($this->generatorData) && method_exists($this->generatorData, 'toXml')) {
            $originalNode = $this->generatorData->toXml($doc);
            $generadorNode = $this->renameElement($doc, $originalNode, 'Generador');
            $root->appendChild($generadorNode);
        }

        // Encadenamiento (required, must be set by the user)
        if (!empty($this->chaining) && method_exists($this->chaining, 'toXml')) {
            $originalNode = $this->chaining->toXml($doc);
            $encadenamientoNode = $this->renameElement($doc, $originalNode, 'Encadenamiento');
            $root->appendChild($encadenamientoNode);
        }

        // SistemaInformatico (required)
        if (!empty($this->systemInfo) && method_exists($this->systemInfo, 'toXml')) {
            $originalNode = $this->systemInfo->toXml($doc);
            $sistemaNode = $this->renameElement($doc, $originalNode, 'SistemaInformatico');
            $root->appendChild($sistemaNode);
        }

        // FechaHoraHusoGenRegistro (required, must be set by the user)
        if (!empty($this->recordTimestamp)) {
            $root->appendChild($doc->createElement('FechaHoraHusoGenRegistro', $this->recordTimestamp));
        }

        // TipoHuella (required, must be set by the user)
        if (!empty($this->hashType)) {
            $root->appendChild($doc->createElement('TipoHuella', $this->hashType?->value));
        }

        // Huella (required, must be set by the user)
        if (!empty($this->hash)) {
            $root->appendChild($doc->createElement('Huella', $this->hash));
        }

        // ds:Signature (optional, to be added after signing)
        // Not included here, but the node can be appended externally if needed

        return $doc;
    }
}
