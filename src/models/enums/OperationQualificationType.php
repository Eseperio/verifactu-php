<?php

declare(strict_types=1);

namespace eseperio\verifactu\models\enums;

/**
 * Enumeration for operation qualifications (CalificacionOperacionType).
 */
enum OperationQualificationType: string
{
    /**
     * Subject and not exempt - without reverse charge.
     */
    case SUBJECT_NO_EXEMPT_NO_REVERSE = 'S1';

    /**
     * Subject and not exempt - with reverse charge.
     */
    case SUBJECT_NO_EXEMPT_REVERSE = 'S2';

    /**
     * Not subject (Article 7, 14, others).
     */
    case NOT_SUBJECT_ARTICLE = 'N1';

    /**
     * Not subject due to location rules.
     */
    case NOT_SUBJECT_LOCATION = 'N2';
}
