<?php

declare(strict_types=1);
// Main entry point of the Verifactu library

namespace eseperio\verifactu;

use eseperio\verifactu\models\InvoiceCancellation;
use eseperio\verifactu\models\InvoiceQuery;
use eseperio\verifactu\models\InvoiceRecord;
use eseperio\verifactu\models\InvoiceResponse;
use eseperio\verifactu\models\InvoiceSubmission;
use eseperio\verifactu\models\QueryResponse;
use eseperio\verifactu\services\VerifactuService;

class Verifactu
{
    public const ENVIRONMENT_PRODUCTION = 'production';
    public const ENVIRONMENT_SANDBOX = 'sandbox';

    /**
     * Production environment URL.
     */
    public const URL_PRODUCTION = 'https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';

    /**
     * Production environment URL (seal certificate).
     */
    public const URL_PRODUCTION_SEAL = 'https://www10.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';

    /**
     * Test (homologation) environment URL.
     */
    public const URL_TEST = 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';

    /**
     * Test (seal certificate) environment URL.
     */
    public const URL_TEST_SEAL = 'https://prewww10.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';

    /**
     * QR verification URL (production).
     */
    public const QR_VERIFICATION_URL_PRODUCTION = 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR';

    /**
     * QR verification URL (testing/homologation).
     */
    public const QR_VERIFICATION_URL_TEST = 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR';

    public const TYPE_CERTIFICATE = 'certificate';
    public const TYPE_SEAL = 'seal';

    /**
     * Certificate content (PEM/PFX/P12).
     * @var string|null
     */
    protected static ?string $certContent = null;

    /**
     * Certificate type ('certificate' or 'seal').
     * @var string|null
     */
    protected static ?string $certContentType = null;

    /**
     * Configuration using certificate file path (backward compatibility).
     * @param string $certPath Path to the certificate file.
     * @param string $certPassword Password for the certificate.
     * @param string $certType Type of certificate, either 'certificate' or 'seal'.
     * @param string $environment Environment to use, either 'production' or 'sandbox'.
     */
    public static function config($certPath, $certPassword, $certType, $environment = self::ENVIRONMENT_PRODUCTION): void
    {
        $endpoint = match ($environment) {
            self::ENVIRONMENT_PRODUCTION => $certType === self::TYPE_SEAL ? self::URL_PRODUCTION_SEAL : self::URL_PRODUCTION,
            self::ENVIRONMENT_SANDBOX => $certType === self::TYPE_SEAL ? self::URL_TEST_SEAL : self::URL_TEST,
            default => throw new \InvalidArgumentException("Invalid environment: $environment")
        };

        $qrValidationUrl = match ($environment) {
            self::ENVIRONMENT_PRODUCTION => self::QR_VERIFICATION_URL_PRODUCTION,
            self::ENVIRONMENT_SANDBOX => self::QR_VERIFICATION_URL_TEST,
            default => throw new \InvalidArgumentException("Invalid environment: $environment")
        };

        self::$certContent = null;
        self::$certContentType = null;

        VerifactuService::config([
            VerifactuService::CERT_PATH_KEY => $certPath,
            VerifactuService::CERT_PASSWORD_KEY => $certPassword,
            VerifactuService::WSDL_ENDPOINT => $endpoint,
            VerifactuService::QR_VERIFICATION_URL => $qrValidationUrl,
        ]);
    }

    /**
     * Alternative configuration using certificate content string.
     * @param string $certContent Certificate content (PEM/PFX/P12)
     * @param string $certPassword Certificate password
     * @param string $certType Type of certificate ('certificate' or 'seal')
     * @param string $environment Environment ('production' or 'sandbox')
     */
    public static function configWithContent($certContent, $certPassword, $certType, $environment = self::ENVIRONMENT_PRODUCTION): void
    {
        $endpoint = match ($environment) {
            self::ENVIRONMENT_PRODUCTION => $certType === self::TYPE_SEAL ? self::URL_PRODUCTION_SEAL : self::URL_PRODUCTION,
            self::ENVIRONMENT_SANDBOX => $certType === self::TYPE_SEAL ? self::URL_TEST_SEAL : self::URL_TEST,
            default => throw new \InvalidArgumentException("Invalid environment: $environment")
        };

        $qrValidationUrl = match ($environment) {
            self::ENVIRONMENT_PRODUCTION => self::QR_VERIFICATION_URL_PRODUCTION,
            self::ENVIRONMENT_SANDBOX => self::QR_VERIFICATION_URL_TEST,
            default => throw new \InvalidArgumentException("Invalid environment: $environment")
        };

        self::$certContent = $certContent;
        self::$certContentType = $certType;

        VerifactuService::config([
            VerifactuService::CERT_PATH_KEY => null,
            VerifactuService::CERT_PASSWORD_KEY => $certPassword,
            VerifactuService::CERT_CONTENT_KEY => $certContent,
            VerifactuService::CERT_CONTENT_TYPE_KEY => $certType,
            VerifactuService::WSDL_ENDPOINT => $endpoint,
            VerifactuService::QR_VERIFICATION_URL => $qrValidationUrl,
        ]);
    }

    /**
     * Registers a new invoice (Alta) with AEAT via VERI*FACTU.
     *
     * @throws \DOMException
     * @throws \SoapFault
     */
    public static function registerInvoice(InvoiceSubmission $invoice): InvoiceResponse
    {
        return VerifactuService::registerInvoice($invoice);
    }

    /**
     * Cancels an invoice (Anulación) with AEAT via VERI*FACTU.
     */
    public static function cancelInvoice(InvoiceCancellation $cancellation): InvoiceResponse
    {
        return VerifactuService::cancelInvoice($cancellation);
    }

    /**
     * Queries submitted invoices from AEAT via VERI*FACTU.
     */
    public static function queryInvoices(InvoiceQuery $query): QueryResponse
    {
        return VerifactuService::queryInvoices($query);
    }

    /**
     * Generates a base64 QR code for the provided invoice.
     *
     * @return string base64-encoded PNG QR code
     */
    public static function generateInvoiceQr(InvoiceRecord $record): string
    {
        return VerifactuService::generateInvoiceQr($record);
    }
}
