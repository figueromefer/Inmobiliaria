# Fase 1C — conciliación manual de entidades maestras

## Alcance y arquitectura

Fase 1C añade conciliación manual a un `ContractDraft` existente. Usa exclusivamente las FKs opcionales ya creadas en `contract_drafts`: `cliente_id`, `propiedad_id` e `inquilino_id`. No crea ni modifica `Cliente`, `Propiedad`, `Inquilino`, `Contrato`, ni el snapshot canónico de `ContractDraftVersion`.

`ContractDraftReconciliationService` concentra:

- búsquedas limitadas a 10 resultados por página, en servidor;
- validación y enlace/desenlace transaccional de una entidad maestra existente;
- comparación visual entre snapshot y maestro;
- bitácora mínima en la tabla existente `activity_logs` con el actor, la acción y sólo los IDs anterior/nuevo.

La conciliación es un dato operativo en la raíz del borrador, no una revisión jurídica ni documental. Por ello **no crea una nueva `ContractDraftVersion`**, no modifica `current_version_id` y no cambia `status=draft`.

## Rutas y permisos

Todas permanecen dentro de `auth` y `can:manage-records`, el Gate existente que permite sólo `admin` y `agent`:

- `GET /contratos/borradores/{draft}?cliente_q=…&propiedad_q=…&inquilino_q=…`
- `POST /contratos/borradores/{draft}/conciliacion/{cliente|propiedad|inquilino}`
- `DELETE /contratos/borradores/{draft}/conciliacion/{cliente|propiedad|inquilino}`

Un visitante redirige a login y `viewer` recibe 403. No se creó middleware, policy ni sistema de roles adicional.

## Búsqueda y vínculos

La sección **Conciliación** de la pantalla del borrador ofrece búsqueda server-side y paginada, sin cargar todos los maestros en un selector:

- Cliente: nombre, RFC o correo; se muestran nombre, RFC y correo.
- Propiedad: alias o domicilio; se muestran alias y domicilio.
- Inquilino: nombre, correo o teléfono; se muestran nombre y un dato de contacto.

Cada enlace valida con `exists` usando las PK reales (`clientes.pk_cliente`, `propiedades.pk_propiedad`, `inquilinos.id`) y vuelve a localizar la entidad dentro de la transacción. Un borrador puede tener cualquier combinación parcial de vínculos. Quitar un vínculo sólo deja la FK correspondiente en `null`.

## Comparación visual

La comparación conserva ambos valores originales y clasifica cada campo como `coincide`, `diferente`, `sin dato en draft` o `sin dato en maestro`.

Sólo para comparación visual se aplica `trim`, espacios repetidos, comparación sin distinguir mayúsculas/minúsculas, RFC en mayúsculas sin espacios y teléfono con sólo dígitos. No se persiste ninguna normalización ni se decide cuál fuente prevalece.

Los campos comparados son:

- Cliente/arrendador: nombre o razón social, RFC, teléfono, correo y domicilio.
- Propiedad: alias y domicilio.
- Inquilino/arrendatario: nombre o razón social, teléfono, correo, domicilio y nacionalidad.

No se expone `raw_legacy_payload` ni JSON canónico bruto.

## Auditoría y limitaciones

No se agregó `contract_audit_events`: el enlace/desenlace registra actor, tiempo y IDs en `activity_logs`. La bitácora no guarda el payload contractual ni datos PII adicionales. No hay aún historial especializado de conciliación ni mecanismo para atribuir una conciliación a una versión documental; esto es una limitación conocida para fases futuras.

No se implementó creación de maestros, matching automático, scoring, sincronización snapshot→maestro o maestro→snapshot, publicación, documentos, Google Drive, Apps Script, ni adaptador del intake legacy.

## Pruebas y verificación manual

`ContractDraftReconciliationTest` cubre:

- acceso de visitante/viewer y autorización de admin/agent;
- búsquedas de cliente, propiedad e inquilino;
- vínculo y desvínculo de las tres entidades;
- rechazo de ID inexistente;
- vínculo parcial;
- snapshot, versión actual y entidades maestras sin cambios;
- ausencia de creación de `Contrato`;
- comparación `coincide`/`diferente` con normalización visual.

Prueba manual sugerida:

1. Como `admin` o `agent`, abrir un borrador interno y llegar a **Conciliación**.
2. Buscar una entidad, vincularla y verificar que la tabla compara snapshot contra maestro.
3. Confirmar que la versión y hash mostrados no cambian; revisar la bitácora para el actor y los IDs.
4. Quitar el vínculo y confirmar que el snapshot continúa idéntico.
