# Verifactu PHP Library

> [!WARNING]
> 2025: Library __UNDER DEVELOPMENT__. You can try it, but expect changes, incomplete features or to be broken until
> first alpha version is released. See the [CHANGELOG](CHANGELOG.md) for details of changes done during development.

**A modern PHP library for integrating with 🇪🇸Spain’s AEAT Verifactu system (digital invoice submission, cancellation,
querying, and events) according to the official regulatory technical specification.**

> [!NOTE]
> This library supports verfactu transactions only. For non verifactu signed transactions, such as those required when
> not using invoicing software, you may look for a different library.

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
* [Developing and testing](#developing-and-testing)
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

### 1. **Configuration**

Before using any service, you must configure the library with your certificate, password, certificate type, and
environment.  
Choose between certificate type (`certificate` or `seal`) and environment (`production` or `sandbox`) according to
whether you'll be working in production or testing.

```php
use eseperio\verifactu\Verifactu;

// Configure the library (do this once before any operation)
Verifactu::config(
    '/path/to/your-certificate.pfx', // Path to certificate
    'your-certificate-password',     // Certificate password
    Verifactu::TYPE_CERTIFICATE,     // Certificate type: TYPE_CERTIFICATE or TYPE_SEAL
    Verifactu::ENVIRONMENT_PRODUCTION // Environment: ENVIRONMENT_PRODUCTION or ENVIRONMENT_SANDBOX
);
```

- Use `Verifactu::TYPE_CERTIFICATE` for a personal/company certificate, or `Verifactu::TYPE_SEAL` for a seal
  certificate.
- Use `Verifactu::ENVIRONMENT_PRODUCTION` for real submissions, or `Verifactu::ENVIRONMENT_SANDBOX` for AEAT
  homologation/testing.

---

### 2. **Register an Invoice (Alta)**

```php
use eseperio\verifactu\Verifactu;
use eseperio\verifactu\models\InvoiceSubmission;
use eseperio\verifactu\models\InvoiceId;
use eseperio\verifactu\models\Breakdown;
use eseperio\verifactu\models\BreakdownDetail;
use eseperio\verifactu\models\Chaining;
use eseperio\verifactu\models\ComputerSystem;
use eseperio\verifactu\models\LegalPerson;
use eseperio\verifactu\models\Recipient;
use eseperio\verifactu\models\enums\InvoiceType;
use eseperio\verifactu\models\enums\TaxType;
use eseperio\verifactu\models\enums\YesNoType;
use eseperio\verifactu\models\enums\HashType;
use eseperio\verifactu\models\enums\OperationQualificationType;

// After calling Verifactu::config(...)

$invoice = new InvoiceSubmission();

// Set invoice ID (using object-oriented approach)
$invoiceId = new InvoiceId();
$invoiceId->issuerNif = 'B12345678';
$invoiceId->seriesNumber = 'FA2024/001';
$invoiceId->issueDate = '2024-07-01';
$invoice->setInvoiceId($invoiceId);

// Set basic invoice data
$invoice->issuerName = 'Empresa Ejemplo SL';
$invoice->invoiceType = InvoiceType::STANDARD; // Using corrected enum value
$invoice->operationDescription = 'Venta de productos';
$invoice->taxAmount = 21.00; // Total tax amount
$invoice->totalAmount = 121.00; // Total invoice amount
$invoice->simplifiedInvoice = YesNoType::NO; // Corrected property name
$invoice->invoiceWithoutRecipient = YesNoType::NO; // Corrected property name

// Add tax breakdown (using object-oriented approach)
$breakdown = new Breakdown();
$detail = new BreakdownDetail();
$detail->taxType = TaxType::IVA;
$detail->taxRate = 21.00;
$detail->taxableBase = 100.00; // Corrected property name
$detail->taxAmount = 21.00;
$detail->operationQualification = OperationQualificationType::SUBJECT_NO_EXEMPT_NO_REVERSE;
$breakdown->addDetail($detail);
$invoice->setBreakdown($breakdown);

// Set chaining data (using object-oriented approach)
$chaining = new Chaining();
$chaining->firstRecord = YesNoType::YES; // Corrected property name - For the first invoice in a chain
// Or for subsequent invoices:
// $chaining->setPreviousInvoice([
//     'seriesNumber' => 'FA2024/000',
//     'issuerNif' => 'B12345678',
//     'issueDate' => '2024-06-30',
//     'hash' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'
// ]);
$invoice->setChaining($chaining);

// Set system information (using object-oriented approach)
$computerSystem = new ComputerSystem();
$computerSystem->systemName = 'ERP Company';
$computerSystem->version = '1.0';
$computerSystem->providerName = 'Software Provider';
$computerSystem->systemId = '01';
$computerSystem->installationNumber = '1';
$computerSystem->onlyVerifactu = YesNoType::YES;
$computerSystem->multipleObligations = YesNoType::NO;

// Set provider information
$provider = new LegalPerson();
$provider->name = 'Software Provider SL';
$provider->nif = 'B87654321';
$computerSystem->setProviderId($provider);

$invoice->setSystemInfo($computerSystem);

// Set other required fields
$invoice->recordTimestamp = '2024-07-01T12:00:00+02:00'; // Date and time with timezone
$invoice->hashType = HashType::SHA_256;
$invoice->hash = '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'; // Calculated hash

// Optional fields
$invoice->operationDate = '2024-07-01'; // Operation date
$invoice->externalRef = 'REF123'; // External reference
$invoice->simplifiedInvoice = YesNoType::NO; // Not a simplified invoice
$invoice->invoiceWithoutRecipient = YesNoType::NO; // Has identified recipient

// Add recipients (using object-oriented approach)
$recipient = new Recipient();
$recipientPerson = new LegalPerson();
$recipientPerson->name = 'Cliente Ejemplo SL';
$recipientPerson->nif = 'A98765432';
$recipient->setLegalPerson($recipientPerson);
$invoice->setRecipient($recipient);

// Validate the invoice before submission
$validationResult = $invoice->validate();
if ($validationResult !== true) {
    // Handle validation errors
    print_r($validationResult);
    exit;
}

// Submit the invoice
$response = Verifactu::registerInvoice($invoice);

if ($response->submissionStatus === \eseperio\verifactu\models\InvoiceResponse::STATUS_OK) {
    echo "AEAT CSV: " . $response->csv;
} else {
    // Check error codes and messages in $response->lineResponses
    foreach ($response->lineResponses as $lineResponse) {
        echo "Error code: " . $lineResponse->errorCode . " - " . $lineResponse->errorMessage . "\n";
    }
}
```

---

### 3. **Cancel an Invoice (Anulación)**

```php
use eseperio\verifactu\Verifactu;
use eseperio\verifactu\models\InvoiceCancellation;
use eseperio\verifactu\models\InvoiceId;
use eseperio\verifactu\models\Chaining;
use eseperio\verifactu\models\ComputerSystem;
use eseperio\verifactu\models\LegalPerson;
use eseperio\verifactu\models\enums\YesNoType;
use eseperio\verifactu\models\enums\HashType;
use eseperio\verifactu\models\enums\GeneratorType;

// After calling Verifactu::config(...)

$cancellation = new InvoiceCancellation();

// Set invoice ID (using object-oriented approach)
$invoiceId = new InvoiceId();
$invoiceId->issuerNif = 'B12345678';
$invoiceId->seriesNumber = 'FA2024/001';
$invoiceId->issueDate = '2024-07-01';
$cancellation->setInvoiceId($invoiceId);

// Set chaining data (using object-oriented approach)
$chaining = new Chaining();
// For subsequent invoices in a chain:
$chaining->setPreviousInvoice([
    'seriesNumber' => 'FA2024/000',
    'issuerNif' => 'B12345678',
    'issueDate' => '2024-06-30',
    'hash' => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'
]);
$cancellation->setChaining($chaining);

// Set system information (using object-oriented approach)
$computerSystem = new ComputerSystem();
$computerSystem->systemName = 'ERP Company';
$computerSystem->version = '1.0';
$computerSystem->providerName = 'Software Provider';
$computerSystem->systemId = '01';
$computerSystem->installationNumber = '1';
$computerSystem->onlyVerifactu = YesNoType::YES;
$computerSystem->multipleObligations = YesNoType::NO;

// Set provider information
$provider = new LegalPerson();
$provider->name = 'Software Provider SL';
$provider->nif = 'B87654321';
$computerSystem->setProviderId($provider);

$cancellation->setSystemInfo($computerSystem);

// Set other required fields
$cancellation->recordTimestamp = '2024-07-01T12:00:00+02:00'; // Date and time with timezone
$cancellation->hashType = HashType::SHA_256;
$cancellation->hash = '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef'; // Calculated hash

// Optional fields
$cancellation->noPreviousRecord = YesNoType::NO; // Not a cancellation without previous record
$cancellation->previousRejection = YesNoType::NO; // Not a cancellation due to previous rejection
$cancellation->generator = GeneratorType::ISSUER; // Generated by the issuer
$cancellation->externalRef = 'REF-CANCEL-123'; // External reference

// Validate the cancellation before submission
$validationResult = $cancellation->validate();
if ($validationResult !== true) {
    // Handle validation errors
    print_r($validationResult);
    exit;
}

// Submit the cancellation
$response = Verifactu::cancelInvoice($cancellation);

if ($response->submissionStatus === \eseperio\verifactu\models\InvoiceResponse::STATUS_OK) {
    echo "AEAT CSV: " . $response->csv;
} else {
    // Check error codes and messages in $response->lineResponses
    foreach ($response->lineResponses as $lineResponse) {
        echo "Error code: " . $lineResponse->errorCode . " - " . $lineResponse->errorMessage . "\n";
    }
}
```

---

### 4. **Query Submitted Invoices**

```php
use eseperio\verifactu\Verifactu;
use eseperio\verifactu\models\InvoiceQuery;
use eseperio\verifactu\models\ComputerSystem;
use eseperio\verifactu\models\LegalPerson;
use eseperio\verifactu\models\enums\YesNoType;
use eseperio\verifactu\models\enums\PeriodType;

// After calling Verifactu::config(...)

$query = new InvoiceQuery();

// Required fields
$query->year = '2024';
$query->period = PeriodType::JULY; // Using enum instead of string

// Optional filters
$query->seriesNumber = 'FA2024'; // Filter by invoice series
$query->issueDate = '2024-07-01'; // Filter by specific date

// Set counterparty (using object-oriented approach)
$counterparty = new LegalPerson();
$counterparty->name = 'Cliente Ejemplo SL';
$counterparty->nif = 'A12345678';
$query->setCounterparty($counterparty);

// Set system information (using object-oriented approach)
$computerSystem = new ComputerSystem();
$computerSystem->systemName = 'ERP Company';
$computerSystem->version = '1.0';
$computerSystem->providerName = 'Software Provider';
$computerSystem->systemId = '01';
$computerSystem->installationNumber = '1';
$computerSystem->onlyVerifactu = YesNoType::YES;
$computerSystem->multipleObligations = YesNoType::NO;

// Set provider information
$provider = new LegalPerson();
$provider->name = 'Software Provider SL';
$provider->nif = 'B87654321';
$computerSystem->setProviderId($provider);

$query->setSystemInfo($computerSystem);

// Additional optional fields
$query->externalRef = 'REF-QUERY-123'; // External reference
$query->setPaginationKey(1, 50); // Page number and records per page

// Validate the query before submission
$validationResult = $query->validate();
if ($validationResult !== true) {
    // Handle validation errors
    print_r($validationResult);
    exit;
}

// Submit the query
$result = Verifactu::queryInvoices($query);

// Process the results
if ($result->queryStatus === \eseperio\verifactu\models\QueryResponse::STATUS_OK) {
    echo "Total records found: " . count($result->foundRecords) . "\n";

    foreach ($result->foundRecords as $record) {
        echo "Invoice: " . $record->seriesNumber . " - Date: " . $record->issueDate . "\n";
        echo "Issuer: " . $record->issuerName . " (" . $record->issuerNif . ")\n";
        echo "Amount: " . $record->totalAmount . " EUR\n";
        echo "CSV: " . $record->csv . "\n";
        echo "Status: " . $record->status . "\n";
        echo "-------------------\n";
    }

    // Check if there are more pages
    if ($result->hasMoreRecords === YesNoType::YES) {
        echo "There are more records available. Use pagination to retrieve them.\n";
    }
} else {
    // Handle query errors
    foreach ($result->errors as $error) {
        echo "Error code: " . $error->code . " - " . $error->message . "\n";
    }
}
```

---

### 5. **Generate QR for an Invoice**

The library provides flexible options for generating QR codes for invoices, supporting different renderers, resolutions,
and output formats.

```php
use eseperio\verifactu\Verifactu;
use eseperio\verifactu\services\QrGeneratorService;
use eseperio\verifactu\models\InvoiceRecord;
use eseperio\verifactu\models\InvoiceSubmission;

// Assuming you already have a valid InvoiceSubmission or InvoiceCancellation object
// that has been submitted to AEAT and has a CSV

// Basic usage (returns raw image data using GD renderer)
$qrData = Verifactu::generateInvoiceQr($invoice);

// Save QR directly to a file
$filePath = Verifactu::generateInvoiceQr(
    $invoice,
    QrGeneratorService::DESTINATION_FILE, // Save to file instead of returning data
    300, // Resolution (size in pixels)
    QrGeneratorService::RENDERER_GD // Use GD library (default)
);
echo "QR code saved to: $filePath";

// Generate SVG format
$svgData = Verifactu::generateInvoiceQr(
    $invoice,
    QrGeneratorService::DESTINATION_STRING,
    300,
    QrGeneratorService::RENDERER_SVG
);
// Use SVG data directly in HTML
echo '<div>' . $svgData . '</div>';

// Generate using Imagick (if available on your server)
$pngData = Verifactu::generateInvoiceQr(
    $invoice,
    QrGeneratorService::DESTINATION_STRING,
    300,
    QrGeneratorService::RENDERER_IMAGICK
);

// Convert to base64 for embedding in HTML or PDF
$base64Data = base64_encode($pngData);
echo '<img src="data:image/png;base64,' . $base64Data . '" alt="QR Code" />';

// Higher resolution QR code
$highResQr = Verifactu::generateInvoiceQr(
    $invoice,
    QrGeneratorService::DESTINATION_STRING,
    600 // Higher resolution
);

// Complete example with a new invoice
$invoice = new InvoiceSubmission();
// ... set all required properties as shown in the Register an Invoice example ...

// First submit the invoice to get a CSV
$response = Verifactu::registerInvoice($invoice);

if ($response->submissionStatus === \eseperio\verifactu\models\InvoiceResponse::STATUS_OK) {
    // Now we can generate a QR code for the invoice
    // The QR code will include the CSV from the response
    $invoice->csv = $response->csv;

    // Generate QR code as PNG and save to file
    $qrFilePath = Verifactu::generateInvoiceQr(
        $invoice,
        QrGeneratorService::DESTINATION_FILE,
        300,
        QrGeneratorService::RENDERER_GD
    );

    echo "Invoice registered successfully with CSV: " . $response->csv . "\n";
    echo "QR code saved to: " . $qrFilePath . "\n";

    // Generate QR code as base64 for embedding in HTML
    $qrData = Verifactu::generateInvoiceQr(
        $invoice,
        QrGeneratorService::DESTINATION_STRING,
        300,
        QrGeneratorService::RENDERER_GD
    );
    $base64QrCode = base64_encode($qrData);

    echo '<img src="data:image/png;base64,' . $base64QrCode . '" alt="Invoice QR Code" />';
}
```

#### Available Options:

- **Destination Types**:
    - `QrGeneratorService::DESTINATION_STRING`: Returns the raw image data (default)
    - `QrGeneratorService::DESTINATION_FILE`: Saves to a temporary file and returns the file path

- **Renderer Types**:
    - `QrGeneratorService::RENDERER_GD`: Uses GD library (default, widely available)
    - `QrGeneratorService::RENDERER_IMAGICK`: Uses ImageMagick (if available)
    - `QrGeneratorService::RENDERER_SVG`: Generates SVG format (vector-based)

- **Resolution**: Size in pixels (default: 300)
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

The library is configured using the `Verifactu::config()` method, which accepts the following parameters:

```php
Verifactu::config(
    string $certPath,           // Path to your digital certificate (PFX or P12 format)
    string $certPassword,       // Password for the certificate file
    string $certType,           // Type of certificate: Verifactu::TYPE_CERTIFICATE or Verifactu::TYPE_SEAL
    string $environment = Verifactu::ENVIRONMENT_PRODUCTION // Environment: Verifactu::ENVIRONMENT_PRODUCTION or Verifactu::ENVIRONMENT_SANDBOX
);
```

### Certificate Types

* `Verifactu::TYPE_CERTIFICATE`: For a personal or company certificate
* `Verifactu::TYPE_SEAL`: For a seal certificate

### Environments

* `Verifactu::ENVIRONMENT_PRODUCTION`: For real submissions to AEAT
* `Verifactu::ENVIRONMENT_SANDBOX`: For testing in AEAT's homologation environment

The library automatically selects the appropriate SOAP endpoints and QR verification URLs based on the certificate type
and environment.

### Advanced Configuration

If you need more control over the configuration, you can use the `VerifactuService::config()` method directly:

```php
use eseperio\verifactu\services\VerifactuService;

VerifactuService::config([
    VerifactuService::CERT_PATH_KEY => '/path/to/your-certificate.p12',
    VerifactuService::CERT_PASSWORD_KEY => 'your-certificate-password',
    VerifactuService::WSDL_ENDPOINT => 'https://custom-endpoint.example.com',
    VerifactuService::QR_VERIFICATION_URL => 'https://custom-qr-verification.example.com',
    // Add any other custom configuration options here
]);
```

---

## Main Models

The library provides a comprehensive set of object-oriented models that represent different aspects of the AEAT
Verifactu system. All models extend the base `Model` class, which provides validation functionality.

### Core Models

* **InvoiceRecord:** Abstract base class for invoice records (submissions and cancellations)
* **InvoiceSubmission:** For registering new invoices (Alta)
* **InvoiceCancellation:** For cancelling existing invoices (Anulación)
* **InvoiceQuery:** For querying submitted invoices
* **InvoiceResponse:** The result of a registration or cancellation
* **QueryResponse:** The result of a query/filter
* **EventRecord:** For system events as required by AEAT

### Component Models

* **InvoiceId:** Invoice identification block used within both Alta and Anulación
* **Breakdown:** Tax breakdown information for invoices
* **BreakdownDetail:** Individual tax rate breakdown item
* **Chaining:** Invoice chaining information for hash linkage
* **ComputerSystem:** Information about the computer system generating the invoice
* **LegalPerson:** Represents a legal entity (person or company) with identification
* **OtherID:** Alternative identification for non-Spanish entities
* **PreviousInvoiceChaining:** Information about the previous invoice in a chain
* **Recipient:** Invoice recipient information
* **RectificationBreakdown:** Breakdown for rectification invoices

### Enumerations

The library uses enum classes for type-safe constants:

* **HashType:** Hash algorithm types (SHA-256)
* **InvoiceType:** Invoice types (F1, F2, R1, R2, etc.)
* **OperationQualificationType:** Tax operation qualifications
* **PeriodType:** Month or quarter periods
* **RectificationType:** Types of invoice rectifications
* **YesNoType:** Yes/No values for boolean fields
* **GeneratorType:** Invoice generator types
* **ThirdPartyOrRecipientType:** Types of third parties or recipients

### Model Validation

All models provide validation through the `validate()` method:

```php
$model = new InvoiceSubmission();
// ... set properties ...
$validationResult = $model->validate();

if ($validationResult !== true) {
    // Handle validation errors
    foreach ($validationResult as $property => $errors) {
        echo "Errors in $property: " . implode(', ', $errors) . "\n";
    }
}
```

### XML Serialization

Models can be serialized to XML using the `toXml()` method, which returns a DOMDocument:

```php
$invoice = new InvoiceSubmission();
// ... set properties ...
$dom = $invoice->toXml();
$xmlString = $dom->saveXML();
```

See the `/src/models` directory for PHPDoc details and validation rules for each model.

---

## Service Reference

The library is organized into specialized services that handle different aspects of the AEAT Verifactu integration.
While most operations can be performed through the main `Verifactu` facade, you can also use these services directly for
more advanced use cases.

### Main Services

* **Verifactu:** The main facade class that provides a simplified API for common operations.
* **VerifactuService:** Orchestrates the main workflow (validate → hash → serialize → sign → SOAP → parse).

### Specialized Services

* **HashGeneratorService:** Implements AEAT-compliant SHA-256 hash calculation for invoices.
  ```php
  use eseperio\verifactu\services\HashGeneratorService;

  // Calculate hash for an invoice
  $hash = HashGeneratorService::generateHash($invoice);
  $invoice->hash = $hash;
  ```

* **XmlSignerService:** Digitally signs XML blocks using XAdES Enveloped and your certificate.
  ```php
  use eseperio\verifactu\services\XmlSignerService;

  // Sign an XML document
  $signedXml = XmlSignerService::signXml($xmlString, $certPath, $certPassword);
  ```

* **SoapClientFactoryService:** Configures and creates secure SOAP clients with certificates.
  ```php
  use eseperio\verifactu\services\SoapClientFactoryService;

  // Create a SOAP client with certificate authentication
  $client = SoapClientFactoryService::createClient($wsdlUrl, $certPath, $certPassword);
  ```

* **QrGeneratorService:** Generates AEAT-compliant QR codes for invoices in various formats.
  ```php
  use eseperio\verifactu\services\QrGeneratorService;

  // Generate a QR code for an invoice
  $qrCode = QrGeneratorService::generateQr(
      $invoice,
      QrGeneratorService::DESTINATION_STRING,
      300,
      QrGeneratorService::RENDERER_GD
  );
  ```

* **ResponseParserService:** Converts AEAT XML/SOAP responses to model objects.
  ```php
  use eseperio\verifactu\services\ResponseParserService;

  // Parse a SOAP response into an InvoiceResponse object
  $response = ResponseParserService::parseInvoiceResponse($soapResponse);
  ```

* **EventDispatcherService:** Handles event submission to AEAT endpoints.
  ```php
  use eseperio\verifactu\services\EventDispatcherService;

  // Submit an event to AEAT
  $response = EventDispatcherService::submitEvent($eventRecord);
  ```

* **CertificateManagerService:** Manages certificate and private key loading and validation.
  ```php
  use eseperio\verifactu\services\CertificateManagerService;

  // Load a certificate and check its validity
  $certInfo = CertificateManagerService::loadCertificate($certPath, $certPassword);
  ```

Each service is designed to be used independently or as part of the overall workflow orchestrated by the
`VerifactuService`. This modular design allows for flexibility and testability.

---

## Error Handling

The library provides comprehensive error handling at multiple levels:

### Model Validation Errors

When using the `validate()` method on models, validation errors are returned as an associative array:

```php
$invoice = new InvoiceSubmission();
// ... set some properties but not all required ones ...

$validationResult = $invoice->validate();
if ($validationResult !== true) {
    // $validationResult is an array of errors by property
    foreach ($validationResult as $property => $errors) {
        echo "Property '$property' has errors: " . implode(', ', $errors) . "\n";
    }
}
```

### AEAT Response Errors

Errors returned by AEAT in SOAP responses are parsed into structured objects:

```php
$response = Verifactu::registerInvoice($invoice);

if ($response->submissionStatus !== \eseperio\verifactu\models\InvoiceResponse::STATUS_OK) {
    foreach ($response->lineResponses as $lineResponse) {
        echo "Error code: " . $lineResponse->errorCode . "\n";
        echo "Error message: " . $lineResponse->errorMessage . "\n";
        echo "Error location: " . $lineResponse->errorLocation . "\n";
    }
}
```

All AEAT error codes are mapped to human-readable messages using the official code dictionary in
`/src/dictionaries/ErrorRegistry.php`.

### Exception Handling

The library throws exceptions for various error conditions:

```php
try {
    $response = Verifactu::registerInvoice($invoice);
} catch (\InvalidArgumentException $e) {
    // Handle invalid input parameters
    echo "Invalid argument: " . $e->getMessage();
} catch (\RuntimeException $e) {
    // Handle runtime errors (file access, etc.)
    echo "Runtime error: " . $e->getMessage();
} catch (\SoapFault $e) {
    // Handle SOAP communication errors
    echo "SOAP error: " . $e->getMessage();
} catch (\Exception $e) {
    // Handle any other unexpected errors
    echo "Unexpected error: " . $e->getMessage();
}
```

### Common Error Types

* **Validation Errors**: Returned by the `validate()` method when model properties don't meet requirements
* **AEAT Business Errors**: Returned in the response when AEAT rejects the submission for business reasons
* **Certificate Errors**: Thrown when there are issues with the digital certificate
* **SOAP Communication Errors**: Thrown when there are network or protocol issues
* **XML Parsing Errors**: Thrown when there are issues parsing XML responses

Proper error handling is essential for a robust integration with AEAT Verifactu.

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

### Testing

This library includes comprehensive tests to ensure code quality and that documentation examples work correctly:

```bash
# Run all tests
composer test

# Test only README examples
composer test-readme

# Run unit tests
composer test-unit
```

### Development

See the [CONTRIBUTING.md](CONTRIBUTING.md) file for guidelines on how to contribute to this project, including setting
up your development environment, running tests, and submitting pull requests.
