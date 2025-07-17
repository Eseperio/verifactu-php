<?php
namespace eseperio\verifactu\services;

use eseperio\verifactu\models\InvoiceRecord;

/**
 * Service responsible for generating the official SHA-256 hash ("huella") for invoice records,
 * following AEAT VERI*FACTU technical specification.
 */
class HashGeneratorService
{
    /**
     * Generates the SHA-256 hash for a given invoice record, according to AEAT specs.
     * The fields concatenation and formatting must strictly follow the regulation.
     *
     * @param InvoiceRecord $record
     * @return string Base64-encoded SHA-256 hash
     */
    public static function generate(InvoiceRecord $record)
    {
        // Build the data string based on record type (submission or cancellation)
        $dataString = self::buildDataString($record);

        // Hash using SHA-256 and encode to base64
        return base64_encode(hash('sha256', $dataString, true));
    }

    /**
     * Builds the data string to be hashed, concatenating fields according to the AEAT rules.
     * For each type (InvoiceSubmission, InvoiceCancellation), uses the required field order.
     *
     * @param InvoiceRecord $record
     * @return string
     */
    protected static function buildDataString(InvoiceRecord $record)
    {
        // Detect type: submission or cancellation
        if ($record instanceof \eseperio\verifactu\models\InvoiceSubmission) {
            // Get invoice ID and chaining using getter methods
            $invoiceId = $record->getInvoiceId();
            $chaining = $record->getChaining();
            
            // Fields order for "RegistroAlta" (submission)
            $fields = [
                'issuerNif'         => $invoiceId->issuerNif,
                'seriesNumber'      => $invoiceId->seriesNumber,
                'issueDate'         => $invoiceId->issueDate,
                'invoiceType'       => $record->invoiceType instanceof \BackedEnum ? $record->invoiceType->value : (string)$record->invoiceType,
                'taxAmount'         => $record->taxAmount,
                'totalAmount'       => $record->totalAmount,
                'hash'              => $chaining && $chaining->getPreviousInvoice() ? $chaining->getPreviousInvoice()->hash : '',
                'recordTimestamp'   => $record->recordTimestamp,
            ];

            // Normalize values (trim, decimals, etc.)
            $fields['taxAmount'] = self::normalizeDecimal($fields['taxAmount']);
            $fields['totalAmount'] = self::normalizeDecimal($fields['totalAmount']);

            // Prepare concatenation
            $parts = [
                'IDEmisorFactura=' . trim($fields['issuerNif']),
                'NumSerieFactura=' . trim($fields['seriesNumber']),
                'FechaExpedicionFactura=' . trim($fields['issueDate']),
                'TipoFactura=' . trim($fields['invoiceType']),
                'CuotaTotal=' . $fields['taxAmount'],
                'ImporteTotal=' . $fields['totalAmount'],
                'Huella=' . trim($fields['hash']),
                'FechaHoraHusoGenRegistro=' . trim($fields['recordTimestamp']),
            ];
            return implode('&', $parts);

        } elseif ($record instanceof \eseperio\verifactu\models\InvoiceCancellation) {
            // Get invoice ID and chaining using getter methods
            $invoiceId = $record->getInvoiceId();
            $chaining = $record->getChaining();
            
            // Fields order for "RegistroAnulacion" (cancellation)
            $fields = [
                'issuerNif'         => $invoiceId->issuerNif,
                'seriesNumber'      => $invoiceId->seriesNumber,
                'issueDate'         => $invoiceId->issueDate,
                'hash'              => $chaining && $chaining->getPreviousInvoice() ? $chaining->getPreviousInvoice()->hash : '',
                'recordTimestamp'   => $record->recordTimestamp,
            ];

            $parts = [
                'IDEmisorFacturaAnulada=' . trim($fields['issuerNif']),
                'NumSerieFacturaAnulada=' . trim($fields['seriesNumber']),
                'FechaExpedicionFacturaAnulada=' . trim($fields['issueDate']),
                'Huella=' . trim($fields['hash']),
                'FechaHoraHusoGenRegistro=' . trim($fields['recordTimestamp']),
            ];
            return implode('&', $parts);

        } else {
            throw new \InvalidArgumentException('Unsupported record type for hash generation.');
        }
    }

    /**
     * Normalizes a decimal value as required by AEAT (removes unnecessary trailing zeros, uses dot).
     *
     * @param mixed $value
     * @return string
     */
    protected static function normalizeDecimal($value)
    {
        // Convert to float, remove trailing zeros, use dot as decimal separator
        return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
    }
}
