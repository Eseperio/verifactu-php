<?php

declare(strict_types=1);

namespace eseperio\verifactu\models;

use eseperio\verifactu\models\enums\ExemptOperationType;
use eseperio\verifactu\models\enums\OperationQualificationType;
use eseperio\verifactu\models\enums\TaxType;

/**
 * Model representing a tax breakdown detail (DetalleType).
 * Original schema: DetalleType.
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
     * @var string|null
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
            [['regimeKey'], 'string'],
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
}
