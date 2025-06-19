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
     * Example: 'Acme Corp S.L.'
     * @var string
     */
    public $issuerName;

    /**
     * Rectification data for invoices (FacturasRectificadas, FacturasSustituidas, optional).
     * Structure:
     *   [
     *      'rectified' => [ // FacturasRectificadas
     *          [
     *              'issuerNif' => 'B12345678', // NIF of the original invoice issuer
     *              'seriesNumber' => 'A-2023/007', // Series and number of rectified invoice
     *              'issueDate' => '2023-09-12' // Date of rectified invoice (YYYY-MM-DD)
     *          ],
     *          ...
     *      ],
     *      'substituted' => [ // FacturasSustituidas
     *          [
     *              'issuerNif' => 'B12345678', // NIF of the original invoice issuer
     *              'seriesNumber' => 'B-2023/010', // Series and number of substituted invoice
     *              'issueDate' => '2023-08-15' // Date of substituted invoice (YYYY-MM-DD)
     *          ],
     *          ...
     *      ]
     *   ]
     * Each list can be empty or omitted if not applicable.
     * @var array
     */
    private $rectificationData = [];

    /**
     * Invoice type (TipoFactura)
     * E.g. 'F1', 'R1', etc. (See AEAT catalog)
     * @var string
     */
    public $invoiceType;

    /**
     * Rectification type (TipoRectificativa, optional)
     * Only for rectifying invoices. Example: 'S' (substitute), 'I' (increase), 'D' (decrease), etc.
     * @var string
     */
    public $rectificationType;

    /**
     * Recipients list (Destinatarios, optional).
     * Each element must be an associative array with at least the recipient's NIF, e.g.:
     *   [
     *     ['nif' => '12345678Z', 'name' => 'Customer S.A.'],
     *     ...
     *   ]
     * @var array
     */
    private $recipients = [];

    /**
     * Tax breakdown (Desglose). Array of tax/fee details applied to the invoice.
     * Example structure:
     *   [
     *     [
     *       'rate' => 21.0,        // Tax rate (TipoImpositivo)
     *       'base' => 100.00,      // Taxable base (BaseImponibleOimporteNoSujeto)
     *       'amount' => 21.00      // Tax amount (CuotaRepercutida)
     *     ],
     *     ...
     *   ]
     * @var array
     */
    private $breakdown = [];

    /**
     * Tax amount (CuotaTotal). Total amount of taxes applied to the invoice.
     * @var float
     */
    public $taxAmount;

    /**
     * Total invoice amount (ImporteTotal). Final total to be paid for the invoice.
     * @var float
     */
    public $totalAmount;

    /**
     * Get the rectification data
     * @return array
     */
    public function getRectificationData()
    {
        return $this->rectificationData;
    }

    /**
     * Set the rectification data
     * @param array $rectificationData Array of rectification data
     * @return $this
     */
    public function setRectificationData($rectificationData)
    {
        $this->rectificationData = $rectificationData;
        return $this;
    }

    /**
     * Add a rectified invoice
     * @param string $issuerNif NIF of the original invoice issuer
     * @param string $seriesNumber Series and number of rectified invoice
     * @param string $issueDate Date of rectified invoice (YYYY-MM-DD)
     * @return $this
     */
    public function addRectifiedInvoice($issuerNif, $seriesNumber, $issueDate)
    {
        if (!isset($this->rectificationData['rectified'])) {
            $this->rectificationData['rectified'] = [];
        }

        $this->rectificationData['rectified'][] = [
            'issuerNif' => $issuerNif,
            'seriesNumber' => $seriesNumber,
            'issueDate' => $issueDate
        ];

        return $this;
    }

    /**
     * Add a substituted invoice
     * @param string $issuerNif NIF of the original invoice issuer
     * @param string $seriesNumber Series and number of substituted invoice
     * @param string $issueDate Date of substituted invoice (YYYY-MM-DD)
     * @return $this
     */
    public function addSubstitutedInvoice($issuerNif, $seriesNumber, $issueDate)
    {
        if (!isset($this->rectificationData['substituted'])) {
            $this->rectificationData['substituted'] = [];
        }

        $this->rectificationData['substituted'][] = [
            'issuerNif' => $issuerNif,
            'seriesNumber' => $seriesNumber,
            'issueDate' => $issueDate
        ];

        return $this;
    }

    /**
     * Get the recipients list
     * @return array
     */
    public function getRecipients()
    {
        return $this->recipients;
    }

    /**
     * Add a recipient to the invoice
     * @param string $nif Recipient NIF
     * @param string|null $name Recipient name (optional)
     * @return $this
     */
    public function addRecipient($nif, $name = null)
    {
        $recipient = new Recipient();
        $recipient->nif = $nif;
        $recipient->name = $name;

        $this->recipients[] = [
            'nif' => $recipient->nif,
            'name' => $recipient->name
        ];

        return $this;
    }

    /**
     * Get the tax breakdown
     * @return array
     */
    public function getBreakdown()
    {
        return $this->breakdown;
    }

    /**
     * Add a tax breakdown item
     * @param float $rate Tax rate
     * @param float $base Taxable base
     * @param float $amount Tax amount
     * @return $this
     */
    public function addBreakdownItem($rate, $base, $amount)
    {
        $this->breakdown[] = [
            'rate' => $rate,
            'base' => $base,
            'amount' => $amount
        ];

        return $this;
    }

    /**
     * Set the tax breakdown
     * @param array $breakdown Array of tax breakdown items
     * @return $this
     */
    public function setBreakdown($breakdown)
    {
        $this->breakdown = $breakdown;
        return $this;
    }

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
