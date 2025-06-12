<?php
namespace eseperio\verifactu\services;

use eseperio\verifactu\models\InvoiceSubmission;
use eseperio\verifactu\models\InvoiceCancellation;
use eseperio\verifactu\models\InvoiceQuery;
use eseperio\verifactu\models\InvoiceRecord;
use eseperio\verifactu\models\InvoiceResponse;
use eseperio\verifactu\models\QueryResponse;

class VerifactuService
{
    /**
     * Registers a new invoice with AEAT via VERI*FACTU.
     *
     * @param InvoiceSubmission $invoice
     * @return InvoiceResponse
     */
    public static function registerInvoice(InvoiceSubmission $invoice): InvoiceResponse
    {
        // TODO: orchestrate model serialization, hash generation, XML signing, SOAP call, response parsing, etc.
        throw new \RuntimeException('Not implemented yet.');
    }

    /**
     * Cancels an invoice with AEAT via VERI*FACTU.
     *
     * @param InvoiceCancellation $cancellation
     * @return InvoiceResponse
     */
    public static function cancelInvoice(InvoiceCancellation $cancellation): InvoiceResponse
    {
        // TODO: orchestrate cancellation flow
        throw new \RuntimeException('Not implemented yet.');
    }

    /**
     * Queries submitted invoices from AEAT via VERI*FACTU.
     *
     * @param InvoiceQuery $query
     * @return QueryResponse
     */
    public static function queryInvoices(InvoiceQuery $query): QueryResponse
    {
        // TODO: orchestrate query flow
        throw new \RuntimeException('Not implemented yet.');
    }

    /**
     * Generates a base64 QR code for the provided invoice.
     *
     * @param InvoiceRecord $record
     * @return string base64-encoded PNG QR code
     */
    public static function generateInvoiceQr(InvoiceRecord $record): string
    {
        // TODO: delegate to QrGeneratorService
        throw new \RuntimeException('Not implemented yet.');
    }

    // Additional coordination methods for new features can be added here.
}
