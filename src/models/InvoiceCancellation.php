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

    //Fix https://github.com/Eseperio/verifactu-php/issues/27
    public $issuerName;

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
     * Deprecated: Use InvoiceSerializer::toCancellationXml() instead.
     * 
     * @deprecated This method has been replaced by InvoiceSerializer::toCancellationXml()
     * @return \DOMDocument
     * @throws \Exception
     */
    public function toXml(): \DOMDocument
    {
        throw new \Exception(
            'This method is deprecated. Use InvoiceSerializer::toCancellationXml() instead. ' .
            'The XML generation has been moved to the InvoiceSerializer service.'
        );
    }
}
