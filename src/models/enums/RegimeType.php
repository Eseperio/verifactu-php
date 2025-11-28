<?php

declare(strict_types=1);

namespace eseperio\verifactu\models\enums;

/**
 * Enumeration for Special Regime Keys or Additional Transcendence (ClaveRegimenType).
 * Claves de Régimen Especial o Trascendencia Adicional.
 */
enum RegimeType: string
{
    /**
     * General regime operation.
     * Operación de régimen general.
     */
    case GENERAL = '01';

    /**
     * Export.
     * Exportación.
     */
    case EXPORT = '02';

    /**
     * Operations under the special regime for used goods, works of art, antiques and collectibles.
     * Operaciones a las que se aplique el régimen especial de bienes usados, objetos de arte, antigüedades y objetos de colección.
     */
    case USED_GOODS = '03';

    /**
     * Special regime for investment gold.
     * Régimen especial del oro de inversión.
     */
    case INVESTMENT_GOLD = '04';

    /**
     * Special regime for travel agencies.
     * Régimen especial de las agencias de viajes.
     */
    case TRAVEL_AGENCIES = '05';

    /**
     * Special regime for groups of entities in VAT (Advanced Level).
     * Régimen especial grupo de entidades en IVA (Nivel Avanzado).
     */
    case ENTITY_GROUPS = '06';

    /**
     * Special regime for cash basis accounting.
     * Régimen especial del criterio de caja.
     */
    case CASH_BASIS = '07';

    /**
     * Operations subject to IPSI / IGIC (Tax on Production, Services and Imports / Canary Islands General Indirect Tax).
     * Operaciones sujetas al IPSI / IGIC (Impuesto sobre la Producción, los Servicios y la Importación / Impuesto General Indirecto Canario).
     */
    case IPSI_IGIC = '08';

    /**
     * Invoicing of travel agency services acting as intermediaries on behalf and for the account of others (D.A 4ª RD1619/2012).
     * Facturación de las prestaciones de servicios de agencias de viaje que actúan como mediadoras en nombre y por cuenta ajena (D.A 4ª RD1619/2012).
     */
    case TRAVEL_AGENCY_INTERMEDIARY = '09';

    /**
     * Collections on behalf of third parties for professional fees or rights derived from industrial property, copyright or other rights on behalf of their members, associates or members made by companies, associations, professional associations or other entities that perform these collection functions.
     * Cobros por cuenta de terceros de honorarios profesionales o de derechos derivados de la propiedad industrial, de autor u otros por cuenta de sus socios, asociados o colegiados efectuados por sociedades, asociaciones, colegios profesionales u otras entidades que realicen estas funciones de cobro.
     */
    case THIRD_PARTY_COLLECTIONS = '10';

    /**
     * Business premises rental operations.
     * Operaciones de arrendamiento de local de negocio.
     */
    case BUSINESS_RENTAL = '11';

    /**
     * Invoice with VAT pending accrual in work certifications whose recipient is a Public Administration.
     * Factura con IVA pendiente de devengo en certificaciones de obra cuyo destinatario sea una Administración Pública.
     */
    case PENDING_VAT_PUBLIC_WORKS = '14';

    /**
     * Invoice with VAT pending accrual in successive tract operations.
     * Factura con IVA pendiente de devengo en operaciones de tracto sucesivo.
     */
    case PENDING_VAT_SUCCESSIVE = '15';

    /**
     * Operation covered by one of the regimes provided for in Chapter XI of Title IX (OSS and IOSS).
     * Operación acogida a alguno de los regímenes previstos en el Capítulo XI del Título IX (OSS e IOSS).
     */
    case OSS_IOSS = '17';

    /**
     * Equivalence surcharge.
     * Recargo de equivalencia.
     */
    case EQUIVALENCE_SURCHARGE = '18';

    /**
     * Operations of activities included in the Special Regime for Agriculture, Livestock and Fishing (REAGYP).
     * Operaciones de actividades incluidas en el Régimen Especial de Agricultura, Ganadería y Pesca (REAGYP).
     */
    case AGRICULTURE_LIVESTOCK_FISHING = '19';

    /**
     * Simplified regime.
     * Régimen simplificado.
     */
    case SIMPLIFIED = '20';
}
