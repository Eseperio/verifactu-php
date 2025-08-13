<?php

declare(strict_types=1);

namespace eseperio\verifactu\models;

/**
 * Model representing rectification breakdown data (DesgloseRectificacionType).
 * Used in rectification invoices to specify the rectified amounts.
 * Original schema: DesgloseRectificacionType.
 * @see docs/aeat/esquemas/SuministroInformacion.xsd.xml
 */
class RectificationBreakdown extends Model
{
    /**
     * Rectified base amount (BaseRectificada).
     * @var float
     */
    public $rectifiedBase;

    /**
     * Rectified tax amount (CuotaRectificada).
     * @var float
     */
    public $rectifiedTax;

    /**
     * Rectified equivalence surcharge amount (CuotaRecargoRectificado, optional).
     * @var float|null
     */
    public $rectifiedEquivalenceSurcharge;

    /**
     * Returns validation rules for the rectification breakdown.
     */
    public function rules(): array
    {
        return [
            [['rectifiedBase', 'rectifiedTax'], 'required'],
            [['rectifiedBase', 'rectifiedTax', 'rectifiedEquivalenceSurcharge'], fn($value): bool|string => (is_null($value) || is_float($value) || is_int($value)) ? true : 'Must be a number or null.'],
        ];
    }
}
