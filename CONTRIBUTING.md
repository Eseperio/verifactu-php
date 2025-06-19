# Contributing to Verifactu-PHP

Thank you for your interest in contributing to the Verifactu-PHP library! This guide will help you understand the contribution process and ensure your contribution is integrated efficiently.

## Prerequisites

To contribute to this project, you will need:

- PHP 8.1 or higher
- Composer
- Basic knowledge of the AEAT Veri*factu API
- Familiarity with PHPUnit for testing

## Development Environment Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/eseperio/verifactu-php.git
   cd verifactu-php
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

## Project Structure

- `src/` - Source code of the library
  - `models/` - Data models to represent invoices and other elements
  - `services/` - Services implementing business logic
  - `dictionaries/` - Dictionaries and error code mappings
- `tests/` - Unit and integration tests
- `docs/` - Project documentation and resources related to the AEAT API

## Running Tests

Tests are configured using PHPUnit. To run them:

```bash
vendor/bin/phpunit
```

To run a specific group of tests:

```bash
vendor/bin/phpunit --testsuite Unit
```

To run a specific test:

```bash
vendor/bin/phpunit tests/Unit/Models/ModelTest.php
```

To generate a coverage report:

```bash
vendor/bin/phpunit --coverage-html coverage
```

Make sure all tests pass before submitting a pull request.

## Contribution Guidelines

### Code Style

- Follow the [PSR-12](https://www.php-fig.org/psr/psr-12/) standard for code style.
- Use camelCase for method and property names.
- Name classes with PascalCase.
- Include DocBlock comments for all classes, methods, and properties.

### Contribution Process

1. **Create a fork** of the repository on GitHub.
2. **Create a branch** for your feature or fix:
   ```bash
   git checkout -b feature/descriptive-name
   ```
   or
   ```bash
   git checkout -b fix/bug-name
   ```
3. **Implement your changes** following the style guidelines.
4. **Add or update tests** to cover your changes.
5. **Run tests** to ensure everything passes correctly.
6. **Comment your commits** in a descriptive and helpful way.
7. **Push** to your fork.
8. **Create a pull request** describing your changes.

### Commits

- Use clear and descriptive commit messages.
- Each commit should represent a logical set of related changes.
- Reference Issues or Pull Requests using `#` followed by the number.

### Documentation

- Update documentation when adding or modifying functionality.
- Provide examples of usage for new features.
- Keep the README.md updated with any relevant changes.

## Adding New Tests

When adding new features, ensure you:

1. Create unit tests for each public method.
2. Verify edge cases and possible errors.
3. Maintain code coverage above 80%.
4. Structure tests in relation to the class they test.

## Reporting Bugs or Requesting Features

- Use GitHub Issues to report bugs or request features.
- Provide a clear title and detailed description.
- For bugs, include steps to reproduce and expected vs. actual behavior.
- For new features, explain the use case and benefits.

## Specific Considerations for Verifactu

- Consider AEAT technical specifications when implementing changes.
- Maintain backward compatibility when possible.
- Consider security implications when handling digital certificates.
- Document any changes to the API or requirements.

## License

By contributing to this project, you agree that your contributions will be licensed under the same license as the project (MIT).

---

Thank you for contributing to Verifactu-PHP!
