<?php
namespace eseperio\verifactu\models;

/**
 * Model representing an invoice submission ("Alta").
 * Based on: RegistroAlta (SuministroInformacion.xsd.xml)
 */
class InvoiceSubmission extends InvoiceRecord
{
    /**
     * Issuer company or person name (NombreRazonEmisor)
     * @var string
     */
    public $issuerName;

    /**
     * Rectification data (FacturasRectificadas, FacturasSustituidas, etc, optional)
     * @var array
     */
    public $rectificationData;

    /**
     * Invoice type (TipoFactura)
     * @var string
     */
    public $invoiceType;

    /**
     * Rectification type (TipoRectificativa, optional)
     * @var string
     */
    public $rectificationType;

    /**
     * Recipients list (Destinatarios, optional)
     * @var array
     */
    public $recipients;

    /**
     * Tax breakdown (Desglose)
     * @var array
     */
    public $breakdown;

    /**
     * Tax amount (CuotaTotal)
     * @var float
     */
    public $taxAmount;

    /**
     * Total invoice amount (ImporteTotal)
     * @var float
     */
    public $totalAmount;

    /**
     * Returns validation rules for invoice submission.
     * Merges parent rules with specific rules for invoice submission.
     * @return array
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            [['issuerName', 'invoiceType', 'breakdown', 'taxAmount', 'totalAmount'], 'required'],
            [['issuerName', 'invoiceType', 'rectificationType'], 'string'],
            [['rectificationData', 'recipients', 'breakdown'], 'array'],
            [['taxAmount', 'totalAmount'], function($value) {
                return (is_float($value) || is_int($value)) ? true : 'Must be a number.';
            }]
        ]);
    }
}
