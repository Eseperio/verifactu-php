<?php

declare(strict_types=1);

namespace eseperio\verifactu\models;

use eseperio\verifactu\models\enums\YesNoType;

/**
 * Model representing information about the system that generated the invoice (SistemaInformaticoType).
 * Original schema: SistemaInformaticoType.
 * @see docs/aeat/esquemas/SuministroInformacion.xsd.xml
 */
class ComputerSystem extends Model
{
    /**
     * Provider name or company name (NombreRazon).
     * @var string
     */
    public $providerName;

    /**
     * Provider identification
     * Either NIF (for Spanish entities) or OtherID (for foreign entities).
     * @var LegalPerson
     */
    private $providerId;

    /**
     * System name (NombreSistemaInformatico).
     * @var string
     */
    public $systemName;

    /**
     * System ID (IdSistemaInformatico).
     * @var string
     */
    public $systemId;

    /**
     * System version (Version).
     * @var string
     */
    public $version;

    /**
     * Installation number (NumeroInstalacion).
     * @var string
     */
    public $installationNumber;

    /**
     * Indicates if the system can only be used with Verifactu (TipoUsoPosibleSoloVerifactu).
     * @var YesNoType
     */
    public $onlyVerifactu;

    /**
     * Indicates if the system can be used with multiple tax obligations (TipoUsoPosibleMultiOT).
     * @var YesNoType
     */
    public $multipleObligations;

    /**
     * Indicates if the system is used with multiple tax obligations (IndicadorMultiplesOT).
     * @var YesNoType
     */
    public $hasMultipleObligations;

    /**
     * Get the provider ID.
     * @return LegalPerson
     */
    public function getProviderId()
    {
        return $this->providerId;
    }

    /**
     * Set the provider ID.
     * @param LegalPerson|array $providerId Provider ID
     * @return $this
     */
    public function setProviderId($providerId): static
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
     */
    public function rules(): array
    {
        return [
            [['providerName', 'providerId', 'systemName', 'systemId', 'version', 'installationNumber', 'onlyVerifactu', 'multipleObligations', 'hasMultipleObligations'], 'required'],
            [['providerName', 'systemName', 'systemId', 'version', 'installationNumber'], 'string'],
            [['onlyVerifactu', 'multipleObligations', 'hasMultipleObligations'], fn($value): bool|string => ($value instanceof YesNoType) ? true : 'Must be an instance of YesNoType.'],
            ['providerId', fn($value): bool|string => ($value instanceof LegalPerson) ? true : 'Must be an instance of LegalPerson.'],
        ];
    }
}
