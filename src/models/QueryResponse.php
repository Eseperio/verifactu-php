<?php

declare(strict_types=1);

namespace eseperio\verifactu\models;

/**
 * Model representing the response to an invoice query.
 * Based on: RespuestaConsultaFactuSistemaFacturacionType (RespuestaConsultaLR.xsd.xml).
 */
class QueryResponse extends Model
{
    /**
     * Header data (Cabecera).
     * @var array
     */
    public $header;

    /**
     * Period to which the records belong (PeriodoImputacion).
     * @var array
     */
    public $period;

    /**
     * Pagination indicator (IndicadorPaginacion).
     * @var string
     */
    public $paginationIndicator;

    /**
     * Query result status (ResultadoConsulta).
     * @var string
     */
    public $queryResult;

    /**
     * Array of found invoice records (RegistroRespuestaConsultaFactuSistemaFacturacion, optional).
     * @var array
     */
    public $foundRecords;

    /**
     * Pagination key for more results (ClavePaginacion, optional).
     * @var array
     */
    public $paginationKey;

    /**
     * Returns validation rules for query response.
     */
    public function rules(): array
    {
        return [
            [['header', 'period', 'paginationIndicator', 'queryResult'], 'required'],
            [['header', 'period', 'foundRecords', 'paginationKey'], 'array'],
            [['paginationIndicator', 'queryResult'], 'string'],
        ];
    }
}
