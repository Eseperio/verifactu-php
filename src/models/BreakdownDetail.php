<?php

declare(strict_types=1);

namespace eseperio\verifactu\models;

use eseperio\verifactu\models\enums\ExemptOperationType;
use eseperio\verifactu\models\enums\OperationQualificationType;
use eseperio\verifactu\models\enums\RegimeType;
use eseperio\verifactu\models\enums\TaxType;

/**
 * Model representing a tax breakdown detail (DetalleType).
 * Original schema: DetalleType.
 * When serialized to XML, this class creates a 'sfLR:DetalleDesglose' element with all tax breakdown details.
 * @see docs/aeat/esquemas/SuministroInformacion.xsd.xml
 */
class BreakdownDetail extends Model
{
    /**
     * Tax type (Impuesto, optional).
     * @var TaxType|null
     */
    public $taxType;

    /**
     * Regime key (ClaveRegimen, optional).
     * @var RegimeType|null
     */
    public $regimeKey;

    /**
     * Operation qualification (CalificacionOperacion).
     * @var OperationQualificationType|null
     */
    public $operationQualification;

    /**
     * Exempt operation (OperacionExenta).
     * @var ExemptOperationType|null
     */
    public $exemptOperation;

    /**
     * Tax rate (TipoImpositivo, optional).
     * @var float|null
     */
    public $taxRate;

    /**
     * Taxable base or non-subject amount (BaseImponibleOimporteNoSujeto).
     * @var float
     */
    public $taxableBase;

    /**
     * Cost-based taxable base (BaseImponibleACoste, optional).
     * @var float|null
     */
    public $costBasedTaxableBase;

    /**
     * Tax amount (CuotaRepercutida, optional).
     * @var float|null
     */
    public $taxAmount;

    /**
     * Equivalence surcharge rate (TipoRecargoEquivalencia, optional).
     * @var float|null
     */
    public $equivalenceSurchargeRate;

    /**
     * Equivalence surcharge amount (CuotaRecargoEquivalencia, optional).
     * @var float|null
     */
    public $equivalenceSurchargeAmount;

    /**
     * Returns validation rules for the tax breakdown detail.
     */
    public function rules(): array
    {
        return [
            [['taxableBase'], 'required'],
            [['taxRate', 'taxableBase', 'costBasedTaxableBase', 'taxAmount', 'equivalenceSurchargeRate', 'equivalenceSurchargeAmount'], fn($value): bool|string => (is_null($value) || is_float($value) || is_int($value)) ? true : 'Must be a number or null.'],
            [['operationQualification', 'exemptOperation'], function ($value, $model): string|bool {
                // Either operationQualification or exemptOperation must be set
                if ($model->operationQualification === null && $model->exemptOperation === null) {
                    return 'Either operationQualification or exemptOperation must be provided.';
                }

                return true;
            }],
            ['taxType', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return ($value instanceof TaxType) ? true : 'Must be an instance of TaxType.';
            }],
            ['regimeKey', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return ($value instanceof RegimeType) ? true : 'Must be an instance of RegimeType.';
            }],
            ['operationQualification', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return ($value instanceof OperationQualificationType) ? true : 'Must be an instance of OperationQualificationType.';
            }],
            ['exemptOperation', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return ($value instanceof ExemptOperationType) ? true : 'Must be an instance of ExemptOperationType.';
            }],
        ];
    }
    
    /**
     * Deprecated: This method has been replaced by direct XML generation in InvoiceSerializer.
     * 
     * @deprecated This method has been replaced by direct XML generation in InvoiceSerializer
     * @param \DOMDocument $doc The XML document to use for creating elements
     * @return \DOMElement
     * @throws \Exception
     */
    public function toXml(\DOMDocument $doc)
    {
        throw new \Exception(
            'This method is deprecated. The XML generation has been moved to the InvoiceSerializer service.'
        );
    }
}
