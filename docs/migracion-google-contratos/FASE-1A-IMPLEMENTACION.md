# Fase 1A — infraestructura de borradores y versionado

## Alcance aplicado

Esta entrega crea exclusivamente la infraestructura física para `contract_data_v1`. No conecta Google Forms, Apps Script, Drive ni el endpoint legacy con los nuevos modelos; tampoco crea contratos definitivos ni entidades maestras.

## Migraciones aditivas y reversibles

1. `2026_09_15_000000_create_contract_drafts_table.php`
   - Crea la raíz estable `contract_drafts` con referencias opcionales a `contratos.id`, `clientes.pk_cliente`, `propiedades.pk_propiedad`, `inquilinos.id` y `users.id`.
   - `current_version_id` se declara primero para evitar un ciclo y su FK se agrega después de existir la tabla de versiones.
2. `2026_09_15_000001_create_contract_draft_versions_table.php`
   - Crea snapshots inmutables con `(contract_draft_id, draft_version)` único, DTO JSON, hash, origen/acción y payload legacy opcional.
   - Agrega el FK nullable de `contract_drafts.current_version_id` a la nueva tabla.
3. `2026_09_15_000002_create_contract_document_versions_table.php`
   - Crea historial documental técnico, sin generar documentos, con versión e `idempotency_key` únicos.

Los `down()` revierten sólo estas tablas/FK nuevos. No eliminan ni alteran tablas existentes.

## Modelos y relaciones

- `ContractDraft`: raíz; pertenece opcionalmente a contrato, cliente, propiedad, inquilino y usuarios; expone versiones y versión actual.
- `ContractDraftVersion`: pertenece al borrador, conserva `canonical_payload` y `raw_legacy_payload` como arrays, y rechaza actualizaciones Eloquent para evitar modificación silenciosa de snapshots.
- `ContractDocumentVersion`: pertenece a una versión de borrador; representa una futura solicitud/resultado documental y no ejecuta Drive.

No se modificaron `Contrato`, `ContratoPendiente`, `Cliente`, `Propiedad`, `Inquilino`, `FormsIntakeController`, rutas ni Apps Script.

## Servicios

- `ContractPayloadCanonicalizer`: ordena recursivamente las claves de objetos, conserva el orden de arreglos y calcula SHA-256 del JSON canónico. No transforma valores; por ejemplo, `dias_pago.raw_text` queda literalmente igual.
- `ContractDraftVersioningService`: crea raíz + versión inicial o agrega una versión en transacción; bloquea la raíz, calcula el siguiente número, crea snapshot y actualiza `current_version_id`.
- `ContractDocumentVersioningService`: crea versiones documentales técnicas en transacción. Sólo registra `not_requested`; no llama a Google ni encola trabajo.

## Pruebas añadidas

- `ContractDraftVersioningTest`: versión inicial y segunda, relación actual, payload/raw payload, unicidad, inmutabilidad, hash determinista, preservación del orden de arrays y de `05 a 10`, versiones documentales, idempotencia y rollback ante serialización imposible.
- `FormsIntakeLegacyRegressionTest`: verifica que `POST /api/forms/contratos` sigue creando/actualizando exactamente un `ContratoPendiente` y no crea drafts.

## Comandos y resultados

```bash
php artisan test tests/Feature/ContractDraftVersioningTest.php
php artisan test tests/Feature/FormsIntakeLegacyRegressionTest.php
php artisan test
git diff --check
```

Resultados obtenidos:

- `ContractDraftVersioningTest` y `FormsIntakeLegacyRegressionTest`: **17 pruebas, 64 aserciones, todas aprobadas**.
- Pruebas relacionadas adicionales (`ContratoDetalleTest` y `PreProductionFixesTest`): **28 pruebas, 122 aserciones, todas aprobadas**.
- Suite completa: **92 aprobadas, 410 aserciones**. Conserva **2 fallos preexistentes** en `Tests\\Feature\\Auth\\RegistrationTest`, porque `/register` está deshabilitada y responde 404. Esta entrega no modifica registro/autenticación.
- `git diff --check` y `git diff --cached --check`: sin errores de espacios.

