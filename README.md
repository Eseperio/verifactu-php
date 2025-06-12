# Verifactu PHP

## ⚠️ 2025: Librería en desarrollo

Librería para la integración del sistema de la agencia tributaria española "Veri*factu" (verifactu)

> ℹ️ Este proyecto comenzó hace dos años con la primera especificación del sistema Verifactu (tenía que haber entrado en
> vigor en 2024), pero ante el volumen de mutaciones me vi obligado a dejarlo aparcado. En 2025, con la nueva
> especificación de la AEAT, he decidido retomar el proyecto.

## Instalación

### Composer

Puedes instalarlo usando composer.

> Actualmente la librería no está listada en packagist, por lo que debes añadir el repositorio en tu composer.json

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/Eseperio/verifactu-php.git"
    }
  ]
}
```

Y ejecutar

```bash
composer require eseperio/verifactu-php 
```


