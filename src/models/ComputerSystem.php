<?php
namespace eseperio\verifactu\models;

use eseperio\verifactu\models\enums\YesNoType;

/**
 * Model representing information about the system that generated the invoice (SistemaInformaticoType).
 * Original schema: SistemaInformaticoType
 * @see docs/aeat/esquemas/SuministroInformacion.xsd.xml
 */
class ComputerSystem extends Model
{
    /**
     * Provider name or company name (NombreRazon)
     * @var string
     */
    public $providerName;

    /**
     * Provider identification
     * Either NIF (for Spanish entities) or OtherID (for foreign entities)
     * @var \eseperio\verifactu\models\LegalPerson
     */
    private $providerId;

    /**
     * System name (NombreSistemaInformatico)
     * @var string
     */
    public $systemName;

    /**
     * System ID (IdSistemaInformatico)
     * @var string
     */
    public $systemId;

    /**
     * System version (Version)
     * @var string
     */
    public $version;

    /**
     * Installation number (NumeroInstalacion)
     * @var string
     */
    public $installationNumber;

    /**
     * Indicates if the system can only be used with Verifactu (TipoUsoPosibleSoloVerifactu)
     * @var \eseperio\verifactu\models\enums\YesNoType
     */
    public $onlyVerifactu;

    /**
     * Indicates if the system can be used with multiple tax obligations (TipoUsoPosibleMultiOT)
     * @var \eseperio\verifactu\models\enums\YesNoType
     */
    public $multipleObligations;

    /**
     * Indicates if the system is used with multiple tax obligations (IndicadorMultiplesOT)
     * @var \eseperio\verifactu\models\enums\YesNoType
     */
    public $hasMultipleObligations;

    /**
     * Get the provider ID
     * @return \eseperio\verifactu\models\LegalPerson
     */
    public function getProviderId()
    {
        return $this->providerId;
    }

    /**
     * Set the provider ID
     * @param \eseperio\verifactu\models\LegalPerson|array $providerId Provider ID
     * @return $this
     */
    public function setProviderId($providerId)
    {
        if (is_array($providerId)) {
            $provider = new LegalPerson();
            $provider->name = $providerId['name'] ?? null;

            if (isset($providerId['nif'])) {
                $provider->nif = $providerId['nif'];
            } elseif (isset($providerId['otherId'])) {
                $provider->setOtherId($providerId['otherId']);
            }

            $this->providerId = $provider;
        } else {
            $this->providerId = $providerId;
        }
        return $this;
    }

    /**
     * Returns validation rules for the system information.
     * @return array
     */
    public function rules()
    {
        return [
            [['providerName', 'providerId', 'systemName', 'systemId', 'version', 'installationNumber', 'onlyVerifactu', 'multipleObligations', 'hasMultipleObligations'], 'required'],
            [['providerName', 'systemName', 'systemId', 'version', 'installationNumber'], 'string'],
            [['onlyVerifactu', 'multipleObligations', 'hasMultipleObligations'], function($value) {
                return ($value instanceof YesNoType) ? true : 'Must be an instance of YesNoType.';
            }],
            ['providerId', function($value) {
                return ($value instanceof LegalPerson) ? true : 'Must be an instance of LegalPerson.';
            }],
        ];
    }
}
