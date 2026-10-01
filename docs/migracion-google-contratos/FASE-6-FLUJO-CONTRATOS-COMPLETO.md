# Flujo completo de contratos privados

## Arquitectura

Un contrato privado se captura en un `ContractDraft` con snapshots inmutables `ContractDraftVersion`.
La publicación congela la versión, genera o reintenta el documento Drive y sólo después registra el `Contrato`.
La clave `finalization_key` persiste en el draft y evita duplicados ante doble submit o reintentos.
El `Contrato` apunta a su snapshot y documento vigentes mediante `contract_draft_version_id` y `contract_document_version_id`.
Los documentos y snapshots anteriores no se eliminan.

## Flujo privado

`/contratos` → Nuevo contrato privado → preparación/concilación → captura compartida → resumen → Generar contrato → Drive → listado.
La conciliación sólo copia información del maestro a campos vacíos del snapshot. Cambios posteriores a Cliente, Propiedad o Inquilino no cambian lo contratado.
El formulario público utiliza el mismo renderer, DTO y ramas PF/PM, pero conserva token, CSRF, throttle, honeypot y envío público. Nunca publica un Contrato definitivo.

## Edición y renovación

Editar crea un draft `purpose=revision` con `editing_contract_id`; copia el snapshot vigente y al generar actualiza los punteros del mismo Contrato. La carpeta Drive vigente se reutiliza cuando existe.
Renovar crea un draft `purpose=renewal` con `renewal_of_contract_id`, establece `renewal.is_renewal=yes` y `renewal.previous_contract_id`. Publica otro Contrato, Documento y carpeta Drive con `previous_contract_id` al contrato previo.
No hay cambios automáticos de fechas ni banderas artificiales de activo. `Contrato::activosEnMes` determina vigencia; en solapamientos se conserva el comportamiento existente.

## Compatibilidad y permisos

Históricos sin snapshot usan un fallback limitado a campos existentes de Contrato y relaciones; queda identificado en `raw_legacy_payload` y los faltantes se capturan manualmente.
Justicia Alternativa conserva listado, detalle e importación, sin edición ni renovación contractual.
Las rutas privadas requieren los permisos operativos actuales. La URL Drive se deriva sólo de un ID validado y se abre con `noopener noreferrer`.

## Migraciones

1. `2026_10_01_000000_add_contract_publication_links_to_contract_drafts_table`
2. `2026_10_01_000001_add_contract_publication_links_to_contratos_table`
3. `2026_10_01_000002_add_contract_revision_context_to_contract_drafts_table`
4. `2026_10_01_000003_add_contract_renewal_context_to_contract_drafts_table`

Son add-only: columnas nullable, índices y FKs con `nullOnDelete`; no hay backfill, recreación de tablas ni borrado de históricos.

## Runbook cPanel sin SSH (no ejecutar automáticamente)

1. Respaldar base de datos y archivos del release actual.
2. Hacer Git Pull del commit aprobado mediante el mecanismo cPanel autorizado.
3. Ejecutar `php artisan migrate --pretend` mediante el deploy temporal allowlisted `deploy-contratos-4b.php`.
4. Revisar SQL y ejecutar `php artisan migrate --force` por ese mismo canal.
5. Ejecutar `php artisan optimize:clear`, `config:cache` y `view:cache`.
6. Smoke test: listado, creación privada, solicitud pública y Justicia Alternativa.
7. Si falla, detener publicaciones, restaurar backup y revertir el release. Las migraciones tienen `down`, pero la reversión funcional debe preservar documentos Drive y contratos publicados.
