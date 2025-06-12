<?php
// Main entry point of the Verifactu library
namespace eseperio\verifactu;

use eseperio\verifactu\models\InvoiceSubmission;
use eseperio\verifactu\models\InvoiceCancellation;
use eseperio\verifactu\models\InvoiceQuery;
use eseperio\verifactu\models\InvoiceRecord;
use eseperio\verifactu\models\InvoiceResponse;
use eseperio\verifactu\models\QueryResponse;

use eseperio\verifactu\services\VerifactuService;

class Verifactu
{
    /**
     * Registers a new invoice (Alta) with AEAT via VERI*FACTU.
     *
     * @param InvoiceSubmission $invoice
     * @return InvoiceResponse
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
