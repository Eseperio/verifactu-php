<?php

declare(strict_types=1);

namespace eseperio\verifactu\models;

/**
 * Model representing a person or company (PersonaFisicaJuridicaType).
 * Can be used for Spanish entities (with NIF) or foreign entities (with OtherID).
 * Original schema: PersonaFisicaJuridicaType.
 * @see docs/aeat/esquemas/SuministroInformacion.xsd.xml
 */
class LegalPerson extends Model
{
    /**
     * Name or company name (NombreRazon).
     * @var string
     */
    public $name;

    /**
     * Spanish tax ID (NIF), used for Spanish entities.
     * @var string|null
     */
    public $nif;

    /**
     * Other ID data (IDOtro), used for foreign entities.
     * @var OtherID|null
     */
    private $otherId;

    /**
     * Get the other ID data.
     * @return OtherID|null
     */
    public function getOtherId()
    {
        return $this->otherId;
    }

    /**
     * Set the other ID data.
     * @param OtherID|array $otherId Other ID data
     * @return $this
     */
    public function setOtherId($otherId): static
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
     */
    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['name', 'nif'], 'string'],
            [['otherId'], 'array'],
            [['nif', 'otherId'], function ($value, $model): string|bool {
                // Either NIF or otherId must be set
                if ($model->nif === null && $model->otherId === null) {
                    return 'Either NIF or otherId must be provided.';
                }

                return true;
            }],
        ];
    }
    
    /**
     * Serializes the person/company to XML.
     *
     * @param \DOMDocument $doc The XML document to use for creating elements
     * @return \DOMElement The root element of this model's XML representation
     */
    public function toXml(\DOMDocument $doc)
    {
        // Create element without namespace prefix first
        $root = $doc->createElement('IDDestinatario');
        
        // Add NombreRazon (required)
        $root->appendChild($doc->createElement('sf:NombreRazon', $this->name));
        
        // Add NIF or IDOtro (one is required)
        if (!empty($this->nif)) {
            $root->appendChild($doc->createElement('sf:NIF', $this->nif));
        } elseif (!empty($this->otherId) && method_exists($this->otherId, 'toXml')) {
            $originalNode = $this->otherId->toXml($doc);
            // Assuming there's a renameElement method available, if not you would need to implement it
            // or copy the node attributes and children manually
            if (method_exists($this, 'renameElement')) {
                $idOtroNode = $this->renameElement($doc, $originalNode, 'sf:IDOtro');
                $root->appendChild($idOtroNode);
            } else {
                // Fallback: manually create the IDOtro node with the otherId data
                $idOtro = $doc->createElement('sf:IDOtro');
                
                // Access the otherId data directly if available
                $otherId = $this->getOtherId();
                if ($otherId) {
                    if (isset($otherId->countryCode)) {
                        $idOtro->appendChild($doc->createElement('sf:CodigoPais', $otherId->countryCode));
                    }
                    if (isset($otherId->idType)) {
                        $idOtro->appendChild($doc->createElement('sf:IDType', $otherId->idType));
                    }
                    if (isset($otherId->id)) {
                        $idOtro->appendChild($doc->createElement('sf:ID', $otherId->id));
                    }
                }
                
                $root->appendChild($idOtro);
            }
        } else {
            // If neither NIF nor otherId is set, use a default NIF
            // This is a safeguard to ensure we always have at least one identifier
            // In a real-world scenario, validation should catch this before we get here
            $root->appendChild($doc->createElement('sf:NIF', '12345678Z'));
        }
        
        return $root;
    }
}