No se ejecutó `php artisan migrate`, ni ningún comando de migración sobre una base de datos de aplicación. Las pruebas usan la base SQLite en memoria configurada por `phpunit.xml` y `RefreshDatabase`.

## Riesgos pendientes

- DH-05 jurídico permanece abierto: esta infraestructura no habilita publicación ni wizard.
- Los payloads JSON pueden contener PII; los servicios no registran payloads ni agregan dumps.
- El diseño no conecta aún `ContratoPendiente` con drafts; ese adaptador corresponde a una fase posterior y requiere pruebas de convivencia.

## Correcciones post-auditoría

| Hallazgo | Corrección | Prueba añadida / estado final |
|---|---|---|
| Nombre automático de unique demasiado largo para MySQL en versión documental | El unique compuesto usa `cdv_draft_version_unique`; los nuevos nombres de índices/FK se revisaron y quedan dentro de 64 caracteres. | Pendiente smoke test MySQL/MariaDB; la definición ya no excede el límite. |
| Retry técnico con la misma `idempotency_key` fallaba por unique | El servicio bloquea la versión de draft, busca por clave y devuelve la versión existente sólo si versión, hash y plantilla son compatibles. Una clave incompatible lanza excepción de dominio sin PII. | Retry devuelve la misma fila; regeneración con clave nueva crea versión 2; conflicto no agrega filas. |
| `current_version_id` permitía asignación masiva y no verificaba pertenencia | Se eliminó de `fillable`; sólo `ContractDraftVersioningService` puede asignarlo, bajo transacción y validando que la versión pertenece al draft. | Se cubre asignación masiva ignorada y rechazo de versión de otro draft. |
| Futuros adapters podían duplicar draft por origen/ID externo | Se agregó unique `contract_drafts_source_external_unique` sobre `source, external_id`. En MySQL/InnoDB, múltiples `external_id=NULL` son permitidos y el mismo valor no nulo para el mismo origen es rechazado. | Se cubren duplicado no nulo y dos drafts independientes con `NULL`. |
| Las versiones posteriores heredaban el actor del creador | `appendVersion` recibe `createdBy` explícito. | Se cubre creador A y edición posterior por usuario B. |
| Canonicalizador admitía objetos y estructuras ambiguas | Acepta sólo escalares definidos, UTF-8 válido, listas densas y objetos asociativos de claves string válidas; rechaza objetos, recursos, no finitos, UTF-8 inválido y claves ambiguas/no densas. | Se cubren DateTime, JsonSerializable, closure, resource, NaN/INF, UTF-8 inválido y arrays ambiguos. |
| Hash recalculaba el payload | El servicio canonicaliza una vez, persiste ese array y calcula con `hashCanonical` sobre esa misma representación. | Se compara hash de payload canónico con hash persistido. |
| Identidad documental podía actualizarse accidentalmente | Campos de identidad salieron de `fillable`; el modelo rechaza cambios de identidad y el servicio centraliza mutaciones operativas permitidas. | Se cubren identidad bloqueada y cambio controlado de estado/intentos/error. |
| Payloads con PII podían serializarse automáticamente | `canonical_payload` y `raw_legacy_payload` son atributos ocultos del modelo de versión. | Se verifica que `toArray()` no los expone. |
| Regresión legacy era insuficiente | La prueba verifica pendiente único, ausencia de drafts y contratos definitivos, mismo ID en segundo POST y preservación de `raw_payload`/`mapped_payload`. | El endpoint y controlador no fueron modificados. |

## Validación MySQL

La validación se ejecutó exclusivamente mediante TCP contra la base temporal aislada `inmobiliaria_contracts_phase1_test` en `127.0.0.1:3308`; no se usó la conexión de `.env`, ni datos reales. Motor probado: **MySQL Community Server 8.0.46**, InnoDB.

