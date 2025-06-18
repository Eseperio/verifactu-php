<?php
namespace eseperio\verifactu\services;

use eseperio\verifactu\models\InvoiceRecord;
use BaconQrCode\Writer;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;

/**
 * Service responsible for generating base64 PNG QR codes
 * for invoices according to the AEAT Verifactu specification,
 * using bacon/bacon-qr-code only.
 */
class QrGeneratorService
{
    /**
     * Generates a base64-encoded PNG QR code for a given invoice record,
     * using the AEAT Verifactu QR specification (URL and fields).
     *
     * @param InvoiceRecord $record
     * @param string $baseVerificationUrl Base URL for AEAT invoice verification
     * @return string Base64-encoded PNG QR image
     * @throws \RuntimeException
     */
    public static function generateQr(InvoiceRecord $record, $baseVerificationUrl)
    {
        $qrContent = self::buildQrContent($record, $baseVerificationUrl);

        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new ImagickImageBackEnd()
        );
        $writer = new Writer($renderer);
        $pngData = $writer->writeString($qrContent);

        return base64_encode($pngData);
    }

    /**
     * Builds the QR content string according to AEAT specification.
     *
     * @param InvoiceRecord $record
     * @param string $baseVerificationUrl
     * @return string
     */
    protected static function buildQrContent(InvoiceRecord $record, $baseVerificationUrl)
    {
        $nif = $record->invoiceId->issuerNif;
        $series = $record->invoiceId->seriesNumber;
        $date = $record->invoiceId->issueDate;
        $hash = $record->hash;

        $params = http_build_query([
            'nif' => $nif,
            'num' => $series,
            'fecha' => $date,
            'huella' => $hash,
        ]);

        return rtrim($baseVerificationUrl, '?') . '?' . $params;
    }
}
