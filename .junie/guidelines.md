# Verifactu-PHP Development Guidelines

This document provides guidelines and information for developers working on the Verifactu-PHP project.

## Build/Configuration Instructions

### Requirements

- PHP 8.1 or higher
- Required PHP extensions:
    - soap
    - libxml
    - openssl
    - dom

### Installation

1. Clone the repository
2. Install dependencies using Composer:
   ```bash
   composer install
   ```

### Configuration

To use the Verifactu library, you need to configure it with your certificate:

```php
use eseperio\verifactu\Verifactu;

// Configure the library
Verifactu::config(
    '/path/to/certificate.p12',  // Path to your certificate file
    'certificate_password',      // Password for your certificate
    Verifactu::TYPE_CERTIFICATE, // Certificate type (certificate or seal)
    Verifactu::ENVIRONMENT_SANDBOX // Environment (sandbox or production)
);
```

## Testing Information

### Running Tests

The project uses PHPUnit for testing. To run all tests:

```bash
./vendor/bin/phpunit
```

To run a specific test file:

```bash
./vendor/bin/phpunit tests/Unit/SomeTest.php
```

To run a specific test method:

```bash
./vendor/bin/phpunit --filter testMethodName tests/Unit/SomeTest.php
```

> IMPORTANT: Test must pass, and if thew do not, and there is no clear solution, a call to markTestSkipped, but a
> detailed explanation must be registered in code as a comment or the message of markTestSkipped.

### Adding New Tests

1. Create a new test file in the appropriate directory:
    - Unit tests go in `tests/Unit/`
    - Model tests go in `tests/Unit/Models/`

2. Name your test file with the suffix `Test.php` (e.g., `MyServiceTest.php`)

3. Extend the `PHPUnit\Framework\TestCase` class:

```php
namespace eseperio\verifactu\tests\Unit;

use PHPUnit\Framework\TestCase;

class MyServiceTest extends TestCase
{
    public function testSomeFunctionality()
    {
        // Test code here
        $this->assertTrue(true);
    }
}
```

### Testing Protected Methods

To test protected methods, use PHP's Reflection API:

```php
$reflectionClass = new \ReflectionClass(MyClass::class);
$method = $reflectionClass->getMethod('protectedMethod');
$method->setAccessible(true);
$result = $method->invoke(null, $param1, $param2); // For static methods
// or
$result = $method->invoke($object, $param1, $param2); // For instance methods
```

### Mocking Abstract Classes

To test code that uses abstract classes, create mock objects:

```php
$mockObject = $this->getMockBuilder(AbstractClass::class)
    ->disableOriginalConstructor()
    ->getMockForAbstractClass();
```

## Additional Development Information

### Code Style

- The project follows PSR-4 for autoloading
- Class names use PascalCase
- Method names use camelCase
- Properties use camelCase
- Constants use UPPER_SNAKE_CASE

### Model Validation

Models extend the `Model` class which provides validation functionality:

1. Define validation rules in the `rules()` method:

```php
public function rules()
{
    return [
        [['property1', 'property2'], 'required'],
        [['property3'], 'string'],
        [['property4'], function($value) {
            return is_numeric($value) ? true : 'Must be numeric.';
        }],
    ];
}
```

2. Call `validate()` to validate the model:

```php
$model = new MyModel();
$result = $model->validate();
if ($result !== true) {
    // Handle validation errors in $result array
}
```

### Error Handling

The library uses exceptions for error handling:

- `\InvalidArgumentException` for invalid input
- `\RuntimeException` for runtime errors
- `\SoapFault` for SOAP API errors

### QR Code Generation

The library uses the bacon/bacon-qr-code library to generate QR codes for invoices. The QR codes follow the AEAT
Verifactu specification.

### SOAP API Integration

The library communicates with the AEAT Verifactu SOAP API. The API has different endpoints for production and sandbox
environments, and for certificate and seal authentication types.
