<?php

declare(strict_types=1);

namespace eseperio\verifactu\models\enums;

/**
 * Enumeration for invoice types (ClaveTipoFacturaType).
 */
enum InvoiceType: string
{
    /**
     * Standard invoice (Art. 6, 7.2 and 7.3 of RD 1619/2012).
     */
    case STANDARD = 'F1';

    /**
     * Simplified invoice and invoices without recipient identification (Art. 6.1.d of RD 1619/2012).
     */
    case SIMPLIFIED = 'F2';

    /**
     * Invoice issued to replace previously declared simplified invoices.
     */
    case REPLACEMENT = 'F3';

    /**
     * Rectification invoice (Art. 80.1, 80.2 and error based on law).
     */
    case RECTIFICATION_1 = 'R1';

    /**
     * Rectification invoice (Art. 80.3).
     */
    case RECTIFICATION_2 = 'R2';

    /**
     * Rectification invoice (Art. 80.4).
     */
    case RECTIFICATION_3 = 'R3';

    /**
     * Rectification invoice (Other cases).
     */
    case RECTIFICATION_4 = 'R4';

    /**
     * Rectification invoice for simplified invoices.
     */
    case RECTIFICATION_SIMPLIFIED = 'R5';
}
