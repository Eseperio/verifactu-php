<?php

declare(strict_types=1);

namespace eseperio\verifactu\models\enums;

/**
 * Enumeration for yes/no values (SiNoType).
 * Original schema: SiNoType.
 * @see docs/aeat/esquemas/SuministroInformacion.xsd.xml
 */
enum YesNoType: string
{
    /**
     * Yes.
     */
    case YES = 'S';

    /**
     * No.
     */
    case NO = 'N';
}
