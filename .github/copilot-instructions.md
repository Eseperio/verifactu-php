# Verifactu-PHP Library

A modern PHP library for integrating with Spain's AEAT Verifactu system (digital invoice submission, cancellation, querying, and events) according to the official regulatory technical specification.

Always reference these instructions first and fallback to search or bash commands only when you encounter unexpected information that does not match the info here.

## Working Effectively

### Bootstrap and Setup
- Install PHP 8.1+ with required extensions:
  - `php -m | grep -E "(soap|libxml|openssl|dom|gd|curl|imagick)"` -- verify these extensions are available
- Install dependencies:
  - `composer install --no-interaction` -- takes 2-3 minutes. NEVER CANCEL. Set timeout to 5+ minutes.
  - If GitHub authentication errors occur, use `--no-interaction` flag to skip prompts
- Run tests to validate setup:
  - `vendor/bin/phpunit` -- takes less than 1 second. Tests: 19, Assertions: 79
  - `composer test` -- alternative command, same result

### Build and Test
- **NEVER CANCEL any build or test commands** - All operations complete quickly (under 5 minutes)
- Dependencies install: 2-3 minutes (includes download from GitHub with fallback to git clone)
- Test execution: Under 1 second for full test suite
- No additional build step required - this is a library, not an application

### Key Project Structure
- `src/` - Library source code (37 PHP files)
  - `src/Verifactu.php` - Main entry point and facade class
  - `src/models/` - Data models for invoices, cancellations, queries (InvoiceSubmission, InvoiceCancellation, etc.)
  - `src/models/enums/` - Type-safe enumerations (InvoiceType, YesNoType, HashType, etc.)
  - `src/services/` - Business logic services (QrGeneratorService, HashGeneratorService, VerifactuService, etc.)
  - `src/dictionaries/` - Error code mappings and AEAT dictionaries
- `tests/Unit/` - PHPUnit tests (5 test files)
- `docs/aeat/` - AEAT documentation and XSD schemas
- Root files: `composer.json`, `phpunit.xml`, `README.md`, `CONTRIBUTING.md`

## Validation

### Manual Testing Scenarios
After making changes, ALWAYS run these validation steps:

1. **Dependency validation**: `composer install --no-interaction` (2-3 minutes)
2. **Test suite**: `vendor/bin/phpunit` (under 1 second)
3. **Model creation and validation**: Create InvoiceSubmission with all required fields:
   - InvoiceId (issuerNif, seriesNumber, issueDate)
   - Basic invoice data (issuerName, invoiceType, operationDescription, taxAmount, totalAmount)
   - BreakdownDetail with tax information
   - Chaining information (setAsFirstRecord() for first invoice)
   - ComputerSystem with provider information
   - Required fields (recordTimestamp, hashType, hash, externalRef, xmlSignature, operationDate)
4. **QR generation**: Validate bacon/bacon-qr-code integration works (Imagick and SVG backends available)
5. **Enum usage**: Test enum values like `InvoiceType::STANDARD->value` returns 'F1'

### Validation Notes
- XML serialization may have issues in current version - avoid testing `toXml()` method
- Model validation will show missing required fields - this is expected for incomplete models
- QR generation requires CSV field to be set (obtained after successful AEAT submission)
- SOAP submission requires valid AEAT certificates and cannot be tested without them

## Common Tasks

### Working with Models
- Use object-oriented approach: `$invoice->setInvoiceId($invoiceId)` instead of direct property assignment
- Always validate models: `$validationResult = $model->validate()`
- Use enums for type safety: `InvoiceType::STANDARD`, `YesNoType::YES`, `HashType::SHA_256`
- Add collection items with helper methods: `$invoice->addBreakdownDetail($detail)`

### Key Enum Values
- InvoiceType: STANDARD='F1', SIMPLIFIED='F2', RECTIFICATION_1='R1'
- YesNoType: YES='S', NO='N'  
- HashType: SHA_256='01'
- OperationQualificationType: SUBJECT_NO_EXEMPT_NO_REVERSE and others

### Running Specific Tests
```bash
vendor/bin/phpunit tests/Unit/Models/ModelTest.php
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --coverage-html coverage  # Generate coverage report
```

### Available Composer Scripts
```bash
composer test         # Run PHPUnit tests
composer run-script --list  # Show available scripts
```

## Dependencies and Requirements

### Core Dependencies
- `bacon/bacon-qr-code: ^3` - QR code generation (Imagick, GD, SVG backends supported)
- `robrichards/xmlseclibs: ^3.0` - XML digital signature
- PHP 8.1+ with extensions: soap, libxml, openssl, dom, gd, curl, imagick

### Development Dependencies  
- `phpunit/phpunit: ^10.0` - Testing framework only

### PHP Configuration
- No special memory limits required (tested with unlimited memory)
- All operations complete quickly (no timeout concerns)
- Standard PHP configuration sufficient

## Important Implementation Notes

### Certificate Requirements
- Production: `Verifactu::ENVIRONMENT_PRODUCTION` with valid AEAT certificate
- Testing: `Verifactu::ENVIRONMENT_SANDBOX` with homologation certificate
- Certificate types: `TYPE_CERTIFICATE` (personal/company) or `TYPE_SEAL`

### Configuration Pattern
```php
Verifactu::config(
    '/path/to/certificate.pfx',
    'certificate-password', 
    Verifactu::TYPE_CERTIFICATE,
    Verifactu::ENVIRONMENT_SANDBOX
);
```

### Common Validation Errors
When model validation fails, common missing fields include:
- chaining (use `$chaining->setAsFirstRecord()` for first invoice)
- systemInfo (requires ComputerSystem with provider information)
- externalRef, xmlSignature, operationDate (string fields)
- invoiceAgreementNumber, systemAgreementId (optional string fields)

## Troubleshooting

### GitHub Authentication Issues
- Use `composer install --no-interaction` to avoid OAuth prompts
- Composer will fallback to git clone automatically if needed

### QR Generation Issues  
- Imagick preferred but SVG fallback available
- Test with: `BaconQrCode\Writer` and `ImageRenderer`
- QR requires CSV field from successful AEAT submission

### Model Validation
- Missing required fields will show specific validation errors
- Use complete model setup as shown in validation scenarios
- XML serialization currently has technical issues - avoid in testing

This library is UNDER DEVELOPMENT - expect changes until first alpha release. Check CHANGELOG.md for recent changes.