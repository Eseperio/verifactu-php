<?php

declare(strict_types=1);

namespace eseperio\verifactu\models\enums;

/**
 * Enumeration for tax types (ImpuestoType).
 */
enum TaxType: string
{
    /**
     * Value Added Tax (IVA).
     */
    case IVA = '01';

    /**
     * Tax on Production, Services and Imports (IPSI) of Ceuta and Melilla.
     */
    case IPSI = '02';

    /**
     * General Indirect Tax of the Canary Islands (IGIC).
     */
    case IGIC = '03';

    /**
     * Other taxes.
     */
    case OTHER = '05';
}
