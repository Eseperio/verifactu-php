<?php
namespace eseperio\verifactu\models;

/**
 * Model representing an invoice cancellation ("Anulación").
 * Based on: RegistroAnulacion (SuministroInformacion.xsd.xml)
 */
class InvoiceCancellation extends InvoiceRecord
{
    /**
     * No previous record found indicator (SinRegistroPrevio, optional)
     * @var string
     */
    public $noPreviousRecord;

    /**
     * Previous rejection indicator (RechazoPrevio, optional)
     * @var string
     */
    public $previousRejection;

    /**
     * Generator (GeneradoPor, optional)
     * @var string
     */
    public $generator;

    /**
     * Generator data (Generador, optional)
     * @var array
     */
    public $generatorData;

    /**
     * Returns validation rules for invoice cancellation.
     * Merges parent rules with specific rules for cancellation.
     * @return array
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            [['noPreviousRecord', 'previousRejection', 'generator'], 'string'],
            [['generatorData'], 'array'],
        ]);
    }
}
