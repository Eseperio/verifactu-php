<?php

declare(strict_types=1);

namespace eseperio\verifactu\models;

/**
 * Model representing a tax breakdown (DesgloseType).
 * Contains a collection of breakdown details.
 * Original schema: DesgloseType.
 * @see docs/aeat/esquemas/SuministroInformacion.xsd.xml
 */
class Breakdown extends Model
{
    /**
     * Breakdown details (DetalleDesglose).
     * @var BreakdownDetail[]
     */
    private $details = [];

    /**
     * Get the breakdown details.
     * @return BreakdownDetail[]
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * Add a breakdown detail.
     * @param BreakdownDetail|array $detail Breakdown detail
     * @return $this
     */
    public function addDetail($detail): static
    {
        if (is_array($detail)) {
            $breakdownDetail = new BreakdownDetail();

            // Map array properties to BreakdownDetail properties
            if (isset($detail['taxType'])) {
                $breakdownDetail->taxType = $detail['taxType'];
            }

            if (isset($detail['regimeKey'])) {
                $breakdownDetail->regimeKey = $detail['regimeKey'];
            }

            if (isset($detail['operationQualification'])) {
                $breakdownDetail->operationQualification = $detail['operationQualification'];
            }

            if (isset($detail['exemptOperation'])) {
                $breakdownDetail->exemptOperation = $detail['exemptOperation'];
            }

            if (isset($detail['taxRate'])) {
                $breakdownDetail->taxRate = $detail['taxRate'];
            }

            if (isset($detail['taxableBase'])) {
                $breakdownDetail->taxableBase = $detail['taxableBase'];
            }

            if (isset($detail['costBasedTaxableBase'])) {
                $breakdownDetail->costBasedTaxableBase = $detail['costBasedTaxableBase'];
            }

            if (isset($detail['taxAmount'])) {
                $breakdownDetail->taxAmount = $detail['taxAmount'];
            }

            if (isset($detail['equivalenceSurchargeRate'])) {
                $breakdownDetail->equivalenceSurchargeRate = $detail['equivalenceSurchargeRate'];
            }

            if (isset($detail['equivalenceSurchargeAmount'])) {
                $breakdownDetail->equivalenceSurchargeAmount = $detail['equivalenceSurchargeAmount'];
            }

            $this->details[] = $breakdownDetail;
        } else {
            $this->details[] = $detail;
        }

        return $this;
    }

    /**
     * Set the breakdown details.
     * @param BreakdownDetail[]|array[] $details Array of breakdown details
     * @return $this
     */
    public function setDetails($details): static
    {
        $this->details = [];

        foreach ($details as $detail) {
            $this->addDetail($detail);
        }

        return $this;
    }

    /**
     * Returns validation rules for the tax breakdown.
     */
    public function rules(): array
    {
        return [
            [['details'], 'required'],
            ['details', function ($value): string|bool {
                if (!is_array($value) || $value === []) {
                    return 'At least one breakdown detail is required.';
                }

                foreach ($value as $detail) {
                    if (!($detail instanceof BreakdownDetail)) {
                        return 'All details must be instances of BreakdownDetail.';
                    }

                    $validation = $detail->validate();

                    if ($validation !== true) {
                        return 'Invalid breakdown detail: ' . json_encode($validation);
                    }
                }

                return true;
            }],
        ];
    }
}
