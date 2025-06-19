<?php
namespace eseperio\verifactu\models;

use eseperio\verifactu\models\enums\YesNoType;
use eseperio\verifactu\models\enums\GeneratorType;

/**
 * Model representing an invoice cancellation ("Anulación").
 * Based on: RegistroAnulacion (SuministroInformacion.xsd.xml)
 * Original schema: RegistroAnulacionType
 * @see docs/aeat/esquemas/SuministroInformacion.xsd.xml
 */
class InvoiceCancellation extends InvoiceRecord
{
    /**
     * No previous record found indicator (SinRegistroPrevio, optional)
     * @var \eseperio\verifactu\models\enums\YesNoType|null
     */
    public $noPreviousRecord;

    /**
     * Previous rejection indicator (RechazoPrevio, optional)
     * @var \eseperio\verifactu\models\enums\YesNoType|null
     */
    public $previousRejection;

    /**
     * Generator (GeneradoPor, optional)
     * @var \eseperio\verifactu\models\enums\GeneratorType|null
     */
    public $generator;

    /**
     * Generator data (Generador, optional)
     * @var \eseperio\verifactu\models\LegalPerson|null
     */
    private $generatorData;

    /**
     * Get the generator data
     * @return \eseperio\verifactu\models\LegalPerson|null
     */
    public function getGeneratorData()
    {
        return $this->generatorData;
    }

    /**
     * Set the generator data
     * @param \eseperio\verifactu\models\LegalPerson|array $generatorData Generator data
     * @return $this
     */
    public function setGeneratorData($generatorData)
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
     * @return array
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            ['noPreviousRecord', function($value) {
                if ($value === null) return true;
                return ($value instanceof YesNoType) ? true : 'Must be an instance of YesNoType.';
            }],
            ['previousRejection', function($value) {
                if ($value === null) return true;
                return ($value instanceof YesNoType) ? true : 'Must be an instance of YesNoType.';
            }],
            ['generator', function($value) {
                if ($value === null) return true;
                return ($value instanceof GeneratorType) ? true : 'Must be an instance of GeneratorType.';
            }],
            ['generatorData', function($value) {
                if ($value === null) return true;
                return ($value instanceof LegalPerson) ? true : 'Must be an instance of LegalPerson.';
            }],
        ]);
    }

    /**
     * Serializes the invoice cancellation to XML.
     *
     * @return \DOMDocument The root element of this model's XML representation
     * @throws \DOMException
     */
    public function toXml()
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
                $idFacturaNode = $invoiceId->toXml($doc);
                $idFacturaNode->tagName = 'IDFactura';
                $root->appendChild($idFacturaNode);
            }
        }

        // RefExterna (optional)
        if (!empty($this->externalReference)) {
            $root->appendChild($doc->createElement('RefExterna', $this->externalReference));
        }

        // SinRegistroPrevio (optional)
        if (!empty($this->noPreviousRecord)) {
            $root->appendChild($doc->createElement('SinRegistroPrevio', $this->noPreviousRecord));
        }

        // RechazoPrevio (optional)
        if (!empty($this->previousRejection)) {
            $root->appendChild($doc->createElement('RechazoPrevio', $this->previousRejection));
        }

        // GeneradoPor (optional)
        if (!empty($this->generator)) {
            $root->appendChild($doc->createElement('GeneradoPor', $this->generator));
        }

        // Generador (optional)
        if (!empty($this->generatorData) && method_exists($this->generatorData, 'toXml')) {
            $generadorNode = $this->generatorData->toXml($doc);
            $generadorNode->tagName = 'Generador';
            $root->appendChild($generadorNode);
        }

        // Encadenamiento (required, must be set by the user)
        if (!empty($this->chaining) && method_exists($this->chaining, 'toXml')) {
            $encadenamientoNode = $this->chaining->toXml($doc);
            $encadenamientoNode->tagName = 'Encadenamiento';
            $root->appendChild($encadenamientoNode);
        }

        // SistemaInformatico (required)
        if (!empty($this->computerSystem) && method_exists($this->computerSystem, 'toXml')) {
            $sistemaNode = $this->computerSystem->toXml($doc);
            $sistemaNode->tagName = 'SistemaInformatico';
            $root->appendChild($sistemaNode);
        }

        // FechaHoraHusoGenRegistro (required, must be set by the user)
        if (!empty($this->generationDateTime)) {
            $root->appendChild($doc->createElement('FechaHoraHusoGenRegistro', $this->generationDateTime));
        }

        // TipoHuella (required, must be set by the user)
        if (!empty($this->hashType)) {
            $root->appendChild($doc->createElement('TipoHuella', $this->hashType));
        }

        // Huella (required, must be set by the user)
        if (!empty($this->hashValue)) {
            $root->appendChild($doc->createElement('Huella', $this->hashValue));
        }

        // ds:Signature (optional, to be added after signing)
        // Not included here, but the node can be appended externally if needed

        return $doc;
    }
}
