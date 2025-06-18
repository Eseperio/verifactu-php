# Changelog

## [Unreleased]

- Added support for choosing which engine to use for QrCode generation.
- Added support for changing the size of the QrCode.
- QrGeneratorService now can either return qr as string or save it to a file.
- No longer encode the QrCode as base64 by default, you should do it by yourself if you need it.
