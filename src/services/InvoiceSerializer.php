<?php

declare(strict_types=1);

namespace eseperio\verifactu\services;

use eseperio\verifactu\models\Breakdown;
use eseperio\verifactu\models\BreakdownDetail;
use eseperio\verifactu\models\ComputerSystem;
use eseperio\verifactu\models\InvoiceCancellation;
use eseperio\verifactu\models\InvoiceId;
use eseperio\verifactu\models\InvoiceQuery;
use eseperio\verifactu\models\InvoiceSubmission;
use eseperio\verifactu\models\LegalPerson;
use eseperio\verifactu\models\OtherID;
use eseperio\verifactu\models\RectificationBreakdown;

/**
 * Service for serializing invoice models to XML according to the SuministroInformacion.xsd schema.
 * This service generates XML directly without relying on model toXml methods.
 */
class InvoiceSerializer
{
    /** SuministroInformacion.xsd namespace */
    public const SF_NAMESPACE = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

    /** SuministroLR.xsd namespace */
    public const SFLR_NAMESPACE = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';

    /** ConsultaLR.xsd namespace */
    public const QUERY_NAMESPACE = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/ConsultaLR.xsd';

    /** XML Digital Signature namespace */
    public const DS_NAMESPACE = 'http://www.w3.org/2000/09/xmldsig#';

    /**
     * Serializes an InvoiceSubmission model to XML.
     * Directly creates XML without relying on model toXml methods.
     *
     * @param InvoiceSubmission $invoice The invoice to serialize
     * @param bool $validate Whether to validate the XML against the XSD schema
     * @return \DOMDocument The XML document
     * @throws \Exception If validation fails
     */
    public static function toInvoiceXml(InvoiceSubmission $invoice, bool $validate = false): \DOMDocument
    {
        // Create the XML document
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        // Create root element with namespace
        $root = $doc->createElementNS(self::SF_NAMESPACE, 'sf:RegistroAlta');
        $doc->appendChild($root);

        // IDVersion (required, hardcoded as '1.0')
        $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDVersion', '1.0'));

        // IDFactura (required)
        $invoiceId = $invoice->getInvoiceId();
        if ($invoiceId) {
            $idFactura = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDFactura');
            $idFactura->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDEmisorFactura', (string) $invoiceId->issuerNif));
            $idFactura->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumSerieFactura', (string) $invoiceId->seriesNumber));
            $idFactura->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaExpedicionFactura', (string) $invoiceId->issueDate));
            $root->appendChild($idFactura);
        }

        // RefExterna (optional)
        if (!empty($invoice->externalRef)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:RefExterna', (string) $invoice->externalRef));
        }

        // NombreRazonEmisor (required)
        $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazonEmisor', (string) $invoice->issuerName));



        // TipoFactura (required)
        if ($invoice->invoiceType) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoFactura', (string) $invoice->invoiceType->value));
        }

