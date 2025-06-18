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
 * Service responsible for generating QR codes
 * for invoices according to the AEAT Verifactu specification,
 * using bacon/bacon-qr-code only.
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
