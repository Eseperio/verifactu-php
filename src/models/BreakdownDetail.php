<?php

declare(strict_types=1);

namespace eseperio\verifactu\models;

use eseperio\verifactu\models\enums\ExemptOperationType;
use eseperio\verifactu\models\enums\OperationQualificationType;
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
    
    /**
     * Serializes the breakdown detail to XML.
     *
     * @param \DOMDocument $doc The XML document to use for creating elements
     * @return \DOMElement The root element of this model's XML representation
     */
    public function toXml(\DOMDocument $doc)
    {
        $root = $doc->createElement('sfLR:DetalleDesglose');
        
        // Impuesto (optional)
        if (!empty($this->taxType)) {
            $root->appendChild($doc->createElement('sfLR:Impuesto', $this->taxType->value));
        }
        
        // ClaveRegimen (optional)
        if (!empty($this->regimeKey)) {
            $root->appendChild($doc->createElement('sfLR:ClaveRegimen', $this->regimeKey));
        }
        
        // CalificacionOperacion (optional, but either this or exemptOperation should be set)
        if (!empty($this->operationQualification)) {
            $root->appendChild($doc->createElement('sfLR:CalificacionOperacion', $this->operationQualification->value));
        }
        
        // OperacionExenta (optional, but either this or operationQualification should be set)
        if (!empty($this->exemptOperation)) {
            $root->appendChild($doc->createElement('sfLR:OperacionExenta', $this->exemptOperation->value));
        }
        
        // TipoImpositivo (optional)
        if (!is_null($this->taxRate)) {
            $root->appendChild($doc->createElement('sfLR:TipoImpositivo', number_format($this->taxRate, 2, '.', '')));
        }
        
        // BaseImponibleOimporteNoSujeto (required)
        $root->appendChild($doc->createElement('sfLR:BaseImponibleOimporteNoSujeto', number_format($this->taxableBase, 2, '.', '')));
        
        // BaseImponibleACoste (optional)
        if (!is_null($this->costBasedTaxableBase)) {
            $root->appendChild($doc->createElement('sfLR:BaseImponibleACoste', number_format($this->costBasedTaxableBase, 2, '.', '')));
        }
        
        // CuotaRepercutida (optional)
        if (!is_null($this->taxAmount)) {
            $root->appendChild($doc->createElement('sfLR:CuotaRepercutida', number_format($this->taxAmount, 2, '.', '')));
        }
        
        // TipoRecargoEquivalencia (optional)
        if (!is_null($this->equivalenceSurchargeRate)) {
            $root->appendChild($doc->createElement('sfLR:TipoRecargoEquivalencia', number_format($this->equivalenceSurchargeRate, 2, '.', '')));
        }
        
        // CuotaRecargoEquivalencia (optional)
        if (!is_null($this->equivalenceSurchargeAmount)) {
            $root->appendChild($doc->createElement('sfLR:CuotaRecargoEquivalencia', number_format($this->equivalenceSurchargeAmount, 2, '.', '')));
        }
        
        return $root;
    }
}
