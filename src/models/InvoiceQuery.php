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

    /**
     * Serializes the invoice query to XML.
     * 
     * @param \DOMDocument $doc The DOM document to use for creating elements
     * @return \DOMElement The root element of this model's XML representation
     * @throws \DOMException
     */
    public function toXml(\DOMDocument $doc)
    {
        // Root node <ConsultaFactuSistemaFacturacion>
        $consulta = $doc->createElement('ConsultaFactuSistemaFacturacion');

        // <Cabecera>
        $cabecera = $doc->createElement('Cabecera');
        $cabecera->appendChild($doc->createElement('Ejercicio', $this->year));
        $cabecera->appendChild($doc->createElement('Periodo', $this->period));
        $consulta->appendChild($cabecera);

        // <FiltroConsulta>
        $filtro = $doc->createElement('FiltroConsulta');

        // <PeriodoImputacion>
        $periodoImputacion = $doc->createElement('PeriodoImputacion');
        $periodoImputacion->appendChild($doc->createElement('Ejercicio', $this->year));
        $periodoImputacion->appendChild($doc->createElement('Periodo', $this->period));
        $filtro->appendChild($periodoImputacion);

        // <NumSerieFactura> (optional)
        if (!empty($this->seriesNumber)) {
            $filtro->appendChild($doc->createElement('NumSerieFactura', $this->seriesNumber));
        }

        // <Contraparte> (optional, array)
        $counterparty = $this->getCounterparty();
        if (!empty($counterparty) && is_array($counterparty)) {
            $contraparte = $doc->createElement('Contraparte');
            foreach ($counterparty as $key => $value) {
                $contraparte->appendChild($doc->createElement($key, $value));
            }
            $filtro->appendChild($contraparte);
        }

        // <FechaExpedicionFactura> (optional)
        if (!empty($this->issueDate)) {
            $filtro->appendChild($doc->createElement('FechaExpedicionFactura', $this->issueDate));
        }

        // <SistemaInformatico> (optional, array)
        $systemInfo = $this->getSystemInfo();
        if (!empty($systemInfo) && is_array($systemInfo)) {
            $sistema = $doc->createElement('SistemaInformatico');
            foreach ($systemInfo as $key => $value) {
                $sistema->appendChild($doc->createElement($key, $value));
            }
            $filtro->appendChild($sistema);
        }

        // <RefExterna> (optional)
        if (!empty($this->externalRef)) {
            $filtro->appendChild($doc->createElement('RefExterna', $this->externalRef));
        }

        // <ClavePaginacion> (optional, array)
        $paginationKey = $this->getPaginationKey();
        if (!empty($paginationKey) && is_array($paginationKey)) {
            $clavePag = $doc->createElement('ClavePaginacion');
            foreach ($paginationKey as $key => $value) {
                $clavePag->appendChild($doc->createElement($key, $value));
            }
            $filtro->appendChild($clavePag);
        }

        $consulta->appendChild($filtro);

        return $consulta;
    }
}
