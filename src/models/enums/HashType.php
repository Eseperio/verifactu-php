<?php

declare(strict_types=1);

namespace eseperio\verifactu\models\enums;

/**
 * Enumeration for hash types (TipoHuellaType).
 */
enum HashType: string
{
    /**
     * SHA-256 hash algorithm.
     */
    case SHA_256 = '01';
}
