<?php
namespace eseperio\verifactu\models\enums;

/**
 * Enumeration for rectification types (ClaveTipoRectificativaType).
 */
enum RectificationType: string
{
    /**
     * Substitutive rectification
     */
    case SUBSTITUTIVE = 'S';
    
    /**
     * Incremental rectification
     */
    case INCREMENTAL = 'I';
}
