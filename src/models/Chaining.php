<?php

declare(strict_types=1);

namespace eseperio\verifactu\models;

/**
 * Model representing chaining information (Encadenamiento).
 * Can represent either a first invoice in a chain or a link to a previous invoice.
 * Original schema: EncadenamientoType.
 * @see docs/aeat/esquemas/SuministroInformacion.xsd.xml
 */
class Chaining extends Model
{
    /**
     * First record indicator (PrimerRegistro)
     * Value 'S' indicates this is the first invoice in a chain.
     * @var string|null
     */
    public $firstRecord;

    /**
     * Previous invoice data (RegistroAnterior).
     * @var PreviousInvoiceChaining|null
     */
    private $previousInvoice;

    /**
     * Get the previous invoice data.
     * @return PreviousInvoiceChaining|null
     */
    public function getPreviousInvoice()
    {
        return $this->previousInvoice;
    }

    /**
     * Set the previous invoice data.
     * @param PreviousInvoiceChaining|array $previousInvoice Previous invoice data
     * @return $this
     */
    public function setPreviousInvoice($previousInvoice): static
    {
        if (is_array($previousInvoice)) {
            $chaining = new PreviousInvoiceChaining();
            $chaining->issuerNif = $previousInvoice['issuerNif'] ?? null;
            $chaining->seriesNumber = $previousInvoice['seriesNumber'] ?? null;
            $chaining->issueDate = $previousInvoice['issueDate'] ?? null;
            $chaining->hash = $previousInvoice['hash'] ?? null;
            $this->previousInvoice = $chaining;
        } else {
            $this->previousInvoice = $previousInvoice;
        }

        // If we set a previous invoice, we can't be the first record
        $this->firstRecord = null;

        return $this;
    }

    /**
     * Set this as the first record in a chain.
     * @return $this
     */
    public function setAsFirstRecord(): static
    {
        $this->firstRecord = 'S';
        $this->previousInvoice = null;

        return $this;
    }

    /**
     * Returns validation rules for the chaining information.
     */
    public function rules(): array
    {
        return [
            [['firstRecord', 'previousInvoice'], function ($value, $model): string|bool {
                // Either firstRecord or previousInvoice must be set
                if ($model->firstRecord === null && $model->previousInvoice === null) {
                    return 'Either firstRecord or previousInvoice must be provided.';
                }

                // Both can't be set at the same time
                if ($model->firstRecord !== null && $model->previousInvoice !== null) {
                    return 'Only one of firstRecord or previousInvoice can be provided.';
                }

                return true;
            }],
            ['firstRecord', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return $value === 'S' ? true : 'First record indicator must be "S".';
            }],
        ];
    }
}
