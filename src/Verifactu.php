<?php
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
    const ENVIRONMENT_PRODUCTION = 'production';
    const ENVIRONMENT_SANDBOX = 'sandbox';


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
    const QR_VERIFICATION_URL_PRODUCTION = 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR';

    /**
     * QR verification URL (testing/homologation).
     */
    const QR_VERIFICATION_URL_TEST = 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR';


    const TYPE_CERTIFICATE = 'certificate';
    const TYPE_SEAL = 'seal';

    /**
     * @param $certPath string Path to the certificate file.
     * @param $certPassword string Password for the certificate.
     * @param $certType string Type of certificate, either 'certificate' or 'seal'.
     * @param $environment string Environment to use, either 'production' or 'sandbox'.
     * @return void
     */
    public static function config($certPath, $certPassword, $certType, $environment = self::ENVIRONMENT_PRODUCTION)
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

        VerifactuService::config([
            VerifactuService::CERT_PATH_KEY => $certPath,
            VerifactuService::CERT_PASSWORD_KEY => $certPassword,
            VerifactuService::WSDL_ENDPOINT => $endpoint,
            VerifactuService::QR_VERIFICATION_URL => $qrValidationUrl,
        ]);
    }

    /**
     * Registers a new invoice (Alta) with AEAT via VERI*FACTU.
     *
     * @param InvoiceSubmission $invoice
     * @return InvoiceResponse
     * @throws \DOMException
     * @throws \SoapFault
     */
    public static function registerInvoice(InvoiceSubmission $invoice): InvoiceResponse
    {
        return VerifactuService::registerInvoice($invoice);
    }

    /**
     * Cancels an invoice (Anulación) with AEAT via VERI*FACTU.
     *
     * @param InvoiceCancellation $cancellation
     * @return InvoiceResponse
     */
    public static function cancelInvoice(InvoiceCancellation $cancellation): InvoiceResponse
    {
        return VerifactuService::cancelInvoice($cancellation);
    }

    /**
     * Queries submitted invoices from AEAT via VERI*FACTU.
     *
     * @param InvoiceQuery $query
     * @return QueryResponse
     */
    public static function queryInvoices(InvoiceQuery $query): QueryResponse
    {
        return VerifactuService::queryInvoices($query);
    }

    /**
     * Generates a base64 QR code for the provided invoice.
     *
     * @param InvoiceRecord $record
     * @return string base64-encoded PNG QR code
     */
    public static function generateInvoiceQr(InvoiceRecord $record): string
    {
        return VerifactuService::generateInvoiceQr($record);
    }

}
