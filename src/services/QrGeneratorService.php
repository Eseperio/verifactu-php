<?php

namespace eseperio\verifactu\services;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use eseperio\verifactu\models\InvoiceRecord;

/**
 * Service responsible for generating QR codes for invoices according to the AEAT Verifactu specification.
 *
 * This service uses the bacon/bacon-qr-code library to generate QR codes that comply with
 * the Spanish Tax Agency (AEAT) Verifactu requirements. The QR code contains a URL with
 * embedded invoice information that can be used to verify the invoice's authenticity.
 *
 * Usage:
 * The main method is `generateQr()` which takes an InvoiceRecord object and generates
 * a QR code with the verification URL containing the invoice's key data.
 *
 * Available rendering engines:
 * - GD (RENDERER_GD): Uses PHP's GD library. Output format is PNG. Most widely compatible option.
 * - Imagick (RENDERER_IMAGICK): Uses ImageMagick for higher quality images. Output format is PNG.
 * - SVG (RENDERER_SVG): Generates vector SVG files that can be scaled without quality loss.
 *
 * Output destinations:
 * - String (DESTINATION_STRING): Returns the binary/text content of the QR image.
 * - File (DESTINATION_FILE): Saves the QR to a temporary file and returns the file path.
 *
 * Example usage:
 * ```
 * // Create an invoice record
 * $invoiceId = new InvoiceId('B12345678', 'FACT-2023-001', '31-12-2023');
 * $record = new InvoiceRecord($invoiceId, 'abcdef123456789');
 *
 * // Generate QR code as SVG string
 * $svgQrCode = QrGeneratorService::generateQr(
 *     $record,
 *     'https://sede.agenciatributaria.gob.es/verifactu',
 *     QrGeneratorService::DESTINATION_STRING,
 *     400,
 *     QrGeneratorService::RENDERER_SVG
 * );
 *
 * // Generate QR code as PNG file
 * $pngFilePath = QrGeneratorService::generateQr(
 *     $record,
 *     'https://sede.agenciatributaria.gob.es/verifactu',
 *     QrGeneratorService::DESTINATION_FILE,
 *     300,
 *     QrGeneratorService::RENDERER_GD
 * );
 * ```
 */
class QrGeneratorService
{
    /**
     * Destination constants
     */
    public const DESTINATION_FILE = 'file';
    public const DESTINATION_STRING = 'string';

    /**
     * Renderer constants
     */
    public const RENDERER_GD = 'gd';
    public const RENDERER_IMAGICK = 'imagick';
    public const RENDERER_SVG = 'svg';

    /**
     * Generates a QR code for a given invoice record,
     * using the AEAT Verifactu QR specification (URL and fields).
     *
     * @param InvoiceRecord $record
     * @param string $baseVerificationUrl Base URL for AEAT invoice verification
     * @param string $dest Destination type (file or string)
     * @param int $size Resolution of the QR code
     * @param string $engine Renderer to use (gd, imagick, svg)
     * @return string QR image data or file path
     * @throws \RuntimeException
     */
    public static function generateQr(
        InvoiceRecord $record,
                      $baseVerificationUrl,
                      $dest = self::DESTINATION_STRING,
                      $size = 300,
                      $engine = self::RENDERER_GD
    )
    {
        $qrContent = self::buildQrContent($record, $baseVerificationUrl);
        $writer = self::createWriter($engine, $size);
        $qrData = $writer->writeString($qrContent);

        if ($dest === self::DESTINATION_FILE) {
            $filePath = sys_get_temp_dir() . '/qr_' . uniqid() . self::getFileExtension($engine);
            file_put_contents($filePath, $qrData);
            return $filePath;
        }

        return $qrData;
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
        $invoiceId = $record->getInvoiceId();
        $nif = $invoiceId->issuerNif;
        $series = $invoiceId->seriesNumber;
        $date = $invoiceId->issueDate;
        $hash = $record->hash;

        $params = http_build_query([
            'nif' => $nif,
            'num' => $series,
            'fecha' => $date,
            'huella' => $hash,
        ]);

        return rtrim($baseVerificationUrl, '?') . '?' . $params;
    }

    /**
     * Creates a writer with the specified renderer and resolution.
     *
     * @param string $renderer
     * @param int $resolution
     * @return Writer
     * @throws \RuntimeException
     */
    protected static function createWriter($renderer, $resolution)
    {
        switch ($renderer) {
            case self::RENDERER_GD:
                return new Writer(new GDLibRenderer($resolution));

            case self::RENDERER_IMAGICK:
                $imageRenderer = new ImageRenderer(
                    new RendererStyle($resolution),
                    new ImagickImageBackEnd()
                );
                return new Writer($imageRenderer);

            case self::RENDERER_SVG:
                $imageRenderer = new ImageRenderer(
                    new RendererStyle($resolution),
                    new SvgImageBackEnd()
                );
                return new Writer($imageRenderer);

            default:
                throw new \RuntimeException("Unsupported renderer: {$renderer}");
        }
    }

    /**
     * Gets the file extension for the specified renderer.
     *
     * @param string $renderer
     * @return string
     */
    protected static function getFileExtension($renderer)
    {
        switch ($renderer) {
            case self::RENDERER_SVG:
                return '.svg';
            case self::RENDERER_GD:
            case self::RENDERER_IMAGICK:
            default:
                return '.png';
        }
    }
}
