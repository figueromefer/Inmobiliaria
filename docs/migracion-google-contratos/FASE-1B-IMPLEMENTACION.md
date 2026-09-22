# Fase 1B — interfaz interna de borradores V1

## Alcance

Se incorporó una interfaz interna para crear, consultar y versionar borradores `contract_data_v1`. Esta fase no publica contratos, no genera documentos y no conecta Google Forms, Apps Script ni Drive.

## Rutas y autorización

Todas las rutas están dentro de `auth` y además usan el Gate existente `manage-records`:

- `GET /contratos/borradores`
- `GET /contratos/borradores/nuevo`
- `POST /contratos/borradores`
- `GET /contratos/borradores/{draft}`
- `GET /contratos/borradores/{draft}/editar`
- `PUT /contratos/borradores/{draft}`
- `GET /contratos/borradores/{draft}/versiones`
- `GET /contratos/borradores/{draft}/versiones/{version}`

El Gate existente autoriza exclusivamente los roles internos `admin` y `agent`. El rol `viewer` y visitantes no acceden. No se creó un sistema de roles, policy o middleware paralelo.

## Arquitectura

- `ContractDraftController` orquesta la interfaz y utiliza el servicio de Fase 1A; no toca `Contrato`, `ContratoPendiente`, `Cliente`, `Propiedad` ni `Inquilino`.
- `StoreContractDraftRequest` y `UpdateContractDraftRequest` validan solicitud/autorización antes del controlador.
- `ContractDraftPayload` concentra el contrato técnico del DTO: parte de un DTO vacío estable, rechaza claves y catálogos desconocidos, valida tipos técnicos, fechas, importes y listas; también limpia ramas técnicamente no aplicables.
- `ContractDraftVersioningService` crea el draft con versión 1 o agrega una nueva versión inmutable. Cada versión registra al actor de esa operación y actualiza `current_version_id` dentro de la transacción existente.

La pantalla muestra campos legibles del snapshot canónico. No muestra `raw_legacy_payload` ni JSON bruto; el historial permite consultar snapshots históricos sólo en lectura.

## Validación técnica aplicada

- Borradores incompletos son válidos: no se hicieron obligatorios los campos `R*` pendientes de DH-05.
- `source` se fija a `laravel` y el estado raíz a `draft`.
- Se validan catálogos V1, fechas `YYYY-MM-DD` cuando existen, importes numéricos, IDs enteros y `property_use_codes` como lista ordenada.
- `term.rent_due_rule.raw_text` es texto literal: `05 a 10` no se convierte a entero ni se normaliza.
- `guarantor` conserva siempre `{type, person, representative}`; con `type=none` limpia persona, representante e inmueble en garantía. Con garantía `no`, dirección y título quedan en `null`.
- Pago distinto de transferencia limpia datos bancarios; mantenimiento `no` limpia pagador; renovación `no` limpia sus datos dependientes.

## Limitaciones pendientes

- DH-05 continúa abierto: esta validación no equivale a obligatoriedad jurídica ni autoriza publicación.
- No se implementan vínculos o conciliación automática con maestros; las FKs permanecen opcionales y esta UI no crea ni altera maestros.
- No existen estados de publicación, documentos, reintentos, Drive, adaptador legacy ni modificación de `POST /api/forms/contratos`.

## Correcciones post-auditoría

- **Conflicto concurrente:** la edición envía `expected_version_id`. `ContractDraftVersioningService` adquiere el lock del borrador, compara la versión actual dentro de la transacción y sólo entonces fusiona el payload parcial. Una versión obsoleta genera un error de validación visible que pide recargar; no crea una tercera versión ni sobrescribe datos.
- **Limpieza PF/PM:** se separaron campos técnicos exclusivos de persona física y moral. Al cambiar de rama se limpian los campos exclusivos anteriores; `rfc`, domicilio, teléfono y correo se conservan como campos técnicamente compartidos. Para PF el representante siempre queda en `null`; para PM puede permanecer `null` mientras el draft esté incompleto.
- **Estructura:** nodos raíz y subnodos críticos se verifican como objetos antes de fusionar. Entradas como `lessor="x"`, `payment=123` o `guarantor="foo"` devuelven validación controlada, no error interno.
- **Campos reservados:** `document_generation` y `audit` sólo aceptan objetos vacíos en Fase 1B; se rechazan subclaves inyectadas.
- **Listas vacías:** el formulario marca explícitamente el envío de `property_use_codes`. Si no llega el campo se conserva el snapshot; si llega vacío se persiste `[]`; cualquier lista recibida reemplaza completa y conserva su orden.
- **Fechas:** además del formato, se valida fecha calendario real, incluidos años bisiestos. No se añadió ninguna regla jurídica de orden de vigencia.
- **Identidad externa:** `metadata.external_id` se rechaza para captura `laravel`; la columna raíz sigue naciendo en `NULL`.
- **Pruebas:** se agregaron casos de conflicto, todas las transiciones PF/PM solicitadas, limpieza de pago/mantenimiento/renovación/fiador, URL de versión de otro borrador, autorización directa y preservación/reemplazo/vaciado de usos.

## Pruebas y verificación manual

Las pruebas feature cubren acceso por rol, creación incompleta, versión 1 y 2, inmutabilidad, actor, hash/historial, reglas de tercero, rechazo técnico, preservación de campos todavía no expuestos y ausencia de creación de entidades maestras. También se conservan las regresiones de Fase 1A y Forms Intake.

Resultados ejecutados:

- `ContractDraftManagementTest`, `ContractDraftVersioningTest` y `FormsIntakeLegacyRegressionTest`: **37 pruebas, 209 aserciones**, aprobadas.
- `ContratoDetalleTest`: **4 pruebas, 25 aserciones**, aprobadas.
- Suite completa: **112 aprobadas, 555 aserciones**; conserva únicamente los **2 fallos preexistentes** de `RegistrationTest` porque `/register` está deshabilitada y devuelve 404. Fase 1B no modifica autenticación ni registro.

Prueba manual sugerida:

1. Con un usuario `admin` o `agent`, abrir **Contratos → Borradores internos**.
2. Crear un borrador vacío o con `05 a 10` como regla de pago; confirmar versión 1.
3. Editar la renta y guardar; confirmar versión 2 e historial legible.
4. Abrir versión 1 y verificar que no ofrece edición ni expone payload legacy.
5. Con `viewer`, confirmar respuesta 403; como visitante, confirmar redirección a login.
