# Fase 3B — Generación real de Google Docs

## Alcance

La generación parte exclusivamente de una `ContractDocumentVersion` creada para la `ContractDraftVersion` que fue previsualizada. No publica contratos, no altera maestros, ni toca Google Forms, Apps Script o el endpoint legacy.

## Configuración segura

Las variables se leen únicamente en tiempo de ejecución y nunca deben incluirse en Git:

- `GOOGLE_CONTRACT_TEMPLATE_WITHOUT_GUARANTOR_ID`
- `GOOGLE_CONTRACT_TEMPLATE_WITH_GUARANTOR_ID`
- `GOOGLE_CONTRACT_DESTINATION_FOLDER_ID`
- `GOOGLE_SERVICE_ACCOUNT_JSON` **o** `GOOGLE_SERVICE_ACCOUNT_JSON_PATH`
- `GOOGLE_CONTRACT_TIMEOUT`

Los valores de plantilla deben ser los IDs verificados en el gate físico, y la carpeta destino debe ser una carpeta compartida con el correo de la Service Account. La cuenta requiere los scopes mínimos `drive` y `documents`; la carpeta y ambas plantillas deben compartirse explícitamente con esa cuenta. El JSON, claves privadas, tokens y encabezados de autorización no se escriben a logs ni documentación.

## Arquitectura

- `ContractDocumentPayloadBuilder`: fuente de verdad pura para plantilla, placeholders, operaciones y marcadores aplicables por `template_key`.
- `GoogleContractDocumentClient`: contrato aislado y simulable para Drive/Docs.
- `GoogleServiceAccountTokenProvider`: obtiene un token OAuth2 mediante JWT RS256 de Service Account y lo conserva temporalmente en caché.
- `GoogleApiContractDocumentClient`: copia plantillas con Drive, crea subcarpeta de contrato, aplica operaciones con Docs `batchUpdate` y vuelve a leer el documento para verificar marcadores residuales.
- `GoogleContractDocumentRenderer`: orquesta idempotencia, estados persistidos y recuperación segura de fallos.

La plantilla original nunca recibe un `batchUpdate`: primero se llama a `files.copy`, y todas las operaciones apuntan al `drive_file_id` de la copia.

## Flujo y estados

1. La previsualización verifica que el snapshot está `ready` y emite `expected_draft_version_id` e `idempotency_key`.
2. El POST bloquea el borrador y crea o reutiliza una `ContractDocumentVersion` sólo si sigue siendo el snapshot actual.
3. El renderer reclama el intento: incrementa `attempts` y pasa a `processing`.
4. Crea una subcarpeta usando `folder_name`, salvo que ya exista `drive_folder_id` en esa misma versión.
5. Copia la plantilla usando `document_name`, salvo que ya exista `drive_file_id`.
6. Persiste inmediatamente los IDs externos recuperados, aplica el plan documental y relee la copia.
7. Si no quedan marcadores `required_markers`, queda `generated`; de lo contrario queda `failed` con un mensaje saneado.

`requires_review` y `blocked` no llegan al renderer. Un POST sobre una previsualización obsoleta devuelve conflicto sin crear solicitud ni invocar Google.

## Idempotencia y reintento

La misma `idempotency_key` reutiliza la misma `ContractDocumentVersion`. Si el fallo ocurre después de copiar el archivo y se alcanzó a persistir `drive_file_id`, el retry técnico reutiliza ese archivo y carpeta; incrementa `attempts` sin crear una segunda versión ni borrar archivos automáticamente. Un documento `processing` no se ejecuta simultáneamente desde un segundo request.

No se implementa aún regeneración humana con una nueva versión documental.

## Operaciones y verificación

El cliente sólo recibe operaciones físicas del inventario de la plantilla seleccionada: reemplazos de texto, tablas `__T1__`–`__T9__`, párrafos `__I1__`–`__I6__`, firmas, la tabla `INSTITUCIÓN BANCARIA` y las cláusulas aplicables. Para PF elimina los párrafos PM `__I*__`; para PM elimina la declaración física literal y limpia los marcadores de declaración moral, replicando el comportamiento observable del Apps Script. No busca ni sustituye `{{garantia}}` o `{{garantia_monto}}` porque no existen en las plantillas activas.

Para borrar tablas el cliente localiza el marcador dentro de la estructura leída del documento y elimina el rango hallado; no usa posiciones fijas. Cada operación estructural se resuelve contra una lectura actual, por lo que no depende de índices de una operación anterior. Al final verifica que ningún marcador requerido por el `template_key` permanezca en la copia.

## Interfaz y permisos

Las rutas viven dentro de `auth` + `can:manage-records`; por la regla actual del proyecto pueden usarlas `admin` y `agent`, nunca `viewer` o invitados.

La previsualización muestra el botón **Generar documento** sólo cuando el payload está `ready`. Muestra versión, estado, intentos, URL de la copia y, para un fallo, un mensaje saneado y el botón de retry. No muestra secretos ni payload crudo.

## Pruebas

`ContractDocumentPreviewTest` sustituye el cliente Google por un fake y cubre:

- copia de plantilla, creación de carpeta y estado `generated`;
- invariantes de que las operaciones nunca apuntan al ID de plantilla;
- idempotencia de doble submit;
- fallo de verificación por marcador residual;
- retry sobre la misma versión reutilizando `drive_file_id` y carpeta;
- `blocked` y versiones obsoletas sin llamadas a Google;
- invitado y `viewer` sin acceso.

Las pruebas no requieren red, credenciales ni documentos de Drive.

## Smoke test de sandbox — aprobado

Se validó la generación real en un Shared Drive sandbox autorizado, usando credenciales configuradas fuera del repositorio y datos ficticios. La prueba confirmó la creación de copias desde las plantillas, la carpeta de destino, la ausencia de marcadores requeridos residuales y la conservación de las plantillas originales.

La habilitación productiva sigue condicionada a usar credenciales, plantillas y carpeta de destino **productivas** —no las del sandbox—, como se detalla en `FASE-4B-DESPLIEGUE-Y-CONVIVENCIA.md`. Los IDs de evidencia sandbox no se versionan.

## Rollback operativo

Desactivar temporalmente el botón/ruta de generación o retirar las variables de Service Account impide nuevas llamadas sin modificar los borradores. Las versiones locales y copias existentes se conservan para trazabilidad. No se borran automáticamente carpetas o archivos creados durante un fallo.
