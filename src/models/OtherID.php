<?php
namespace eseperio\verifactu\models;

/**
 * Model representing an identifier for foreign entities (IDOtroType).
 * Used when the entity doesn't have a Spanish NIF.
 * Original schema: IDOtroType
 * @see docs/aeat/esquemas/SuministroInformacion.xsd.xml
 */
class OtherID extends Model
{
    /**
     * Country code (CodigoPais, optional)
     * ISO 3166-1 alpha-2 code (e.g., 'FR', 'DE', 'US')
     * @var string|null
     */
    public $countryCode;

    /**
     * ID type (IDType)
     * Possible values:
     * - 02: NIF-IVA
     * - 03: Passport
     * - 04: ID in country of residence
     * - 05: Residence certificate
     * - 06: Other document
     * - 07: Not registered
     * @var string
     */
    public $idType;

    /**
     * ID value (ID)
     * @var string
     */
    public $id;

    /**
     * Returns validation rules for the foreign ID.
     * @return array
     */
    public function rules()
    {
        return [
            [['idType', 'id'], 'required'],
            [['countryCode', 'idType', 'id'], 'string'],
            ['idType', function($value) {
                $validTypes = ['02', '03', '04', '05', '06', '07'];
                return in_array($value, $validTypes) ? true : 'Invalid ID type.';
            }],
        ];
    }
}
