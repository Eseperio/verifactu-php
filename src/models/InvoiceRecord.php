<?php

namespace eseperio\verifactu\models;

/**
 * Abstract base class for invoice records (submissions and cancellations).
 * Contains common fields and validation rules for RegistroAlta and RegistroAnulacion.
 */
abstract class InvoiceRecord extends Model
{
    /**
     * Version identifier (IDVersion)
     * Default value should match the XSD schema version in use.
     * @see docs/aeat/2025-06/esquemas/SuministroInformacion.xsd.xml
     * @var string
     */
    public $versionId = '1.0';

    /**
     * Invoice identification data (<IDFactura> or <IDFacturaAnulada>)
     * @var array
     */
    public $invoiceId;

    /**
     * External reference (RefExterna, optional)
     * @var string
     */
    public $externalRef;

    /**
     * Chaining data (Encadenamiento), for hash linkage with previous record
     * @var array
     */
    public $chaining;

    /**
     * System information (SistemaInformatico)
     * @var array
     */
    public $systemInfo;

    /**
     * Record timestamp with timezone (FechaHoraHusoGenRegistro)
     * @var string
     */
    public $recordTimestamp;

    /**
     * Hash type (TipoHuella)
     * Always \"SHA-256\" as per AEAT specification.
     * @var string
     */
    public $hashType = 'SHA-256';

    /**
     * Record hash (Huella)
     * @var string
     */
    public $hash;

    /**
     * XML Signature block (optional, for signed XML)
     * @var string
     */
    public $xmlSignature;

    /**
     * Returns validation rules for the base invoice record.
     * Child classes should merge with their own rules.
     * @return array
     */
    public function rules()
    {
        return [
            [['versionId', 'invoiceId', 'chaining', 'systemInfo', 'recordTimestamp', 'hashType', 'hash'], 'required'],
            [['versionId', 'recordTimestamp', 'hashType', 'hash', 'externalRef', 'xmlSignature'], 'string'],
            [['invoiceId', 'chaining', 'systemInfo'], 'array'],
            [['externalRef', 'xmlSignature'], function ($value) {
                return (is_null($value) || is_string($value)) ? true : 'Must be string or null.';
            }],
        ];
    }
}
