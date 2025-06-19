<?php
namespace eseperio\verifactu\models\enums;

/**
 * Enumeration for generator types (GeneradoPorType).
 */
enum GeneratorType: string
{
    /**
     * Issuer (obliged to issue the cancelled invoice)
     */
    case ISSUER = 'E';
    
    /**
     * Recipient
     */
    case RECIPIENT = 'D';
    
    /**
     * Third party
     */
    case THIRD_PARTY = 'T';
}