- **Migración y reversión:** `migrate:fresh --force` aplicó el esquema completo, incluidas las tres migraciones de Fase 1A. `migrate:rollback --step=3 --force` revirtió, en este orden, `contract_document_versions`, `contract_draft_versions` y `contract_drafts`; la FK de `current_version_id` se retiró antes de eliminar las tablas dependientes. Una consulta posterior a `INFORMATION_SCHEMA` no encontró esas tablas ni FKs. Un `migrate --force` posterior las creó nuevamente sin errores.
- **Índices y constraints:** `INFORMATION_SCHEMA` confirmó las FKs y uniques esperadas. Los nombres más largos son `contract_draft_versions_contract_draft_id_draft_version_unique` (62 caracteres) y `contract_document_versions_contract_draft_version_id_foreign` (60); todos son menores o iguales a 64 caracteres. El compuesto documental usa explícitamente `cdv_draft_version_unique`.
- **JSON y relaciones:** se insertó y recuperó un `canonical_payload` sintético; MySQL reportó `JSON_TYPE = OBJECT`. Las FKs reales rechazaron un `current_version_id` inexistente; una versión del mismo draft pudo ser el puntero actual. La protección de dominio contra versiones de otro draft quedó cubierta también por pruebas del servicio.
- **Unique `source, external_id`:** dos drafts con `source=laravel` y `external_id=NULL` se aceptaron; el segundo `google_form/ABC123` fue rechazado; `legacy_import/ABC123` coexistió con `google_form/ABC123`. Esto confirma la semántica de `NULL` de MySQL/InnoDB requerida por el adapter futuro.
- **Locks y concurrencia:** dos procesos PHP simultáneos agregaron versiones al mismo draft bajo MySQL. Obtuvieron `draft_version` 3 y 4, quedaron las versiones `1,2,3,4` y `current_version_id` apuntó a la 4. Dos procesos simultáneos para la misma versión de borrador crearon versiones documentales 3 y 4, sin repetición ni pérdida. Esto ejercita los bloqueos `FOR UPDATE` implementados por los servicios.
- **Idempotencia documental:** la clave X creó versión documental 1; el retry X devolvió la misma fila y no creó la 2; reutilizar X con plantilla incompatible lanzó la excepción de dominio y no agregó filas; la clave Y creó la versión 2. Las pruebas concurrentes adicionales produjeron 3 y 4 con claves nuevas.
- **Tests MySQL:** PHPUnit, configurado temporalmente fuera del repositorio y forzado a esa misma base, aprobó `ContractDraftVersioningTest`, `FormsIntakeLegacyRegressionTest` y `ContratoDetalleTest`: **21 pruebas, 89 aserciones**. La suite completa ejecutó **94 pruebas, 410 aserciones**, con sólo los **2 fallos preexistentes** de `RegistrationTest` porque `/register` está deshabilitado (404).
- **Diferencias SQLite/MySQL detectadas:** ninguna incompatibilidad de JSON, FK, índices, uniques, rollback o transacción en estas migraciones/servicios. La validación MySQL aporta cobertura real de `NULL` en unique y `FOR UPDATE`, que SQLite en memoria no reproduce de forma equivalente.

Al finalizar se retiraron las tres tablas y FKs de Fase 1A; `INFORMATION_SCHEMA` confirmó `0` tablas y `0` constraints restantes para ellas. Se eliminaron los auxiliares temporales de validación. El intento de `migrate:reset --force` para retirar también el resto del esquema temporal se detuvo en un `down()` histórico ajeno a Fase 1A (`2026_07_09_000001_add_assignment_fields_to_movimientos_table`): MySQL no permite retirar `movimientos_inquilino_id_fecha_index` mientras una FK todavía lo requiere. No se modificó esa migración fuera de alcance; el contenedor/base temporal debe destruirse externamente si se requiere eliminar el esquema residual completo.
