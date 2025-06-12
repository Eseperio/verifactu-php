# Verifactu PHP Library: Technical Structure & Documentation

## Directory Structure (Composer/PSR-4) 

```
src/
│
├── Verifactu.php
│
├── services/
│   ├── VerifactuService.php
│   ├── SoapClientFactoryService.php
│   ├── CertificateManagerService.php
│   ├── HashGeneratorService.php
│   ├── XmlSignerService.php
│   ├── QrGeneratorService.php
│   ├── ResponseParserService.php
│   ├── EventDispatcherService.php
│
├── models/
│   ├── InvoiceSubmission.php
│   ├── InvoiceCancellation.php
│   ├── InvoiceQuery.php
│   ├── InvoiceRecord.php
│   ├── InvoiceResponse.php
│   ├── QueryResponse.php
│   ├── EventRecord.php
│   └── (...other model classes)
│
├── dictionaries/
│   └── ErrorRegistry.php
│
└── (...other standard folders: tests/, docs/, etc.)
```

---

## 1. Responsibilities and Class Overview

### `src/Verifactu.php`

* **Responsibility:** Main entry point (façade).
* **Static methods** for all primary Verifactu operations, e.g.:

    * `Verifactu::registerInvoice(InvoiceSubmission $invoice): InvoiceResponse`
    * `Verifactu::cancelInvoice(InvoiceCancellation $cancellation): InvoiceResponse`
    * `Verifactu::queryInvoices(InvoiceQuery $query): QueryResponse`
    * `Verifactu::generateInvoiceQr(InvoiceRecord $record): string`

---

### `src/services/` (all classes suffixed with `Service`)

* **VerifactuService.php:** Internal orchestrator, composes and coordinates subservices.
* **SoapClientFactoryService.php:** Creates/configures SOAP clients for all environments.
* **CertificateManagerService.php:** Manages secure X.509 certificates (load, validate, assign for signing).
* **HashGeneratorService.php:** Generates SHA-256 hashes as specified in the AEAT docs.
* **XmlSignerService.php:** Digitally signs XML with XAdES enveloped.
* **QrGeneratorService.php:** Generates QR codes compliant with AEAT Verifactu specs.
* **ResponseParserService.php:** Parses XML/SOAP responses into typed PHP model objects.
* **EventDispatcherService.php:** Handles system event submission to AEAT.

---

### `src/models/`

Each model corresponds directly to a relevant AEAT XSD structure. Fields and serialization should match exactly for
interoperability.

* **InvoiceSubmission.php**

    * *Responsibility:* Represents invoice registration data ("Alta").
    * *XSD basis:* `RegistroAlta` (SuministroInformacion.xsd.xml)

* **InvoiceCancellation.php**

    * *Responsibility:* Represents invoice cancellation data ("Anulación").
    * *XSD basis:* `RegistroAnulacion` (SuministroInformacion.xsd.xml)

* **InvoiceQuery.php**

    * *Responsibility:* Represents a query for submitted records (period, NIF, etc.).
    * *XSD basis:* `ConsultaFactuSistemaFacturacionType` (ConsultaLR.xsd.xml)

* **InvoiceRecord.php**

    * *Responsibility:* Abstract base for individual invoice record (Alta or Cancellation).

* **InvoiceResponse.php**

    * *Responsibility:* Represents the parsed AEAT response for registrations/cancellations.
    * *XSD basis:* `RespuestaRegFactuSistemaFacturacionType` (RespuestaSuministro.xsd.xml)

* **QueryResponse.php**

    * *Responsibility:* Represents the parsed AEAT response for queries.
    * *XSD basis:* `RespuestaConsultaFactuSistemaFacturacionType` (RespuestaConsultaLR.xsd.xml)

* **EventRecord.php**

    * *Responsibility:* Represents a system event for submission.
    * *XSD basis:* `RegistroEvento` (EventosSIF.xsd.xml)

---

### `src/dictionaries/ErrorRegistry.php`

* **Responsibility:**

    * Provides a dictionary of AEAT error codes and messages.
    * Loads the dictionary from static array or external file (properties, database, etc).

---

## 2. Example: Public Static Interface (Façade)

```php
// src/Verifactu.php
class Verifactu
{
    public static function registerInvoice(models\InvoiceSubmission $invoice): models\InvoiceResponse
    // Registers an invoice (Alta) with AEAT

    public static function cancelInvoice(models\InvoiceCancellation $cancellation): models\InvoiceResponse
    // Cancels an invoice

    public static function queryInvoices(models\InvoiceQuery $query): models\QueryResponse
    // Queries submitted invoices

    public static function generateInvoiceQr(models\InvoiceRecord $record): string
    // Generates a base64 QR code for the given invoice

    // ...other relevant methods
}
```

---

## 3. Model Overview Table

| Class (src/models/) | XSD Basis                                                                  | Primary Use                        |
|---------------------|----------------------------------------------------------------------------|------------------------------------|
| InvoiceSubmission   | RegistroAlta (SuministroInformacion.xsd.xml)                               | Invoice registration (Alta)        |
| InvoiceCancellation | RegistroAnulacion (SuministroInformacion.xsd.xml)                          | Invoice cancellation (Anulación)   |
| InvoiceQuery        | ConsultaFactuSistemaFacturacionType (ConsultaLR.xsd.xml)                   | Invoice record queries             |
| InvoiceRecord       | (Abstract/base)                                                            | Individual invoice representation  |
| InvoiceResponse     | RespuestaRegFactuSistemaFacturacionType (RespuestaSuministro.xsd.xml)      | Registration/cancellation response |
| QueryResponse       | RespuestaConsultaFactuSistemaFacturacionType (RespuestaConsultaLR.xsd.xml) | Query response                     |
| EventRecord         | RegistroEvento (EventosSIF.xsd.xml)                                        | System event submission            |

---

## 4. Additional Guidelines

* **All service/utility classes go in `/services` and are suffixed with `Service`.**
* **Dictionaries and reference tables go in `/dictionaries`.**
* **All data models go in `/models`, one class per relevant XSD.**
* **Only `Verifactu.php` should be present in the `/src` root.**
* **Consumers only use the static `Verifactu` class; everything else is internal.**
* **Follow PSR and Composer standards for autoloading, naming, etc.**

---

## 5. Next Steps

* Review this structure and naming for alignment with your team's conventions.
* For each model, define all fields and types, mapping to their XSD definitions.
* Document each public method, its parameters, and expected return types.
* Ensure all code, tests, and docs adhere to this documented structure for maintainability and clarity.
