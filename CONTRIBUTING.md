# Contribuyendo a Verifactu-PHP

¡Gracias por tu interés en contribuir a la biblioteca Verifactu-PHP! Esta guía te ayudará a entender el proceso de contribución y a garantizar que tu aportación se integre de manera eficiente.

## Requisitos previos

Para contribuir a este proyecto, necesitarás:

- PHP 8.1 o superior
- Composer
- Conocimientos básicos sobre la API Veri*factu de la AEAT
- Familiaridad con PHPUnit para tests

## Configuración del entorno de desarrollo

1. Clona el repositorio:
   ```bash
   git clone https://github.com/eseperio/verifactu-php.git
   cd verifactu-php
   ```

2. Instala las dependencias:
   ```bash
   composer install
   ```

## Estructura del proyecto

- `src/` - Código fuente de la biblioteca
  - `models/` - Modelos de datos para representar facturas y otros elementos
  - `services/` - Servicios que implementan la lógica de negocio
  - `dictionaries/` - Diccionarios y mapeos de códigos de error
- `tests/` - Tests unitarios y de integración
- `docs/` - Documentación del proyecto y recursos relacionados con la API de AEAT

## Ejecutando los tests

Los tests están configurados usando PHPUnit. Para ejecutarlos:

```bash
vendor/bin/phpunit
```

Para ejecutar un grupo específico de tests:

```bash
vendor/bin/phpunit --testsuite Unit
```

Para ejecutar un test específico:

```bash
vendor/bin/phpunit tests/Unit/Models/ModelTest.php
```

Para generar un informe de cobertura:

```bash
vendor/bin/phpunit --coverage-html coverage
```

Asegúrate de que todos los tests pasen antes de enviar un pull request.

## Directrices de contribución

### Estilo de código

- Sigue el estándar [PSR-12](https://www.php-fig.org/psr/psr-12/) para el estilo de código.
- Usa camelCase para los nombres de métodos y propiedades.
- Nombra las clases con PascalCase.
- Incluye comentarios DocBlock para todas las clases, métodos y propiedades.

### Proceso de contribución

1. **Crea un fork** del repositorio en GitHub.
2. **Crea una rama** para tu funcionalidad o corrección:
   ```bash
   git checkout -b feature/nombre-descriptivo
   ```
   o
   ```bash
   git checkout -b fix/nombre-bug
   ```
3. **Implementa tus cambios** siguiendo las directrices de estilo.
4. **Añade o actualiza los tests** para cubrir tus cambios.
5. **Ejecuta los tests** para asegurarte de que todo pasa correctamente.
6. **Comenta tus commits** de manera descriptiva y útil.
7. **Push** a tu fork.
8. **Crea un pull request** describiendo tus cambios.

### Commits

- Usa mensajes de commit claros y descriptivos.
- Cada commit debe representar un conjunto lógico de cambios relacionados.
- Referencia Issues o Pull Requests usando `#` seguido del número.

### Documentación

- Actualiza la documentación cuando añadas o modifiques funcionalidades.
- Proporciona ejemplos de uso para nuevas funcionalidades.
- Mantén actualizado el README.md con cualquier cambio relevante.

## Añadiendo nuevos tests

Al añadir nuevas funcionalidades, asegúrate de:

1. Crear tests unitarios para cada método público.
2. Verificar casos límite y posibles errores.
3. Mantener una cobertura del código superior al 80%.
4. Estructurar los tests en relación con la clase que prueban.

## Reportar bugs o solicitar funcionalidades

- Usa GitHub Issues para reportar bugs o solicitar funcionalidades.
- Proporciona un título claro y una descripción detallada.
- Para bugs, incluye los pasos para reproducirlo y el comportamiento esperado vs. actual.
- Para nuevas funcionalidades, explica el caso de uso y los beneficios.

## Consideraciones específicas para Verifactu

- Ten en cuenta las especificaciones técnicas de la AEAT al implementar cambios.
- Mantén la retrocompatibilidad cuando sea posible.
- Considera implicaciones de seguridad al manejar certificados digitales.
- Documenta cualquier cambio en la API o en los requisitos.

## Licencia

Al contribuir a este proyecto, aceptas que tus contribuciones se licenciarán bajo la misma licencia que el proyecto (MIT).

---

¡Gracias por contribuir a Verifactu-PHP!
