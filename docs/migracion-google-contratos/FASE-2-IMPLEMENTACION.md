# Fase 2 — captura completa Laravel

## Arquitectura y rutas

La captura se implementa como wizard Blade sobre el `ContractDraft` y el versionado inmutable de Fase 1A/1B. Cada guardado usa `expected_version_id`, bloquea y relee el borrador mediante `ContractDraftVersioningService`, y produce una versión nueva; una edición concurrente muestra un conflicto controlado sin sobrescribir datos.

Rutas internas, protegidas por `auth` y `can:manage-records` (`admin` y `agent`):

- `GET /contratos/borradores/{draft}/captura/{step}`
- `PUT /contratos/borradores/{draft}/captura/{step}`

Los pasos son: datos generales, arrendador, arrendatario, tercero/fiador, garantía, vigencia e importes, uso, pago, mantenimiento, renovación y resumen. El resumen es sólo lectura y declara explícitamente **Borrador — no publicado**.

## Captura y ramas técnicas

- Arrendador y arrendatario capturan las ramas física/moral V1, incluidos RFC, contacto, domicilio, datos físicos, acta y representante cuando corresponde.
- Tercero usa el objeto estable `guarantor`; `none` limpia persona/representante y fuerza la garantía a `no`.
- Garantía captura domicilio y título sólo para `exists=yes`.
- Vigencia preserva literalmente `term.rent_due_rule.raw_text`, incluido `05 a 10`; no infiere rangos.
- Importes incluyen ambos valores de comisión y su unidad explícita, sin inferirla.
- `property_use_codes` se guarda como lista ordenada y permite multiuso.
- Transferencia muestra banco/beneficiario/CLABE; al cambiar a efectivo/no especificado el normalizador servidor limpia esos valores.
- Mantenimiento y renovación limpian sus campos dependientes cuando cambian a `no`.

JavaScript sólo muestra/oculta ramas. El DTO completo se normaliza y valida en servidor por `ContractDraftPayload`; los requisitos jurídicos R* continúan sin imponerse y un borrador incompleto puede guardarse.

## Integridad y conciliación

El wizard no toca `cliente_id`, `propiedad_id` ni `inquilino_id`, ni copia datos entre maestros y snapshot. La conciliación de Fase 1C permanece separada y visible desde el borrador. No se crean contratos definitivos, maestros, documentos ni integraciones Google.

## Diferencias y decisiones pendientes

- No se usa el alias legado con typo; la UI usa `leased_property.alias` estable.
- `metadata.contract_reference` sigue nullable y `metadata.external_id` no se captura internamente.
- El domicilio PF del arrendatario se captura sólo si el usuario lo proporciona: nunca se infiere del inmueble.
- Los contactos de arrendador se conservan en su rama contractual; no se mezclan automáticamente con contactos de maestro.
- DH-02, DH-05, DH-09 y DH-10 siguen pendientes. La captura no autoriza publicación ni generación documental.

## Pruebas y checklist manual

`ContractDraftWizardTest` cubre apertura/autorización, guardado por paso, PF/PM, tercero/garantía, multiuso, pago, mantenimiento, renovación, literal `05 a 10`, inmutabilidad, conflicto y preservación de conciliación.

Checklist manual:

1. PF/PF sin tercero.
2. PM/PM con tercero PF.
3. PF/PM con tercero PM y garantía.
4. Transferencia y después efectivo para comprobar limpieza bancaria.
5. Multiuso y renovación.
6. Guardar, salir, reabrir y navegar entre pasos.
7. Confirmar en resumen que no existe acción de publicar o generar documentos.

## Correcciones post-auditoría

- **Resumen ampliado:** ahora muestra datos generales, propiedad y todos los usos seleccionados, partes, garantía, vigencia, importes, pago y banco sólo para transferencia, mantenimiento, renovación y los tres vínculos de conciliación. No muestra JSON, payload legacy, publicación ni acciones Google.
- **Cobertura de pasos:** se verifica por HTTP el acceso `GET` de `admin` y `agent` a los once pasos y que navegar no crea versiones.
- **Ramas adicionales:** se cubren tercero PM→PF→none, garantía yes→no, pago transferencia→efectivo→transferencia/no especificado, ambos pagadores de mantenimiento y multiuso/vaciado explícito.
- **Navegación y reapertura:** se prueba guardar y salir, reabrir un paso no secuencial y llegar al resumen sin perder datos previos.
- **Conflicto:** el caso con `expected_version_id` obsoleto verifica que permanece la versión N+1 y no se crea N+2.
