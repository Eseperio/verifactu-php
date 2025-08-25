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
    public static function toInvoiceXml(InvoiceSubmission $invoice, bool $validate = true): \DOMDocument
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
            $idFactura->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDEmisorFactura', $invoiceId->issuerNif));
            $idFactura->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumSerieFactura', $invoiceId->seriesNumber));
            $idFactura->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaExpedicionFactura', $invoiceId->issueDate));
            $root->appendChild($idFactura);
        }

        // RefExterna (optional)
        if (!empty($invoice->externalRef)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:RefExterna', $invoice->externalRef));
        }

        // NombreRazonEmisor (required)
        $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazonEmisor', $invoice->issuerName));

        // Subsanacion (optional)
        if (isset($invoice->correction)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Subsanacion', $invoice->correction->value));
        }

        // RechazoPrevio (optional)
        if (isset($invoice->previousRejection)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:RechazoPrevio', $invoice->previousRejection->value));
        }

        // TipoFactura (required)
        if ($invoice->invoiceType) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoFactura', $invoice->invoiceType->value));
        }

        // TipoRectificativa (optional)
        if ($invoice->rectificationType) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoRectificativa', $invoice->rectificationType->value));
        }

        // FacturasRectificadas (optional)
        $rectData = $invoice->getRectificationData();
        if (!empty($rectData['rectified'])) {
            $facturasRectificadas = $doc->createElementNS(self::SF_NAMESPACE, 'sf:FacturasRectificadas');
            foreach ($rectData['rectified'] as $rect) {
                $idFacturaRectificada = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDFacturaRectificada');
                $idFacturaRectificada->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDEmisorFactura', $rect['issuerNif']));
                $idFacturaRectificada->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumSerieFactura', $rect['seriesNumber']));
                $idFacturaRectificada->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaExpedicionFactura', $rect['issueDate']));
                $facturasRectificadas->appendChild($idFacturaRectificada);
            }
            $root->appendChild($facturasRectificadas);
        }

        // FacturasSustituidas (optional)
        if (!empty($rectData['substituted'])) {
            $facturasSustituidas = $doc->createElementNS(self::SF_NAMESPACE, 'sf:FacturasSustituidas');
            foreach ($rectData['substituted'] as $subst) {
                $idFacturaSustituida = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDFacturaSustituida');
                $idFacturaSustituida->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDEmisorFactura', $subst['issuerNif']));
                $idFacturaSustituida->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumSerieFactura', $subst['seriesNumber']));
                $idFacturaSustituida->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaExpedicionFactura', $subst['issueDate']));
                $facturasSustituidas->appendChild($idFacturaSustituida);
            }
            $root->appendChild($facturasSustituidas);
        }

        // ImporteRectificacion (optional)
        $rectBreakdown = $invoice->getRectificationBreakdown();
        if ($rectBreakdown) {
            $importeRectificacion = $doc->createElementNS(self::SF_NAMESPACE, 'sf:ImporteRectificacion');
            $importeRectificacion->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:BaseRectificada', 
                number_format($rectBreakdown->rectifiedBase, 2, '.', '')));
            $importeRectificacion->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CuotaRectificada', 
                number_format($rectBreakdown->rectifiedTax, 2, '.', '')));
            
            if (!is_null($rectBreakdown->rectifiedEquivalenceSurcharge)) {
                $importeRectificacion->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CuotaRecargoRectificado', 
                    number_format($rectBreakdown->rectifiedEquivalenceSurcharge, 2, '.', '')));
            }
            
            $root->appendChild($importeRectificacion);
        }

        // FechaOperacion (optional)
        if (!empty($invoice->operationDate)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaOperacion', $invoice->operationDate));
        }

        // DescripcionOperacion (required)
        $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:DescripcionOperacion', $invoice->operationDescription));

        // FacturaSimplificadaArt7273 (optional)
        if ($invoice->simplifiedInvoice) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FacturaSimplificadaArt7273', $invoice->simplifiedInvoice->value));
        }

        // FacturaSinIdentifDestinatarioArt61d (optional)
        if ($invoice->invoiceWithoutRecipient) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FacturaSinIdentifDestinatarioArt61d', $invoice->invoiceWithoutRecipient->value));
        }

        // Macrodato (optional)
        if ($invoice->macrodata) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Macrodato', $invoice->macrodata->value));
        }

        // EmitidaPorTerceroODestinatario (optional)
        if ($invoice->issuedBy) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:EmitidaPorTerceroODestinatario', $invoice->issuedBy->value));
        }

        // Tercero (optional)
        $thirdParty = $invoice->getThirdParty();
        if ($thirdParty) {
            $tercero = $doc->createElementNS(self::SF_NAMESPACE, 'sf:Tercero');
            $tercero->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazon', $thirdParty->name));
            
            if (!empty($thirdParty->nif)) {
                $tercero->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NIF', $thirdParty->nif));
            } elseif ($otherId = $thirdParty->getOtherId()) {
                $idOtro = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDOtro');
                
                if (!empty($otherId->countryCode)) {
                    $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CodigoPais', $otherId->countryCode));
                }
                
                if (!empty($otherId->idType)) {
                    $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDType', $otherId->idType));
                }
                
                if (!empty($otherId->id)) {
                    $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:ID', $otherId->id));
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
                $idDestinatario->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazon', $recipient->name));
                
                if (!empty($recipient->nif)) {
                    $idDestinatario->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NIF', $recipient->nif));
                } elseif ($otherId = $recipient->getOtherId()) {
                    $idOtro = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDOtro');
                    
                    if (!empty($otherId->countryCode)) {
                        $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CodigoPais', $otherId->countryCode));
                    }
                    
                    if (!empty($otherId->idType)) {
                        $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDType', $otherId->idType));
                    }
                    
                    if (!empty($otherId->id)) {
                        $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:ID', $otherId->id));
                    }
                    
                    $idDestinatario->appendChild($idOtro);
                }
                
                $destinatarios->appendChild($idDestinatario);
            }
            
            $root->appendChild($destinatarios);
        }

        // Cupon (optional)
        if ($invoice->coupon) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Cupon', $invoice->coupon->value));
        }

        // Desglose (required)
        $breakdown = $invoice->getBreakdown();
        if ($breakdown) {
            $desglose = $doc->createElementNS(self::SF_NAMESPACE, 'sf:Desglose');
            
            foreach ($breakdown->getDetails() as $detail) {
                $detalleDesglose = $doc->createElementNS(self::SF_NAMESPACE, 'sf:DetalleDesglose');
                
                // Impuesto (optional)
                if ($detail->taxType) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Impuesto', $detail->taxType->value));
                }
                
                // ClaveRegimen (optional)
                if (!empty($detail->regimeKey)) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:ClaveRegimen', $detail->regimeKey));
                }
                
                // Either CalificacionOperacion or OperacionExenta (one is required)
                if ($detail->operationQualification) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CalificacionOperacion', 
                        $detail->operationQualification->value));
                } elseif ($detail->exemptOperation) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:OperacionExenta', 
                        $detail->exemptOperation->value));
                }
                
                // TipoImpositivo (optional)
                if (!is_null($detail->taxRate)) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoImpositivo', 
                        number_format($detail->taxRate, 2, '.', '')));
                }
                
                // BaseImponibleOimporteNoSujeto (required)
                $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:BaseImponibleOimporteNoSujeto', 
                    number_format($detail->taxableBase, 2, '.', '')));
                
                // BaseImponibleACoste (optional)
                if (!is_null($detail->costBasedTaxableBase)) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:BaseImponibleACoste', 
                        number_format($detail->costBasedTaxableBase, 2, '.', '')));
                }
                
                // CuotaRepercutida (optional)
                if (!is_null($detail->taxAmount)) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CuotaRepercutida', 
                        number_format($detail->taxAmount, 2, '.', '')));
                }
                
                // TipoRecargoEquivalencia (optional)
                if (!is_null($detail->equivalenceSurchargeRate)) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoRecargoEquivalencia', 
                        number_format($detail->equivalenceSurchargeRate, 2, '.', '')));
                }
                
                // CuotaRecargoEquivalencia (optional)
                if (!is_null($detail->equivalenceSurchargeAmount)) {
                    $detalleDesglose->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CuotaRecargoEquivalencia', 
                        number_format($detail->equivalenceSurchargeAmount, 2, '.', '')));
                }
                
                $desglose->appendChild($detalleDesglose);
            }
            
            $root->appendChild($desglose);
        }

        // CuotaTotal (required)
        $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CuotaTotal', 
            number_format($invoice->taxAmount, 2, '.', '')));

        // ImporteTotal (required)
        $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:ImporteTotal', 
            number_format($invoice->totalAmount, 2, '.', '')));

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
                    $registroAnterior->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDEmisorFactura', $previousInvoice->issuerNif));
                    $registroAnterior->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumSerieFactura', $previousInvoice->seriesNumber));
                    $registroAnterior->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaExpedicionFactura', $previousInvoice->issueDate));
                    $registroAnterior->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Huella', $previousInvoice->hash));
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
        if ($computerSystem) {
            $sistemaInformatico = $doc->createElementNS(self::SF_NAMESPACE, 'sf:SistemaInformatico');
            
            // Add provider name
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazon', $computerSystem->providerName));
            
            // Add provider ID (NIF or IDOtro)
            $providerId = $computerSystem->getProviderId();
            if ($providerId) {
                if (!empty($providerId->nif)) {
                    $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NIF', $providerId->nif));
                } elseif ($otherId = $providerId->getOtherId()) {
                    $idOtro = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDOtro');
                    
                    if (!empty($otherId->countryCode)) {
                        $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CodigoPais', $otherId->countryCode));
                    }
                    
                    if (!empty($otherId->idType)) {
                        $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDType', $otherId->idType));
                    }
                    
                    if (!empty($otherId->id)) {
                        $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:ID', $otherId->id));
                    }
                    
                    $sistemaInformatico->appendChild($idOtro);
                }
            }
            
            // Add system details
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreSistemaInformatico', $computerSystem->systemName));
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IdSistemaInformatico', $computerSystem->systemId));
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Version', $computerSystem->version));
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumeroInstalacion', $computerSystem->installationNumber));
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoUsoPosibleSoloVerifactu', $computerSystem->onlyVerifactu->value));
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoUsoPosibleMultiOT', $computerSystem->multipleObligations->value));
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IndicadorMultiplesOT', $computerSystem->hasMultipleObligations->value));
            
            $root->appendChild($sistemaInformatico);
        } else {
            // Create a default SistemaInformatico node if not provided
            $sistemaInformatico = $doc->createElementNS(self::SF_NAMESPACE, 'sf:SistemaInformatico');
            $root->appendChild($sistemaInformatico);
        }

        // FechaHoraHusoGenRegistro (required)
        if (!empty($invoice->recordTimestamp)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaHoraHusoGenRegistro', $invoice->recordTimestamp));
        }

        // NumRegistroAcuerdoFacturacion (optional)
        if (!empty($invoice->invoiceAgreementNumber)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumRegistroAcuerdoFacturacion', $invoice->invoiceAgreementNumber));
        }

        // IdAcuerdoSistemaInformatico (optional)
        if (!empty($invoice->systemAgreementId)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IdAcuerdoSistemaInformatico', $invoice->systemAgreementId));
        }

        // TipoHuella (required)
        if ($invoice->hashType) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoHuella', $invoice->hashType->value));
        }

        // Huella (required)
        if (!empty($invoice->hash)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Huella', $invoice->hash));
        }

        // Optionally validate the XML document
        if ($validate) {
            self::validateXml($doc, __DIR__ . '/../schemes/SuministroInformacion.xsd');
        }
        
        return $doc;
    }

    /**
     * Serializes an InvoiceCancellation model to XML.
     *
     * @param InvoiceCancellation $cancellation The cancellation to serialize
     * @param bool $validate Whether to validate the XML against the XSD schema
     * @return \DOMDocument The XML document
     * @throws \Exception If validation fails
     */
    public static function toCancellationXml(InvoiceCancellation $cancellation, bool $validate = true): \DOMDocument
    {
        // Create the XML document
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        // Create root element with namespace
        $root = $doc->createElementNS(self::SF_NAMESPACE, 'sf:RegistroAnulacion');
        $doc->appendChild($root);

        // IDVersion (required, hardcoded as '1.0')
        $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDVersion', '1.0'));

        // IDFactura (required)
        $invoiceId = $cancellation->getInvoiceId();
        if ($invoiceId) {
            $idFactura = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDFactura');
            $idFactura->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDEmisorFacturaAnulada', $invoiceId->issuerNif));
            $idFactura->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumSerieFacturaAnulada', $invoiceId->seriesNumber));
            $idFactura->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaExpedicionFacturaAnulada', $invoiceId->issueDate));
            $root->appendChild($idFactura);
        }

        // RefExterna (optional)
        if (!empty($cancellation->externalReference)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:RefExterna', $cancellation->externalReference));
        }

        // SinRegistroPrevio (optional)
        if ($cancellation->noPreviousRecord) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:SinRegistroPrevio', $cancellation->noPreviousRecord->value));
        }

        // RechazoPrevio (optional)
        if ($cancellation->previousRejection) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:RechazoPrevio', $cancellation->previousRejection->value));
        }

        // GeneradoPor (optional)
        if ($cancellation->generator) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:GeneradoPor', $cancellation->generator->value));
        }

        // Generador (optional)
        $generatorData = $cancellation->getGeneratorData();
        if ($generatorData) {
            $generador = $doc->createElementNS(self::SF_NAMESPACE, 'sf:Generador');
            $generador->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazon', $generatorData->name));
            
            if (!empty($generatorData->nif)) {
                $generador->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NIF', $generatorData->nif));
            } elseif ($otherId = $generatorData->getOtherId()) {
                $idOtro = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDOtro');
                
                if (!empty($otherId->countryCode)) {
                    $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CodigoPais', $otherId->countryCode));
                }
                
                if (!empty($otherId->idType)) {
                    $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDType', $otherId->idType));
                }
                
                if (!empty($otherId->id)) {
                    $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:ID', $otherId->id));
                }
                
                $generador->appendChild($idOtro);
            }
            
            $root->appendChild($generador);
        }

        // Encadenamiento (required)
        $encadenamiento = $doc->createElementNS(self::SF_NAMESPACE, 'sf:Encadenamiento');
        
        $chaining = $cancellation->getChaining();
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
                    $registroAnterior->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDEmisorFactura', $previousInvoice->issuerNif));
                    $registroAnterior->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumSerieFactura', $previousInvoice->seriesNumber));
                    $registroAnterior->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaExpedicionFactura', $previousInvoice->issueDate));
                    $registroAnterior->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Huella', $previousInvoice->hash));
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
        $computerSystem = $cancellation->getSystemInfo();
        if ($computerSystem) {
            $sistemaInformatico = $doc->createElementNS(self::SF_NAMESPACE, 'sf:SistemaInformatico');
            
            // Add provider name
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazon', $computerSystem->providerName));
            
            // Add provider ID (NIF or IDOtro)
            $providerId = $computerSystem->getProviderId();
            if ($providerId) {
                if (!empty($providerId->nif)) {
                    $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NIF', $providerId->nif));
                } elseif ($otherId = $providerId->getOtherId()) {
                    $idOtro = $doc->createElementNS(self::SF_NAMESPACE, 'sf:IDOtro');
                    
                    if (!empty($otherId->countryCode)) {
                        $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:CodigoPais', $otherId->countryCode));
                    }
                    
                    if (!empty($otherId->idType)) {
                        $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IDType', $otherId->idType));
                    }
                    
                    if (!empty($otherId->id)) {
                        $idOtro->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:ID', $otherId->id));
                    }
                    
                    $sistemaInformatico->appendChild($idOtro);
                }
            }
            
            // Add system details
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreSistemaInformatico', $computerSystem->systemName));
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IdSistemaInformatico', $computerSystem->systemId));
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Version', $computerSystem->version));
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumeroInstalacion', $computerSystem->installationNumber));
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoUsoPosibleSoloVerifactu', $computerSystem->onlyVerifactu->value));
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoUsoPosibleMultiOT', $computerSystem->multipleObligations->value));
            $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:IndicadorMultiplesOT', $computerSystem->hasMultipleObligations->value));
            
            $root->appendChild($sistemaInformatico);
        } else {
            // Create a default SistemaInformatico node if not provided
            $sistemaInformatico = $doc->createElementNS(self::SF_NAMESPACE, 'sf:SistemaInformatico');
            $root->appendChild($sistemaInformatico);
        }

        // FechaHoraHusoGenRegistro (required)
        if (!empty($cancellation->recordTimestamp)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaHoraHusoGenRegistro', $cancellation->recordTimestamp));
        }

        // TipoHuella (required)
        if ($cancellation->hashType) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:TipoHuella', $cancellation->hashType->value));
        }

        // Huella (required)
        if (!empty($cancellation->hash)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Huella', $cancellation->hash));
        }

        // Optionally validate the XML document
        if ($validate) {
            self::validateXml($doc, __DIR__ . '/../schemes/SuministroInformacion.xsd');
        }
        
        return $doc;
    }

    /**
     * Serializes an InvoiceQuery model to XML.
     *
     * @param InvoiceQuery $query The query to serialize
     * @param bool $validate Whether to validate the XML against the XSD schema
     * @return \DOMDocument The XML document
     * @throws \Exception If validation fails
     */
    public static function toQueryXml(InvoiceQuery $query, bool $validate = true): \DOMDocument
    {
        // Create the XML document
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        // Create root element with namespace
        // Note: While the original doesn't use namespaces, we'll add them for consistency
        $root = $doc->createElementNS(self::SF_NAMESPACE, 'sf:ConsultaFactuSistemaFacturacion');
        $doc->appendChild($root);

        // Ejercicio (required) - The year
        $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Ejercicio', $query->year));

        // Periodo (required) - The period
        $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:Periodo', $query->period));

        // NumSerieFactura (optional) - The series number
        if (!empty($query->seriesNumber)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NumSerieFactura', $query->seriesNumber));
        }

        // Contraparte (optional) - The counterparty information
        $counterparty = $query->getCounterparty();
        if (!empty($counterparty) && is_array($counterparty)) {
            $contraparte = $doc->createElementNS(self::SF_NAMESPACE, 'sf:Contraparte');
            
            if (!empty($counterparty['nif'])) {
                $contraparte->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NIF', $counterparty['nif']));
            }
            
            if (!empty($counterparty['name'])) {
                $contraparte->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazon', $counterparty['name']));
            }
            
            if (!empty($counterparty['otherId'])) {
                $contraparte->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:OtroID', $counterparty['otherId']));
            }
            
            $root->appendChild($contraparte);
        }

        // FechaExpedicionFactura (optional) - The issue date
        if (!empty($query->issueDate)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:FechaExpedicionFactura', $query->issueDate));
        }

        // SistemaInformatico (optional) - The system information
        $systemInfo = $query->getSystemInfo();
        if (!empty($systemInfo) && is_array($systemInfo)) {
            $sistemaInformatico = $doc->createElementNS(self::SF_NAMESPACE, 'sf:SistemaInformatico');
            
            foreach ($systemInfo as $key => $value) {
                // Convert key to proper element name with namespace
                $elementName = 'sf:' . ucfirst($key);
                $sistemaInformatico->appendChild($doc->createElementNS(self::SF_NAMESPACE, $elementName, $value));
            }
            
            $root->appendChild($sistemaInformatico);
        }

        // RefExterna (optional) - The external reference
        if (!empty($query->externalRef)) {
            $root->appendChild($doc->createElementNS(self::SF_NAMESPACE, 'sf:RefExterna', $query->externalRef));
        }

        // ClavePaginacion (optional) - The pagination key
        $paginationKey = $query->getPaginationKey();
        if (!empty($paginationKey) && is_array($paginationKey)) {
            $clavePaginacion = $doc->createElementNS(self::SF_NAMESPACE, 'sf:ClavePaginacion');
            
            foreach ($paginationKey as $key => $value) {
                // Convert key to proper element name with namespace
                $elementName = 'sf:' . ucfirst($key);
                $clavePaginacion->appendChild($doc->createElementNS(self::SF_NAMESPACE, $elementName, $value));
            }
            
            $root->appendChild($clavePaginacion);
        }

        // Optionally validate the XML document
        if ($validate) {
            self::validateXml($doc, __DIR__ . '/../schemes/SuministroInformacion.xsd');
        }
        
        return $doc;
    }

    /**
     * Validates an XML document against an XSD schema.
     *
     * @param \DOMDocument $doc The XML document to validate
     * @param string $schemaPath The path to the XSD schema
     * @throws \Exception If validation fails
     */
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

    /**
     * Wraps an XML element in the proper RegFactuSistemaFacturacion structure with Cabecera.
     *
     * @param \DOMDocument $doc The document to wrap
     * @param string $nif The NIF of the issuer
     * @param string $name The name of the issuer
     * @return \DOMDocument The wrapped document
     */
    public static function wrapXmlWithRegFactuStructure(\DOMDocument $doc, string $nif, string $name): \DOMDocument
    {
        $newDoc = new \DOMDocument('1.0', 'UTF-8');
        $newDoc->formatOutput = true;

        // root: sfLR:RegFactuSistemaFacturacion
        $root = $newDoc->createElementNS(self::SFLR_NAMESPACE, 'sfLR:RegFactuSistemaFacturacion');
        // declare other namespaces on root
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sf', self::SF_NAMESPACE);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', self::DS_NAMESPACE);
        $newDoc->appendChild($root);

        // sfLR:Cabecera (element in sfLR NS, type CabeceraType from sf)
        $cabecera = $newDoc->createElementNS(self::SFLR_NAMESPACE, 'sfLR:Cabecera');
        $root->appendChild($cabecera);

        // sf:ObligadoEmision
        $obligadoEmision = $newDoc->createElementNS(self::SF_NAMESPACE, 'sf:ObligadoEmision');
        $cabecera->appendChild($obligadoEmision);
        $obligadoEmision->appendChild($newDoc->createElementNS(self::SF_NAMESPACE, 'sf:NIF', $nif));
        $obligadoEmision->appendChild($newDoc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazon', $name));

        // sf:Representante (optional)
        $representante = $newDoc->createElementNS(self::SF_NAMESPACE, 'sf:Representante');
        $representante->appendChild($newDoc->createElementNS(self::SF_NAMESPACE, 'sf:NIF', $nif));
        $representante->appendChild($newDoc->createElementNS(self::SF_NAMESPACE, 'sf:NombreRazon', $name));
        $cabecera->appendChild($representante);

        // sf:RemisionRequerimiento (only for NO-VERIFACTU flows; remove for Veri*factu)
        $remReq = $newDoc->createElementNS(self::SF_NAMESPACE, 'sf:RemisionRequerimiento');
        $remReq->appendChild($newDoc->createElementNS(self::SF_NAMESPACE, 'sf:RefRequerimiento', 'TEST' . date('YmdHis')));
        $remReq->appendChild($newDoc->createElementNS(self::SF_NAMESPACE, 'sf:FinRequerimiento', 'S'));
        $cabecera->appendChild($remReq);

        // sfLR:RegistroFactura
        $registroFactura = $newDoc->createElementNS(self::SFLR_NAMESPACE, 'sfLR:RegistroFactura');
        $root->appendChild($registroFactura);

        // import original payload (must be sf:RegistroAlta or sf:RegistroAnulacion in SF NS)
        $imported = $newDoc->importNode($doc->documentElement, true);
        $registroFactura->appendChild($imported);

        return $newDoc;
    }
}
