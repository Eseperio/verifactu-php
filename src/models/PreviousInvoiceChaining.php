<?php
namespace eseperio\verifactu\models;

/**
 * Model representing chaining data with a previous invoice (EncadenamientoFacturaAnteriorType).
 * Used to link invoices in a chain for integrity verification.
 * Original schema: EncadenamientoFacturaAnteriorType
 * @see docs/aeat/esquemas/SuministroInformacion.xsd.xml
 */
class PreviousInvoiceChaining extends Model
{
    /**
     * Issuer NIF (IDEmisorFactura)
     * @var string
     */
    public $issuerNif;

    /**
     * Series and invoice number (NumSerieFactura)
     * @var string
     */
    public $seriesNumber;

    /**
     * Issue date (FechaExpedicionFactura), format YYYY-MM-DD
     * @var string
     */
    public $issueDate;

    /**
     * Previous invoice hash (Huella)
     * @var string
     */
    public $hash;

    /**
     * Returns validation rules for the chaining data.
     * @return array
     */
    public function rules()
    {
        return [
            [['issuerNif', 'seriesNumber', 'issueDate', 'hash'], 'required'],
            [['issuerNif', 'seriesNumber', 'issueDate', 'hash'], 'string'],
            ['issueDate', function($value) {
                // Checks for format YYYY-MM-DD (simple regex)
                return (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) ? true : 'Must be a valid date (YYYY-MM-DD).';
            }],
        ];
    }
}
