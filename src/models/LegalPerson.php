<?php
namespace eseperio\verifactu\models;

/**
 * Model representing a person or company (PersonaFisicaJuridicaType).
 * Can be used for Spanish entities (with NIF) or foreign entities (with OtherID).
 * Original schema: PersonaFisicaJuridicaType
 * @see docs/aeat/esquemas/SuministroInformacion.xsd.xml
 */
class LegalPerson extends Model
{
    /**
     * Name or company name (NombreRazon)
     * @var string
     */
    public $name;

    /**
     * Spanish tax ID (NIF), used for Spanish entities
     * @var string|null
     */
    public $nif;

    /**
     * Other ID data (IDOtro), used for foreign entities
     * @var \eseperio\verifactu\models\OtherID|null
     */
    private $otherId;

    /**
     * Get the other ID data
     * @return \eseperio\verifactu\models\OtherID|null
     */
    public function getOtherId()
    {
        return $this->otherId;
    }

    /**
     * Set the other ID data
     * @param \eseperio\verifactu\models\OtherID|array $otherId Other ID data
     * @return $this
     */
    public function setOtherId($otherId)
    {
        if (is_array($otherId)) {
            $otherID = new OtherID();
            $otherID->countryCode = $otherId['countryCode'] ?? null;
            $otherID->idType = $otherId['idType'] ?? null;
            $otherID->id = $otherId['id'] ?? null;
            $this->otherId = $otherID;
        } else {
            $this->otherId = $otherId;
        }
        return $this;
    }

    /**
     * Returns validation rules for the person/company.
     * @return array
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name', 'nif'], 'string'],
            [['otherId'], 'array'],
            [['nif', 'otherId'], function($value, $model) {
                // Either NIF or otherId must be set
                if ($model->nif === null && $model->otherId === null) {
                    return 'Either NIF or otherId must be provided.';
                }
                return true;
            }],
        ];
    }
}