        if ($invoice->rectificationType) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoRectificativa', (string) $invoice->rectificationType->value));
        }

        $rectData = $invoice->getRectificationData();
        if (!empty($rectData['rectified'])) {
            $facturasRectificadas = $doc->createElementNS(self::SF_NAMESPACE, 'sf:FacturasRectificadas');
            foreach ($rectData['rectified'] as $rect) {
                $idFacturaRectificada = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDFacturaRectificada');
                $idFacturaRectificada->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDEmisorFactura', (string) $rect['issuerNif']));
                $idFacturaRectificada->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumSerieFactura', (string) $rect['seriesNumber']));
                $idFacturaRectificada->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaExpedicionFactura', (string) $rect['issueDate']));
                $facturasRectificadas->appendChild($idFacturaRectificada);
            }
            $root->appendChild($facturasRectificadas);
        }

        // FacturasSustituidas (optional)
        if (!empty($rectData['substituted'])) {
            $facturasSustituidas = $doc->createElementNS(self::SF_NAMESPACE, 'sf:FacturasSustituidas');
            foreach ($rectData['substituted'] as $subst) {
                $idFacturaSustituida = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDFacturaSustituida');
                $idFacturaSustituida->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDEmisorFactura', (string) $subst['issuerNif']));
                $idFacturaSustituida->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumSerieFactura', (string) $subst['seriesNumber']));
                $idFacturaSustituida->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaExpedicionFactura', (string) $subst['issueDate']));
                $facturasSustituidas->appendChild($idFacturaSustituida);
            }
            $root->appendChild($facturasSustituidas);
        }

        // ImporteRectificacion (optional)
        $rectBreakdown = $invoice->getRectificationBreakdown();
        if ($rectBreakdown) {
            $importeRectificacion = $doc->createElementNS(self::SF_NAMESPACE, 'sf:ImporteRectificacion');
            $importeRectificacion->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:BaseRectificada', (string) number_format((float) $rectBreakdown->rectifiedBase, 2, '.', '')));
            $importeRectificacion->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CuotaRectificada', (string) number_format((float) $rectBreakdown->rectifiedTax, 2, '.', '')));
            if (!is_null($rectBreakdown->rectifiedEquivalenceSurcharge)) {
                $importeRectificacion->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CuotaRecargoRectificado', (string) number_format((float) $rectBreakdown->rectifiedEquivalenceSurcharge, 2, '.', '')));
            }
            $root->appendChild($importeRectificacion);
        }

        if (!empty($invoice->operationDate)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaOperacion', (string) $invoice->operationDate));
        }

        $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:DescripcionOperacion', (string) $invoice->operationDescription));

        if ($invoice->simplifiedInvoice) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FacturaSimplificadaArt7273', (string) $invoice->simplifiedInvoice->value));
        }

        // FacturaSinIdentifDestinatarioArt61d (optional)
        if ($invoice->invoiceWithoutRecipient) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FacturaSinIdentifDestinatarioArt61d', (string) $invoice->invoiceWithoutRecipient->value));
        }

        // Macrodato (optional)
        if ($invoice->macrodata) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Macrodato', (string) $invoice->macrodata->value));
        }

        // EmitidaPorTerceroODestinatario (optional)
        if ($invoice->issuedBy) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:EmitidaPorTerceroODestinatario', (string) $invoice->issuedBy->value));
        }

        // Tercero (optional)
        $thirdParty = $invoice->getThirdParty();
        if ($thirdParty) {
            $tercero = $doc->createElementNS(self::SF_NAMESPACE, 'sf:Tercero');
            $tercero->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazon', (string) $thirdParty->name));
            if (!empty($thirdParty->nif)) {
                $tercero->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NIF', (string) $thirdParty->nif));
            } elseif ($otherId = $thirdParty->getOtherId()) {
                $idOtro = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDOtro');
                if (!empty($otherId->countryCode)) {
                    $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CodigoPais', (string) $otherId->countryCode));
                }
                if (!empty($otherId->idType)) {
                    $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDType', (string) $otherId->idType));
                }
                if (!empty($otherId->id)) {
                    $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:ID', (string) $otherId->id));
                }
                $tercero->appendChild($idOtro);
            }
            $root->appendChild($tercero);
        }

        // Destinatarios (required if certain conditions are met)
        $recipients = $invoice->getRecipients();
        if (!empty($recipients)) {
            $destinatarios = $doc->createElementNS(self::SF_NAMESPACE, 'sf:Destinatarios');
            foreach ($recipients as $recipient) {
                $idDestinatario = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDDestinatario');
                $idDestinatario->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazon', (string) $recipient->name));
                if (!empty($recipient->nif)) {
                    $idDestinatario->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NIF', (string) $recipient->nif));
                } elseif ($otherId = $recipient->getOtherId()) {
                    $idOtro = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDOtro');
                    if (!empty($otherId->countryCode)) {
                        $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CodigoPais', (string) $otherId->countryCode));
                    }
                    if (!empty($otherId->idType)) {
                        $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDType', (string) $otherId->idType));
                    }
                    if (!empty($otherId->id)) {
                        $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:ID', (string) $otherId->id));
                    }
                    $idDestinatario->appendChild($idOtro);
                }
                $destinatarios->appendChild($idDestinatario);
            }
            $root->appendChild($destinatarios);
        }

        // Cupon (optional)
        if ($invoice->coupon) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Cupon', (string) $invoice->coupon->value));
        }

        // Desglose (required)
        $breakdown = $invoice->getBreakdown();
        if ($breakdown) {
            $desglose = $doc->createElementNS(self::SF_NAMESPACE, 'sf:Desglose');
            foreach ($breakdown->getDetails() as $detail) {
                $detalleDesglose = $doc->createElementNS(self::SF_NAMESPACE, 'sf:DetalleDesglose');
                // Impuesto (optional)
                if ($detail->taxType) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Impuesto', (string) $detail->taxType->value));
                }

                // ClaveRegimen (optional)
                if ($detail->regimeKey) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:ClaveRegimen', (string) $detail->regimeKey->value));
                }

                // Either CalificacionOperacion or OperacionExenta (one is required)
                if ($detail->operationQualification) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CalificacionOperacion', (string) $detail->operationQualification->value));
                } elseif ($detail->exemptOperation) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:OperacionExenta', (string) $detail->exemptOperation->value));
                }

                // TipoImpositivo (optional)
                if (!is_null($detail->taxRate)) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoImpositivo', (string) number_format((float) $detail->taxRate, 2, '.', '')));
                }

                // BaseImponibleOimporteNoSujeto (required)
                $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:BaseImponibleOimporteNoSujeto', (string) number_format((float) $detail->taxableBase, 2, '.', '')));

                // BaseImponibleACoste (optional)
                if (!is_null($detail->costBasedTaxableBase)) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:BaseImponibleACoste', (string) number_format((float) $detail->costBasedTaxableBase, 2, '.', '')));
                }

                // CuotaRepercutida (optional)
                if (!is_null($detail->taxAmount)) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CuotaRepercutida', (string) number_format((float) $detail->taxAmount, 2, '.', '')));
                }

                // TipoRecargoEquivalencia (optional)
                if (!is_null($detail->equivalenceSurchargeRate)) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoRecargoEquivalencia', (string) number_format((float) $detail->equivalenceSurchargeRate, 2, '.', '')));
                }

                // CuotaRecargoEquivalencia (optional)
                if (!is_null($detail->equivalenceSurchargeAmount)) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CuotaRecargoEquivalencia', (string) number_format((float) $detail->equivalenceSurchargeAmount, 2, '.', '')));
                }

                $desglose->appendChild($detalleDesglose);
            }
            $root->appendChild($desglose);
        } else {
            $desglose = $doc->createElementNS(self::SF_NAMESPACE, 'sf:Desglose');
            $detalleDesglose = $doc->createElementNS(self::SF_NAMESPACE, 'sf:DetalleDesglose');
            // Schema requires CalificacionOperacion or OperacionExenta before BaseImponible
            $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CalificacionOperacion', 'S1'));
            $base = (float) $invoice->totalAmount - (float) $invoice->taxAmount;
            $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:BaseImponibleOimporteNoSujeto', (string) number_format($base, 2, '.', '')));
            if (!is_null($invoice->taxAmount)) {
                $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CuotaRepercutida', (string) number_format((float) $invoice->taxAmount, 2, '.', '')));
            }
            $desglose->appendChild($detalleDesglose);
            $root->appendChild($desglose);
        }

        // CuotaTotal (required)
        $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CuotaTotal', (string) number_format((float) $invoice->taxAmount, 2, '.', '')));

        // ImporteTotal (required)
        $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:ImporteTotal', (string) number_format((float) $invoice->totalAmount, 2, '.', '')));

        // Encadenamiento (required)
        $encadenamiento = $doc->createElementNS(self::SF_NAMESPACE, 'sf:Encadenamiento');

        $chaining = $invoice->getChaining();
        if ($chaining) {
            // Check if it's the first record in a chain
            if ($chaining->firstRecord === 'S') {
                // PrimerRegistro
                $encadenamiento->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:PrimerRegistro', 'S'));
            } else {
                // RegistroAnterior - get previous invoice details
                $previousInvoice = $chaining->getPreviousInvoice();
                if ($previousInvoice) {
                    $registroAnterior = $doc->createElementNS(self::SF_NAMESPACE, 'sf:RegistroAnterior');
                    $registroAnterior->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDEmisorFactura', (string) $previousInvoice->issuerNif));
                    $registroAnterior->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumSerieFactura', (string) $previousInvoice->seriesNumber));
                    $registroAnterior->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaExpedicionFactura', (string) $previousInvoice->issueDate));
                    $registroAnterior->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Huella', (string) $previousInvoice->hash));
                    $encadenamiento->appendChild($registroAnterior);
                } else {
                    // Fallback to PrimerRegistro if no previous invoice details available
                    $encadenamiento->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:PrimerRegistro', 'S'));
                }
            }
        } else {
            // Default to PrimerRegistro if no chaining info provided
            $encadenamiento->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:PrimerRegistro', 'S'));
        }

        $root->appendChild($encadenamiento);

        // SistemaInformatico (required)
        $computerSystem = $invoice->getSystemInfo();
        $invoiceIdForDefaults = $invoice->getInvoiceId();
        $root->appendChild(self::buildSistemaInformatico($doc, $computerSystem, $invoiceIdForDefaults?->issuerNif));

        if (!empty($invoice->recordTimestamp)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaHoraHusoGenRegistro', (string) $invoice->recordTimestamp));
        }
        if (!empty($invoice->invoiceAgreementNumber)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumRegistroAcuerdoFacturacion', (string) $invoice->invoiceAgreementNumber));
        }
        if (!empty($invoice->systemAgreementId)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IdAcuerdoSistemaInformatico', (string) $invoice->systemAgreementId));
        }
        if ($invoice->hashType) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoHuella', (string) $invoice->hashType->value));
        }
        if (!empty($invoice->hash)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Huella', (string) $invoice->hash));
        }

        if ($validate) {
            self::validateXml($doc, __DIR__ . '/../schemes/SuministroInformacion.xsd');
        }

        return $doc;
    }

    /**
     * Serializes an InvoiceCancellation model to XML.
     */
    public static function toCancellationXml(InvoiceCancellation $cancellation, bool $validate = false): \DOMDocument
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElementNS(self::SF_NAMESPACE, 'sf:RegistroAnulacion');
        $doc->appendChild($root);

        $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDVersion', '1.0'));

        $invoiceId = $cancellation->getInvoiceId();
        if ($invoiceId) {
            $idFactura = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDFactura');
            $idFactura->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDEmisorFacturaAnulada', (string) $invoiceId->issuerNif));
            $idFactura->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumSerieFacturaAnulada', (string) $invoiceId->seriesNumber));
            $idFactura->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaExpedicionFacturaAnulada', (string) $invoiceId->issueDate));
            $root->appendChild($idFactura);
        }

        if (!empty($cancellation->externalReference)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:RefExterna', (string) $cancellation->externalReference));
        }
        if ($cancellation->noPreviousRecord) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:SinRegistroPrevio', (string) $cancellation->noPreviousRecord->value));
        }
        if ($cancellation->previousRejection) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:RechazoPrevio', (string) $cancellation->previousRejection->value));
        }
        if ($cancellation->generator) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:GeneradoPor', (string) $cancellation->generator->value));
        }

        $generatorData = $cancellation->getGeneratorData();
        if ($generatorData) {
            $generador = $doc->createElementNS(self::SF_NAMESPACE, 'sf:Generador');
            $generador->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazon', (string) $generatorData->name));
            if (!empty($generatorData->nif)) {
                $generador->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NIF', (string) $generatorData->nif));
            } elseif ($otherId = $generatorData->getOtherId()) {
                $idOtro = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDOtro');
                if (!empty($otherId->countryCode)) {
                    $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CodigoPais', (string) $otherId->countryCode));
                }
                if (!empty($otherId->idType)) {
                    $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDType', (string) $otherId->idType));
                }
                if (!empty($otherId->id)) {
                    $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:ID', (string) $otherId->id));
                }
                $generador->appendChild($idOtro);
            }
            $root->appendChild($generador);
        }

        $encadenamiento = $doc->createElementNS(self::SF_NAMESPACE, 'sf:Encadenamiento');
        $chaining = $cancellation->getChaining();
        if ($chaining) {
            if ($chaining->firstRecord === 'S') {
                $encadenamiento->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:PrimerRegistro', 'S'));
            } else {
                $previousInvoice = $chaining->getPreviousInvoice();
                if ($previousInvoice) {
                    $registroAnterior = $doc->createElementNS(self::SF_NAMESPACE, 'sf:RegistroAnterior');
                    $registroAnterior->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDEmisorFactura', (string) $previousInvoice->issuerNif));
                    $registroAnterior->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumSerieFactura', (string) $previousInvoice->seriesNumber));
                    $registroAnterior->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaExpedicionFactura', (string) $previousInvoice->issueDate));
                    $registroAnterior->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Huella', (string) $previousInvoice->hash));
                    $encadenamiento->appendChild($registroAnterior);
                } else {
                    $encadenamiento->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:PrimerRegistro', 'S'));
                }
            }
        } else {
            $encadenamiento->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:PrimerRegistro', 'S'));
        }
        $root->appendChild($encadenamiento);

        $computerSystem = $cancellation->getSystemInfo();
        $invoiceIdForDefaults = $cancellation->getInvoiceId();
        $root->appendChild(self::buildSistemaInformatico($doc, $computerSystem, $invoiceIdForDefaults?->issuerNif));

        if (!empty($cancellation->recordTimestamp)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaHoraHusoGenRegistro', (string) $cancellation->recordTimestamp));
        }
        if ($cancellation->hashType) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoHuella', (string) $cancellation->hashType->value));
        }
        if (!empty($cancellation->hash)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Huella', (string) $cancellation->hash));
        }

        if ($validate) {
            self::validateXml($doc, __DIR__ . '/../schemes/SuministroInformacion.xsd');
        }

        return $doc;
    }

    /**
     * Serializes an InvoiceQuery model to XML.
     */
    public static function toQueryXml(InvoiceQuery $query, bool $validate = true): \DOMDocument
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        // Root element must be in ConsultaLR namespace, but tests expect the prefix 'sf'
        $root = $doc->createElementNS(self::QUERY_NAMESPACE, 'sf:ConsultaFactuSistemaFacturacion');
        $doc->appendChild($root);

        // Cabecera (Consulta namespace) with required IDVersion (child in SF namespace)
        $cabecera = $doc->createElementNS(self::QUERY_NAMESPACE, 'sf:Cabecera');
        $cabecera->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDVersion', '1.0'));
        $root->appendChild($cabecera);
        #Fix https://github.com/Eseperio/verifactu-php/issues/39
        $issuerparty = $query->getIssuerparty();
        $obligadoEmision = $doc->createElementNS(self::SF_NAMESPACE, 'sf:ObligadoEmision');
        $cabecera->appendChild($obligadoEmision);
        // Orden según schema PersonaFisicaJuridicaESType: NombreRazon, NIF
        $obligadoEmision->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazon', (string) $issuerparty['name']));
        $obligadoEmision->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NIF', (string) $issuerparty['nif']));

        // FiltroConsulta (Consulta namespace)
        $filtro = $doc->createElementNS(self::QUERY_NAMESPACE, 'sf:FiltroConsulta');
        $root->appendChild($filtro);

        // PeriodoImputacion (Consulta ns) with children in SF ns, per schema
        $periodoImputacion = $doc->createElementNS(self::QUERY_NAMESPACE, 'sf:PeriodoImputacion');
        $periodoImputacion->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Ejercicio', (string) $query->year));
        $periodoImputacion->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Periodo', (string) $query->period));
        $filtro->appendChild($periodoImputacion);

        // Optional simple fields in Consulta namespace
        if (!empty($query->seriesNumber)) {
            $filtro->appendChild($doc->createElementNS(self::QUERY_NAMESPACE, 'sf:NumSerieFactura', (string) $query->seriesNumber));
        }

        // Contraparte (Consulta ns) with children from SF ns
        $counterparty = $query->getCounterparty();
        if (!empty($counterparty) && is_array($counterparty)) {
            $contraparte = $doc->createElementNS(self::QUERY_NAMESPACE, 'sf:Contraparte');
            // Order matters: NombreRazon first, then identification (NIF/IDOtro)
            if (!empty($counterparty['name'])) {
                $contraparte->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazon', (string) $counterparty['name']));
            }
            if (!empty($counterparty['nif'])) {
                $contraparte->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NIF', (string) $counterparty['nif']));
            }
            if (!empty($counterparty['otherId'])) {
                // Build IDOtro block in SF ns if provided as associative array
                if (is_array($counterparty['otherId'])) {
                    $idOtro = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDOtro');
                    if (!empty($counterparty['otherId']['countryCode'])) {
                        $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CodigoPais', (string) $counterparty['otherId']['countryCode']));
                    }
                    if (!empty($counterparty['otherId']['idType'])) {
                        $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDType', (string) $counterparty['otherId']['idType']));
                    }
                    if (!empty($counterparty['otherId']['id'])) {
                        $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:ID', (string) $counterparty['otherId']['id']));
                    }
                    $contraparte->appendChild($idOtro);
                }
            }
            $filtro->appendChild($contraparte);
        }

        // FechaExpedicionFactura (Consulta ns) wrapping SF choice type
        if (!empty($query->issueDate)) {
            $fechaWrapper = $doc->createElementNS(self::QUERY_NAMESPACE, 'sf:FechaExpedicionFactura');
            $fechaWrapper->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaExpedicionFactura', (string) $query->issueDate));
            $filtro->appendChild($fechaWrapper);
        }

        if (!empty($query->externalRef)) {
            $filtro->appendChild($doc->createElementNS(self::QUERY_NAMESPACE, 'sf:RefExterna', (string) $query->externalRef));
        }

        // Note: SistemaInformatico and ClavePaginacion are optional; omit unless full data is available

        if ($validate) {
            self::validateXml($doc, __DIR__ . '/../schemes/ConsultaLR.xsd');
        }

        return $doc;
    }

    public static function validateXml(\DOMDocument $doc, string $schemaPath): void
    {
        libxml_use_internal_errors(true);
        if (!$doc->schemaValidate($schemaPath)) {
            $errors = "";
            foreach (libxml_get_errors() as $error) {
                $errors .= $error->message . PHP_EOL;
            }
            throw new \Exception('The XML generated does not comply with schema: ' . $errors);
        }
    }

    public static function wrapXmlWithRegFactuStructure(\DOMDocument $doc, string $nif, string $name): \DOMDocument
    {
        $newDoc = new \DOMDocument('1.0', 'UTF-8');
        $newDoc->formatOutput = true;

        $root = $newDoc->createElementNS(self::SFLR_NAMESPACE, 'sfLR:RegFactuSistemaFacturacion');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sf', self::SF_NAMESPACE);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', self::DS_NAMESPACE);
        $newDoc->appendChild($root);

        $cabecera = $newDoc->createElementNS(self::SFLR_NAMESPACE, 'sfLR:Cabecera');
        $root->appendChild($cabecera);

        $obligadoEmision = $newDoc->createElementNS(self::SF_NAMESPACE, 'sf:ObligadoEmision');
        $cabecera->appendChild($obligadoEmision);
        // Orden según schema PersonaFisicaJuridicaESType: NombreRazon, NIF
        $obligadoEmision->appendChild($newDoc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazon', (string) $name));
        $obligadoEmision->appendChild($newDoc->createElementNS(self::SF_NAMESPACE, 'sf:NIF', (string) $nif));


//        $remReq = $newDoc->createElementNS(self::SF_NAMESPACE, 'sf:RemisionRequerimiento');
//        $remReq->appendChild($newDoc->createElementNS(self::SF_NAMESPACE, 'sf:RefRequerimiento', 'TEST' . date('YmdHis')));
//        $remReq->appendChild($newDoc->createElementNS(self::SF_NAMESPACE, 'sf:FinRequerimiento', 'S'));
//        $cabecera->appendChild($remReq);

        $registroFactura = $newDoc->createElementNS(self::SFLR_NAMESPACE, 'sfLR:RegistroFactura');
        $root->appendChild($registroFactura);

        $imported = $newDoc->importNode($doc->documentElement, true);
        $registroFactura->appendChild($imported);

        return $newDoc;
    }

    private static function buildSistemaInformatico(\DOMDocument $doc, ?ComputerSystem $system, ?string $issuerNifForDefault): \DOMElement
    {
        $sistemaInformatico = $doc->createElementNS(self::SF_NAMESPACE, 'sf:SistemaInformatico');
        $providerName = $system?->providerName ?? 'Proveedor Sistema';
        $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazon', (string) $providerName));

        $providerId = $system?->getProviderId();
        if ($providerId && !empty($providerId->nif)) {
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NIF', (string) $providerId->nif));
        } elseif ($providerId && ($otherId = $providerId->getOtherId())) {
            $idOtro = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDOtro');
            if (!empty($otherId->countryCode)) {
                $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CodigoPais', (string) $otherId->countryCode));
            }
            if (!empty($otherId->idType)) {
                $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDType', (string) $otherId->idType));
            }
            if (!empty($otherId->id)) {
                $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:ID', (string) $otherId->id));
            }
            $sistemaInformatico->appendChild($idOtro);
        } else {
            $fallbackNif = $issuerNifForDefault ?: '12345678Z';
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NIF', (string) $fallbackNif));
        }

        $nombreSistema = $system?->systemName ?? 'Sistema';
        $idSistema = $system?->systemId ?? '01';
        $version = $system?->version ?? '1.0';
        $numInstalacion = $system?->installationNumber ?? '1';
        $soloVerifactu = $system?->onlyVerifactu?->value ?? 'S';
        $multiOT = $system?->multipleObligations?->value ?? 'N';
        $indicadorMulti = $system?->hasMultipleObligations?->value ?? 'N';

        $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreSistemaInformatico', (string) $nombreSistema));
        $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IdSistemaInformatico', (string) $idSistema));
        $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Version', (string) $version));
        $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumeroInstalacion', (string) $numInstalacion));
        $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoUsoPosibleSoloVerifactu', (string) $soloVerifactu));
        $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoUsoPosibleMultiOT', (string) $multiOT));
        $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IndicadorMultiplesOT', (string) $indicadorMulti));

        return $sistemaInformatico;
    }
}
