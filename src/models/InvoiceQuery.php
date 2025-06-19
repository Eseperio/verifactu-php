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
    private $counterparty;

    /**
     * Issue date filter (FechaExpedicionFactura, optional)
     * @var string
     */
    public $issueDate;

    /**
     * System information filter (SistemaInformatico, optional)
     * @var array
     */
    private $systemInfo;

    /**
     * External reference filter (RefExterna, optional)
     * @var string
     */
    public $externalRef;

    /**
     * Pagination key (ClavePaginacion, optional)
     * @var array
     */
    private $paginationKey;

    /**
     * Get the counterparty information
     * @return array
     */
    public function getCounterparty()
    {
        return $this->counterparty;
    }

    /**
     * Set the counterparty information
     * @param string $nif Counterparty NIF
     * @param string|null $name Counterparty name (optional)
     * @return $this
     */
    public function setCounterparty($nif, $name = null)
    {
        $this->counterparty = [
            'nif' => $nif
        ];

        if ($name !== null) {
            $this->counterparty['name'] = $name;
        }

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
     * Get the pagination key
     * @return array
     */
    public function getPaginationKey()
    {
        return $this->paginationKey;
    }

    /**
     * Set the pagination key
     * @param int $page Page number
     * @param int $size Page size
     * @return $this
     */
    public function setPaginationKey($page, $size)
    {
        $this->paginationKey = [
            'page' => $page,
            'size' => $size
        ];
        return $this;
    }

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
