# Changelog

## [Unreleased]

- Add setters for all complex properties. For collection properties, `add` method is provided. If properties are more
  than 3, it expects an object as parameter, otherwise it expects properties as parameters.
- Added models for all dependant schemas.
- Added support for choosing which engine to use for QrCode generation.
- Added support for changing the size of the QrCode.
- QrGeneratorService now can either return qr as string or save it to a file.
- No longer encode the QrCode as base64 by default, you should do it by yourself if you need it.
