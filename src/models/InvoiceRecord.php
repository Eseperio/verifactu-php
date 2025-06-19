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
     * @var \eseperio\verifactu\models\InvoiceId
     */
    private $invoiceId;

    /**
     * External reference (RefExterna, optional)
     * @var string
     */
    public $externalRef;

    /**
     * Chaining data (Encadenamiento), for hash linkage with previous record
     * @var array
     */
    private $chaining;

    /**
     * System information (SistemaInformatico)
     * @var array
     */
    private $systemInfo;

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
     * Get the invoice ID
     * @return \eseperio\verifactu\models\InvoiceId
     */
    public function getInvoiceId()
    {
        return $this->invoiceId;
    }

    /**
     * Set the invoice ID
     * @param \eseperio\verifactu\models\InvoiceId|array $invoiceId InvoiceId object or array with issuerNif, seriesNumber, and issueDate
     * @return $this
     */
    public function setInvoiceId($invoiceId)
    {
        if (is_array($invoiceId)) {
            $id = new InvoiceId();
            $id->issuerNif = $invoiceId['issuerNif'] ?? null;
            $id->seriesNumber = $invoiceId['seriesNumber'] ?? null;
            $id->issueDate = $invoiceId['issueDate'] ?? null;
            $this->invoiceId = $id;
        } else {
            $this->invoiceId = $invoiceId;
        }
        return $this;
    }

    /**
     * Get the chaining data
     * @return array
     */
    public function getChaining()
    {
        return $this->chaining;
    }

    /**
     * Set the chaining data
     * @param string $previousInvoice Previous invoice series and number
     * @param string $previousHash Previous invoice hash
     * @return $this
     */
    public function setChaining($previousInvoice, $previousHash)
    {
        $this->chaining = [
            'previousInvoice' => $previousInvoice,
            'previousHash' => $previousHash
        ];
        return $this;
    }

    /**
     * Get the system information
     * @return array
     */
    public function getSystemInfo()
    {
        return $this->systemInfo;
    }

    /**
     * Set the system information
     * @param string $system System name
     * @param string $version System version
     * @return $this
     */
    public function setSystemInfo($system, $version)
    {
        $this->systemInfo = [
            'system' => $system,
            'version' => $version
        ];
        return $this;
    }

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
