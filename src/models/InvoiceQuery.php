<?php
namespace eseperio\verifactu\models;

/**
 * Model representing a query/filter for submitted invoices.
 * Based on: ConsultaFactuSistemaFacturacionType (ConsultaLR.xsd.xml)
 */
class InvoiceQuery extends Model
{
    /**
     * Tax year (Ejercicio)
     * @var string
     */
    public $year;

    /**
     * Period (Periodo), usually month or quarter
     * @var string
     */
    public $period;

    /**
     * Series + invoice number to filter (NumSerieFactura, optional)
     * @var string
     */
    public $seriesNumber;

    /**
     * Counterparty information (Contraparte, optional)
     * @var array
     */
    public $counterparty;

    /**
     * Issue date filter (FechaExpedicionFactura, optional)
     * @var string
     */
    public $issueDate;

    /**
     * System information filter (SistemaInformatico, optional)
     * @var array
     */
    public $systemInfo;

    /**
     * External reference filter (RefExterna, optional)
     * @var string
     */
    public $externalRef;

    /**
     * Pagination key (ClavePaginacion, optional)
     * @var array
     */
    public $paginationKey;

    /**
     * Returns validation rules for invoice query.
     * @return array
     */
    public function rules()
    {
        return [
            [['year', 'period'], 'required'],
            [['year', 'period', 'seriesNumber', 'issueDate', 'externalRef'], 'string'],
            [['counterparty', 'systemInfo', 'paginationKey'], 'array'],
        ];
    }
}
