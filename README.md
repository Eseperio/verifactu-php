# Verifactu PHP Library

> ⚠️ 2025: Library under development. You can try it, but expect changes and incomplete features.

Tasks pending before the first beta release:
- [ ] 

**A modern PHP library for integrating with Spain’s AEAT Verifactu system (digital invoice submission, cancellation,
querying, and events) according to the official regulatory technical specification.**

---

## Table of Contents

* [Introduction](#introduction)
* [Features](#features)
* [Installation](#installation)
* [Basic Usage](#basic-usage)
* [AEAT Workflow Overview](#aeat-workflow-overview)
* [Configuration](#configuration)
* [Main Models](#main-models)
* [Service Reference](#service-reference)
* [Error Handling](#error-handling)
* [Contributing](#contributing)
* [License](#license)
* [Acknowledgements](#acknowledgements)

---

## Introduction

This library provides an object-oriented, strongly-typed and extensible interface to Spain’s AEAT Verifactu digital
invoicing system, including:

* Invoice registration (Alta)
* Invoice cancellation (Anulación)
* Querying previously submitted invoices
* Event notification (system-level events required by law)
* QR code generation (for inclusion on invoices)
* Built-in XML signature (XAdES enveloped) and hash calculation
* Error translation using the official AEAT code dictionary

It is designed for easy Composer-based installation and seamless integration into any modern PHP 8.0+ application.

---

## Features

* PSR-4 namespaced (`eseperio\verifactu`)
* Models mapped to AEAT XSDs for strong validation and serialization
* Fully modular, easily testable architecture
* Certificate management for SOAP and XML signature
* Compatible with both production and testing AEAT endpoints
* Lightweight QR code generation (no unnecessary dependencies)
* Developer-friendly validation and error reporting

---

## Installation

Install via Composer:

```bash
composer require eseperio/verifactu
```

```bash
composer require bacon/bacon-qr-code
composer require robrichards/xmlseclibs
```

---

## Basic Usage

### 1. **Register an Invoice (Alta)**

```php
use eseperio\verifactu\Verifactu;
use eseperio\verifactu\models\InvoiceSubmission;
use eseperio\verifactu\models\InvoiceId;

$config = [
    'wsdl' => 'https://aeat.example/wsdl/VerifactuService.wsdl',
    'certPath' => '/path/to/your-certificate.pfx',
    'certPassword' => 'your-cert-password',
    'baseVerificationUrl' => 'https://aeat.es/consulta-verifactu',
];

$invoice = new InvoiceSubmission();
$invoice->versionId = '1.0';
$invoice->invoiceId = new InvoiceId();
$invoice->invoiceId->issuerNif = 'B12345678';
$invoice->invoiceId->seriesNumber = 'FA2024/001';
$invoice->invoiceId->issueDate = '2024-07-01';
$invoice->issuerName = 'Empresa Ejemplo SL';
// ...asigna el resto de campos obligatorios...

$response = Verifactu::registerInvoice($invoice, $config);

if ($response->submissionStatus === 'Correcto') {
    echo "AEAT CSV: " . $response->csv;
} else {
    // See error codes and messages in $response->lineResponses
}
```

---

### 2. **Cancel an Invoice (Anulación)**

```php
use eseperio\verifactu\Verifactu;
use eseperio\verifactu\models\InvoiceCancellation;
use eseperio\verifactu\models\InvoiceId;

$config = [ /* ...same as above... */ ];

$cancellation = new InvoiceCancellation();
$cancellation->versionId = '1.0';
$cancellation->invoiceId = new InvoiceId();
$cancellation->invoiceId->issuerNif = 'B12345678';
$cancellation->invoiceId->seriesNumber = 'FA2024/001';
$cancellation->invoiceId->issueDate = '2024-07-01';
// ...asigna los campos obligatorios...

$response = Verifactu::cancelInvoice($cancellation, $config);

// Check response as above
```

---

### 3. **Query Submitted Invoices**

```php
use eseperio\verifactu\Verifactu;
use eseperio\verifactu\models\InvoiceQuery;

$query = new InvoiceQuery();
$query->year = '2024';
$query->period = '07';
// ...añade filtros opcionales...

$result = Verifactu::queryInvoices($query, $config);

foreach ($result->foundRecords as $record) {
    // Handle each record
}
```

---

### 4. **Generate QR for an Invoice**

```php
use eseperio\verifactu\Verifactu;
// $invoice must be a valid InvoiceRecord with populated data
$qrBase64 = Verifactu::generateInvoiceQr($invoice, $config['baseVerificationUrl']);
// Save or embed the PNG in your PDF, HTML, etc.
```

---

## AEAT Workflow Overview

The typical workflow to comply with AEAT Verifactu regulation is:

1. **Prepare Invoice Data:** Build an `InvoiceSubmission` model (or `InvoiceCancellation`, `EventRecord`, etc.) with all
   required fields.
2. **Generate Hash (Huella):** The library calculates the SHA-256 hash of the invoice using official field ordering.
3. **Serialize to XML:** The library creates an XML structure strictly matching the AEAT XSD.
4. **Digitally Sign XML:** The library signs the XML block using XAdES enveloped and your digital certificate.
5. **Transmit to AEAT:** The signed XML is sent to AEAT via SOAP using the appropriate endpoint and certificate
   authentication.
6. **Parse Response:** The response is parsed into a typed model (`InvoiceResponse`, `QueryResponse`, etc.), translating
   error codes using the official dictionary.
7. **Handle Results:** Use the CSV, status, and error details to update your ERP, notify users, or perform follow-up
   actions.

The same flow applies to cancellations and events, changing only the data model and XML structure.

---

## Configuration

Your `$config` array must include:

* **wsdl:** The WSDL endpoint for the operation (provided by AEAT; separate for production and testing).
* **certPath:** Path to your digital certificate (PFX or PEM format).
* **certPassword:** Password for the certificate file.
* **baseVerificationUrl:** AEAT’s verification/cotejo URL for QR codes (required for QR generation).

You may add or extend configuration options as your integration grows.

---

## Main Models

* **InvoiceSubmission:** For registering new invoices (Alta)
* **InvoiceCancellation:** For cancelling existing invoices (Anulación)
* **InvoiceId:** Invoice identification block used within both Alta and Anulación
* **InvoiceQuery:** For querying submitted invoices
* **InvoiceResponse:** The result of a registration or cancellation
* **QueryResponse:** The result of a query/filter
* **EventRecord:** For system events as required by AEAT

See the `/src/models` directory for PHPDoc details and validation rules for each.

---

## Service Reference

* **VerifactuService:** Orchestrates the main workflow (validate → hash → serialize → sign → SOAP → parse).
* **HashGeneratorService:** Implements AEAT-compliant SHA-256 hash calculation.
* **XmlSignerService:** Digitally signs XML blocks using XAdES Enveloped and your certificate.
* **SoapClientFactoryService:** Configures and creates secure SOAP clients with certificates.
* **QrGeneratorService:** Generates AEAT-compliant QR codes (PNG base64) for invoices.
* **ResponseParserService:** Converts AEAT XML/SOAP responses to models and decodes errors.
* **EventDispatcherService:** Handles event submission to AEAT endpoints.
* **CertificateManagerService:** Manages certificate and private key loading and validation.

Each service can be used directly or via the main `Verifactu` façade.

---

## Error Handling

* All errors returned by AEAT (XML/SOAP) are parsed and mapped using the official code dictionary in
  `/src/dictionaries/ErrorRegistry.php`.
* Validation errors in models trigger PHP exceptions or return detailed error arrays.
* Connection, signing, and parsing errors also trigger exceptions, which you should handle in your business logic.

---

## Contributing

* Open issues or pull requests with improvements, bugfixes, or feature requests.
* Please follow PSR coding standards and document all public classes/methods with PHPDoc.
* Contributions for additional AEAT document types or regulatory changes are welcome!

---

## License

MIT License.
See [LICENSE](LICENSE) file.

---

## Acknowledgements

* Based on public technical documentation and XSDs by AEAT.
* QR generation via [bacon/bacon-qr-code](https://github.com/Bacon/BaconQrCode)
* XML signature via [robrichards/xmlseclibs](https://github.com/robrichards/xmlseclibs)

---

*For more information on the Verifactu regulation, see the [AEAT website](https://www.agenciatributaria.es/)*

## Developing and testing

See the [CONTRIBUTING.md](CONTRIBUTING.md) file for guidelines on how to contribute to this project, including setting
up your development environment, running tests, and submitting pull requests.

