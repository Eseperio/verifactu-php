<?php

declare(strict_types=1);

namespace eseperio\verifactu\models;

/**
 * Model representing a query/filter for submitted invoices.
 * Based on: ConsultaFactuSistemaFacturacionType (ConsultaLR.xsd.xml).
 */
class InvoiceQuery extends Model
{
    /**
     * Tax year (Ejercicio).
     * @var string
     */
    public $year;

    /**
     * Period (Periodo), usually month or quarter.
     * @var string
     */
    public $period;

    /**
     * Series + invoice number to filter (NumSerieFactura, optional).
     * @var string
     */
    public $seriesNumber;

    //Fix https://github.com/Eseperio/verifactu-php/issues/39
    /**
     * Issuerparty information (Emisor).
     * @var array
     */
    private $issuerparty;

    /**
     * Counterparty information (Contraparte, optional).
     * @var array
     */
    private $counterparty;

    /**
     * Issue date filter (FechaExpedicionFactura, optional).
     * @var string
     */
    public $issueDate;

    /**
     * System information filter (SistemaInformatico, optional).
     */
    private ?array $systemInfo = null;

    /**
     * External reference filter (RefExterna, optional).
     * @var string
     */
    public $externalRef;

    /**
     * Pagination key (ClavePaginacion, optional).
     */
    private ?array $paginationKey = null;

    //Fix https://github.com/Eseperio/verifactu-php/issues/39
    /**
     * Get the issuerparty information.
     * @return array
     */
    public function getIssuerparty()
    {
        return $this->issuerparty;
    }

    //Fix https://github.com/Eseperio/verifactu-php/issues/39
    /**
     * Set the issuerparty information.
     * @param string $nif Issuer party NIF
     * @param string|null $name Issuer party name (optional)
     * @return $this
     */
    public function setIssuerparty($nif, $name = null): static
    {
        $this->issuerparty = [
            'nif' => $nif,
        ];

        if ($name !== null) {
            $this->issuerparty['name'] = $name;
        }

        return $this;
    }

    /**
     * Get the counterparty information.
     * @return array
     */
    public function getCounterparty()
    {
        return $this->counterparty;
    }

    /**
     * Set the counterparty information.
     * @param string $nif Counterparty NIF
     * @param string|null $name Counterparty name (optional)
     * @return $this
     */
    public function setCounterparty($nif, $name = null): static
    {
        $this->counterparty = [
            'nif' => $nif,
        ];

        if ($name !== null) {
            $this->counterparty['name'] = $name;
        }

        return $this;
    }

    /**
     * Get the system information.
     * @return array
     */
    public function getSystemInfo()
    {
        return $this->systemInfo;
    }

    /**
     * Set the system information.
     * @param string $system System name
     * @param string $version System version
     * @return $this
     */
    public function setSystemInfo($system, $version): static
    {
        $this->systemInfo = [
            'system' => $system,
            'version' => $version,
        ];

        return $this;
    }

    /**
     * Get the pagination key.
     * @return array
     */
    public function getPaginationKey()
    {
        return $this->paginationKey;
    }

    /**
     * Set the pagination key.
     * @param int $page Page number
     * @param int $size Page size
     * @return $this
     */
    public function setPaginationKey($page, $size): static
    {
        $this->paginationKey = [
            'page' => $page,
            'size' => $size,
        ];

        return $this;
    }

    /**
     * Returns validation rules for invoice query.
     */
    public function rules(): array
    {
        return [
            [['year', 'period'], 'required'],
            [['year', 'period', 'seriesNumber', 'issueDate', 'externalRef'], 'string'],
            [['counterparty', 'systemInfo', 'paginationKey'], 'array'],
        ];
    }

    /**
     * Deprecated: Use InvoiceSerializer::toQueryXml() instead.
     * 
     * @deprecated This method has been replaced by InvoiceSerializer::toQueryXml()
     * @return \DOMDocument
     * @throws \Exception
     */
    public function toXml(): \DOMDocument
    {
        throw new \Exception(
            'This method is deprecated. Use InvoiceSerializer::toQueryXml() instead. ' .
            'The XML generation has been moved to the InvoiceSerializer service.'
        );
    }
}
