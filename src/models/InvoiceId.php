<?php
namespace eseperio\verifactu\models;

/**
 * Model representing invoice identification data (<IDFactura> node).
 * Fields: issuer NIF, series+number, issue date.
 * Original schema: IDFacturaType
 * @see docs/aeat/esquemas/SuministroInformacion.xsd.xml
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

    /**
     * Serializes the invoice ID to XML.
     * 
     * @param \DOMDocument $doc The XML document to use for creating elements
     * @return \DOMElement The root element of this model's XML representation
     */
    public function toXml(\DOMDocument $doc)
    {
        $root = $doc->createElement('IDFactura');

        // IDEmisorFactura (required)
        $root->appendChild($doc->createElement('IDEmisorFactura', $this->issuerNif));

        // NumSerieFactura (required)
        $root->appendChild($doc->createElement('NumSerieFactura', $this->seriesNumber));

        // FechaExpedicionFactura (required)
        $root->appendChild($doc->createElement('FechaExpedicionFactura', $this->issueDate));

        return $root;
    }
}
