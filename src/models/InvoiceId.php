<?php
namespace eseperio\verifactu\models;

/**
 * Model representing invoice identification data (<IDFactura> node).
 * Fields: issuer NIF, series+number, issue date.
 */
class InvoiceId extends Model
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
     * Returns validation rules for the invoice ID.
     * @return array
     */
    public function rules()
    {
        return [
            [['issuerNif', 'seriesNumber', 'issueDate'], 'required'],
            [['issuerNif', 'seriesNumber', 'issueDate'], 'string'],
            ['issueDate', function($value) {
                // Checks for format YYYY-MM-DD (simple regex)
                return (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) ? true : 'Must be a valid date (YYYY-MM-DD).';
            }],
        ];
    }
}
