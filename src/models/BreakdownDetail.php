<?php
namespace eseperio\verifactu\models;

use eseperio\verifactu\models\enums\TaxType;
use eseperio\verifactu\models\enums\OperationQualificationType;
use eseperio\verifactu\models\enums\ExemptOperationType;

/**
 * Model representing a tax breakdown detail (DetalleType).
 * Original schema: DetalleType
 * @see docs/aeat/esquemas/SuministroInformacion.xsd.xml
 */
class BreakdownDetail extends Model
{
    /**
     * Tax type (Impuesto, optional)
     * @var \eseperio\verifactu\models\enums\TaxType|null
     */
    public $taxType;

    /**
     * Regime key (ClaveRegimen, optional)
     * @var string|null
     */
    public $regimeKey;

    /**
     * Operation qualification (CalificacionOperacion)
     * @var \eseperio\verifactu\models\enums\OperationQualificationType|null
     */
    public $operationQualification;

    /**
     * Exempt operation (OperacionExenta)
     * @var \eseperio\verifactu\models\enums\ExemptOperationType|null
     */
    public $exemptOperation;

    /**
     * Tax rate (TipoImpositivo, optional)
     * @var float|null
     */
    public $taxRate;

    /**
     * Taxable base or non-subject amount (BaseImponibleOimporteNoSujeto)
     * @var float
     */
    public $taxableBase;

    /**
     * Cost-based taxable base (BaseImponibleACoste, optional)
     * @var float|null
     */
    public $costBasedTaxableBase;

    /**
     * Tax amount (CuotaRepercutida, optional)
     * @var float|null
     */
    public $taxAmount;

    /**
     * Equivalence surcharge rate (TipoRecargoEquivalencia, optional)
     * @var float|null
     */
    public $equivalenceSurchargeRate;

    /**
     * Equivalence surcharge amount (CuotaRecargoEquivalencia, optional)
     * @var float|null
     */
    public $equivalenceSurchargeAmount;

    /**
     * Returns validation rules for the tax breakdown detail.
     * @return array
     */
    public function rules()
    {
        return [
            [['taxableBase'], 'required'],
            [['regimeKey'], 'string'],
            [['taxRate', 'taxableBase', 'costBasedTaxableBase', 'taxAmount', 'equivalenceSurchargeRate', 'equivalenceSurchargeAmount'], function($value) {
                return (is_null($value) || is_float($value) || is_int($value)) ? true : 'Must be a number or null.';
            }],
            [['operationQualification', 'exemptOperation'], function($value, $model) {
                // Either operationQualification or exemptOperation must be set
                if ($model->operationQualification === null && $model->exemptOperation === null) {
                    return 'Either operationQualification or exemptOperation must be provided.';
                }
                return true;
            }],
            ['taxType', function($value) {
                if ($value === null) return true;
                return ($value instanceof TaxType) ? true : 'Must be an instance of TaxType.';
            }],
            ['operationQualification', function($value) {
                if ($value === null) return true;
                return ($value instanceof OperationQualificationType) ? true : 'Must be an instance of OperationQualificationType.';
            }],
            ['exemptOperation', function($value) {
                if ($value === null) return true;
                return ($value instanceof ExemptOperationType) ? true : 'Must be an instance of ExemptOperationType.';
            }],
        ];
    }
}
