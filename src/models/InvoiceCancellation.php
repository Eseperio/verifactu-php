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
     * @param \DOMDocument $doc The DOM document to use for creating elements
     * @return \DOMElement The root element of this model's XML representation
     * @throws \DOMException
     */
    public function toXml(\DOMDocument $doc)
    {
        // Root node <RegistroAnulacion>
        $registroAnulacion = $doc->createElement('RegistroAnulacion');

        // <IDVersion>
        $registroAnulacion->appendChild($doc->createElement('IDVersion', $this->versionId));

        // <IDFacturaAnulada>
        $idFacturaAnulada = $doc->createElement('IDFacturaAnulada');
        $invoiceId = $this->getInvoiceId();
        $idFacturaAnulada->appendChild($doc->createElement('IDEmisorFacturaAnulada', $invoiceId->issuerNif));
        $idFacturaAnulada->appendChild($doc->createElement('NumSerieFacturaAnulada', $invoiceId->seriesNumber));
        $idFacturaAnulada->appendChild($doc->createElement('FechaExpedicionFacturaAnulada', $invoiceId->issueDate));
        $registroAnulacion->appendChild($idFacturaAnulada);

        // <RefExterna> (optional)
        if (!empty($this->externalRef)) {
            $registroAnulacion->appendChild($doc->createElement('RefExterna', $this->externalRef));
        }

        // <SinRegistroPrevio> (optional)
        if (!empty($this->noPreviousRecord)) {
            $registroAnulacion->appendChild($doc->createElement('SinRegistroPrevio', $this->noPreviousRecord));
        }

        // <RechazoPrevio> (optional)
        if (!empty($this->previousRejection)) {
            $registroAnulacion->appendChild($doc->createElement('RechazoPrevio', $this->previousRejection));
        }

        // <GeneradoPor> (optional)
        if (!empty($this->generator)) {
            $registroAnulacion->appendChild($doc->createElement('GeneradoPor', $this->generator));
        }

        // <Generador> (optional, array)
        $generatorData = $this->getGeneratorData();
        if (!empty($generatorData)) {
            $generador = $doc->createElement('Generador');
            if (method_exists($generatorData, 'toXml')) {
                $generatorElement = $generatorData->toXml($doc);
                foreach ($generatorElement->childNodes as $child) {
                    $importedNode = $doc->importNode($child, true);
                    $generador->appendChild($importedNode);
                }
            } else {
                // Fallback to manual serialization
                if (isset($generatorData->nif)) {
                    $generador->appendChild($doc->createElement('NIF', $generatorData->nif));
                }
                if (isset($generatorData->name)) {
                    $generador->appendChild($doc->createElement('NombreRazon', $generatorData->name));
                }
            }
            $registroAnulacion->appendChild($generador);
        }

        // <Encadenamiento> (required, array)
        $chaining = $this->getChaining();
        if (!empty($chaining)) {
            $encadenamiento = $doc->createElement('Encadenamiento');
            if (isset($chaining['previousHash'])) {
                $encadenamiento->appendChild($doc->createElement('RegistroAnterior', $chaining['previousHash']));
            } else {
                $encadenamiento->appendChild($doc->createElement('PrimerRegistro', 'S'));
            }
            $registroAnulacion->appendChild($encadenamiento);
        }

        // <SistemaInformatico> (required, array)
        $systemInfo = $this->getSystemInfo();
        if (!empty($systemInfo)) {
            $sistema = $doc->createElement('SistemaInformatico');
            foreach ($systemInfo as $key => $value) {
                $sistema->appendChild($doc->createElement($key, $value));
            }
            $registroAnulacion->appendChild($sistema);
        }

        // <FechaHoraHusoGenRegistro>
        $registroAnulacion->appendChild($doc->createElement('FechaHoraHusoGenRegistro', $this->recordTimestamp));

        // <TipoHuella>
        $registroAnulacion->appendChild($doc->createElement('TipoHuella', $this->hashType));

        // <Huella>
        $registroAnulacion->appendChild($doc->createElement('Huella', $this->hash));

        return $registroAnulacion;
    }
}
