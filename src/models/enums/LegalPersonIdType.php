<?php

namespace eseperio\verifactu\models\enums;

enum LegalPersonIdType: string
{
    case VAT_ID = '02'; // NIF-IVA
    case PASSPORT = '03'; // Pasaporte
    case ID_IN_COUNTRY_OF_RESIDENCE = '04'; // IDEnPaisResidencia
    case RESIDENCE_CERTIFICATE = '05'; // Certificado Residencia
    case OTHER_PROOF_DOCUMENT = '06'; // Otro documento Probatorio
    case NOT_REGISTERED = '07'; // No Censado
}
