<?php

declare(strict_types=1);

namespace eseperio\verifactu\models;

/**
 * Model representing a recipient of an invoice.
 */
class Recipient extends Model
{
    /**
     * Recipient NIF (NIF).
     * @var string
     */
    public $nif;

    /**
     * Recipient name (Nombre).
     * @var string
     */
    public $name;

    /**
     * Returns validation rules for the recipient.
     */
    public function rules(): array
    {
        return [
            [['nif'], 'required'],
            [['nif', 'name'], 'string'],
        ];
    }
}
