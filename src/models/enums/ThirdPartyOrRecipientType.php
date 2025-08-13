<?php

declare(strict_types=1);

namespace eseperio\verifactu\models\enums;

/**
 * Enumeration for third party or recipient types (TercerosODestinatarioType).
 */
enum ThirdPartyOrRecipientType: string
{
    /**
     * Recipient.
     */
    case RECIPIENT = 'D';

    /**
     * Third party.
     */
    case THIRD_PARTY = 'T';
}
