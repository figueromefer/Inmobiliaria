# Plan de migración progresiva: contratos Google → Laravel

## Alcance y fuentes auditadas

Este documento es un plan técnico; no cambia el flujo actual. Se revisaron el código Laravel y el respaldo local de Google Forms/Apps Script en esta carpeta. No se consultó Google externamente ni se inspeccionaron secretos o cuentas de producción.

Fuentes principales: `appsscript/Código.js`, `appsscript/appsscript.json`, los inventarios/mapeos CSV y Markdown del respaldo, `routes/api.php`, `FormsIntakeController`, `Contrato`, `ContratoPendiente`, sus migraciones, controladores/vistas de contratos y pruebas existentes.

## 1. Arquitectura actual

```text
Google Form (97 preguntas, 17 secciones)
  └─ trigger instalable onFormSubmit(e)
      └─ Apps Script
          ├─ lee respuestas por título con pregunta.includes(...)
          ├─ decide una de dos plantillas por Tercero Interesado
          ├─ crea carpeta y copia Google Doc en Drive
          ├─ sustituye placeholders e inserta/elimina cláusulas
          └─ POST JSON; si falla por HTML/WAF, POST urlencoded
              └─ Laravel POST /api/forms/contratos
                  └─ FormsIntakeController
                      └─ modo efectivo por defecto: contratos_pendientes
                          └─ conciliación manual cliente/propiedad/inquilino
                              └─ Contrato definitivo
```

### Recepción Laravel actual

- `POST /api/forms/contratos` está en `routes/api.php`, sin middleware de autenticación ni firma de webhook.
- `FormsIntakeController::storeContrato()` normaliza importes y `dias_pago`, mapea un subconjunto y, salvo configuración externa no presente en el repositorio, usa `config('services.forms_contratos.mode', 'pending')`: como `config/services.php` no define esa clave, el valor efectivo del código es `pending`.
- En modo `pending`, usa `updateOrCreate(origen=privado, external_id)` y conserva `raw_payload` y `mapped_payload` en `contratos_pendientes`. Es idempotente sólo mientras el `responseId` llegue estable.
- La conciliación requiere un usuario `admin` o `agent` (`manage-records`), propone coincidencias y crea/relaciona cliente, propiedad e inquilino antes de crear el contrato. El endpoint público no crea directamente en el modo actual.
- El modo `direct` existe pero no tiene configuración declarada ni pruebas. Hace coincidencias por nombre/domicilio, puede crear inquilino y depende de datos que pueden no resolver una propiedad obligatoria; no debe activarse como mecanismo de migración.
- El contrato definitivo conserva datos básicos y origen, pero la resolución de un pendiente privado actualmente no copia `edit_url` ni `urldoc` a `contratos`; quedan sólo en el JSON del pendiente. Esto impide asegurar el enlace del documento desde el registro definitivo.
- No hay jobs, comandos, SDK ni servicio Laravel que genere o actualice Google Docs/Drive para contratos privados. Los enlaces al Google Form siguen en las vistas de contratos y clientes.

### Google actual que debe seguir activo durante convivencia

- Trigger `onFormSubmit(e)`; la generación ocurre antes del POST a Laravel.
- Dos plantillas: sin tercero interesado y con tercero interesado.
- Carpeta destino y nomenclatura de carpeta/documento gestionadas por Apps Script.
- APIs avanzadas Google Docs v1 y Drive v3, `DriveApp`, `DocumentApp`, `UrlFetchApp`; manifiesto V8 y zona horaria `America/Mexico_City`.
- El Script devuelve en el payload el ID de respuesta como `external_id`/`response_id`, la URL de edición del Form (`editUrl`) y la URL del Doc (`urldoc`).

## 2. Arquitectura objetivo recomendada

```text
Formulario Laravel autenticado (borrador versionado)
  └─ esquema canónico + reglas condicionales en servidor
      ├─ prellenado por cliente/propiedad/inquilino existentes
      ├─ validación y vista previa documental determinista
      ├─ confirmación humana
      └─ Adaptador Google de generación (fase híbrida)
          └─ Apps Script/Google Drive conserva plantillas y crea el Doc
              └─ respuesta firmada/idempotente con IDs y URLs
                  └─ Laravel guarda versión, hash de payload, plantilla y estado
                      └─ conciliación/creación de contrato sin reasignar historia
```

Principios:

- Laravel será la fuente de verdad del borrador y de su versión publicada; Drive seguirá siendo la fuente de los documentos hasta una decisión posterior.
- El contrato canónico no debe depender de títulos de preguntas ni de coincidencias de texto. Definir claves estables de campo y un DTO/versionado de esquema.
- Generar documentos de forma asíncrona e idempotente, con un identificador de solicitud y una clave única por borrador-versión; nunca por reintento HTTP ciego.
- Mantener el endpoint actual para Forms sin modificarlo durante las fases de convivencia. El nuevo flujo debe usar un endpoint/adaptador separado y autenticado con controles explícitos, no una URL con token en query string.
- Guardar eventos/auditoría de recepción, generación, error y regeneración; una regeneración crea una nueva versión y nunca sobreescribe la referencia histórica.

## 3. Tabla de equivalencia

| Dominio Google Form | Apps Script actual | Google Docs | Laravel actual | Laravel objetivo |
|---|---|---|---|---|
| Identidad de respuesta | `responseId`, `editUrl` | No aplica | `external_id`, `raw_payload` en pendiente | `origen`, `external_id`, `schema_version`, `payload_hash`, eventos y versión inmutables |
| Arrendador / cliente | Lee título y sobrescribe datos según persona física/moral | Placeholders de arrendador y representante | Cliente básico; mapeo básico en pendiente | Entidad de parte contractual/versionada; relación al cliente existente sólo tras confirmación |
| Propiedad | Alias y domicilio por títulos | `{{inmueble_arrendado}}` | Propiedad + domicilio de contrato; conciliación manual | Selector de propiedad existente o alta explícita, con snapshot de domicilio/alias |
| Arrendatario | Datos físicos/morales; sólo subconjunto enviado | Placeholders de arrendatario y representante | `Inquilino` básico | Parte contractual completa + enlace opcional al inquilino maestro |
| Fiador / tercero | Define plantilla y cláusula de garantía | Placeholders/tabla de fiador, cláusula 13.3 | Sólo `tipo_tercero`; no persiste los detalles | Partes adicionales y garantía versionadas, con reglas explícitas |
| Vigencia e importes | Formatea fecha/monto a letra | Fechas, renta, depósito, vigencia | Fechas, montos, comisiones y `dias_pago` | Valores decimales/canónicos, texto documental derivado y validación de coherencia |
| Pago, banco y mantenimiento | Elimina/insertar tablas y cláusulas | `{{forma_pago}}`, banco, mantenimiento | No se persiste | Regla condicional + snapshot del texto/documento |
| Renovación y uso | Cambia cláusula de depósito e inserta cláusulas de uso | Cláusulas dinámicas | No se persiste; no hay vínculo de renovación | Renovación ligada a contrato previo; tipo de uso y cláusulas con versión |
| Documento generado | Copia plantilla, crea carpeta y devuelve `urldoc` | Google Doc resultante | URL en payload; se pierde al resolver privado | `documento_generado`/versión con Drive file ID, URL, plantilla, estado, timestamps y error |

## 4. Inventario de campos

### Ya soportados de forma normalizada, al menos parcialmente

| Grupo | Campos |
|---|---|
| Tipos | `tipo_solicitante`, `tipo_complementaria`, `tipo_tercero` |
| Relación básica | cliente/propiedad por conciliación; inquilino por nombre, nacionalidad, domicilio, teléfono y correo |
| Contrato | inicio, fin, comisión por renta, comisión mensual, días de pago, monto total, renta mensual, depósito, domicilio del inmueble |
| Integración | `external_id`, `raw_payload`, `mapped_payload` en pendiente; `editUrl` y `urldoc` recibidos en el payload |

### Existen con nombre o semántica diferente

| Origen | Laravel actual | Riesgo / decisión necesaria |
|---|---|---|
| `cliente_telefono` / `telefono_solicitante` | `clientes` tiene `fijo` y `celular`, no `telefono` fillable | El resolver intenta asignar `telefono`; definir si va a celular, fijo o un campo nuevo. |
| `propiedad_alias`, `propiedad_domicilio` | `Propiedad.alias`, `Propiedad.domicilio`; `Contrato.domicilio_inmueble` | Mantener snapshot contractual distinto del maestro y no sustituir alias por domicilio. |
| `fecha_inicio_contrato`, `fecha_terminacion_contrato` | `fecha_inicio`, `fecha_fin` | Mapeo claro, pero validar zona/formatos y orden. |
| `dias_pago` (“05 a 10”) | `unsignedSmallInteger` | `toInt()` elimina texto y convertiría `05 a 10` en `510`; no representa un intervalo. Requiere modelo/regla confirmada. |
| `comision_mensual` (“10%”) | decimal y accessor fracción | Normalización acepta número, pero falta definir unidad canónica (10 vs 0.10) y uso documental. |
| `editUrl` | `edit_url` | El pendiente lo guarda mapeado, pero la resolución privada no lo pasa al contrato. |

### Faltantes en el modelo canónico

- Fecha de firma, meses de vigencia como selección documental, forma de pago, banco, beneficiario, CLABE.
- Renovación, contrato anterior, decisión/estado de continuidad de depósito y versión documental.
- Uso del inmueble y sus reglas de cláusula.
- Existencia de mantenimiento, obligado a pagar y cláusula/snapshot correspondiente.
- Fiador/tercero, persona física/moral, representante, identificaciones, RFC, actas, nacionalidad, lugar/fecha de nacimiento, estado civil, ocupación, domicilios, correos y teléfonos.
- Datos completos de arrendador y arrendatario persona física/moral; hoy la mayoría ni siquiera viaja en el payload Laravel actual.
- Inmueble en garantía: decisión, título y domicilio.
- Identificador de plantilla, ID de archivo/carpeta de Drive, estado de generación, error, reintentos, versiones y regeneraciones.

### Obsoletos o sin evidencia actual

- Apps Script lee `Número de expediente`, pero el inventario visual del Form actual no lo contiene y Laravel no lo usa para contratos privados. Conservar sólo en el adaptador de compatibilidad hasta que negocio decida retirarlo.
- Helpers de Apps Script para leer etiquetas con IDs no se invocan en `onFormSubmit`; no deben diseñar el nuevo esquema sin evidencia de uso.
- La rama `direct` de `FormsIntakeController` y `ContratoRequest` no forman un flujo de alta privada web vigente; no reutilizarlos como contrato de la nueva captura sin rediseño y pruebas.

## 5. Reglas de negocio/documentales que deben migrarse

1. Selección de plantilla: sin tercero interesado usa una plantilla distinta de la plantilla con tercero.
2. Partes persona física/moral para arrendador, arrendatario y tercero: seleccionar bloque/tabla documental correcto y datos del representante cuando aplica.
3. Tercero: no hay tercero, persona física o persona moral; con/sin inmueble en garantía y cláusula adicional correspondiente.
4. Forma de pago: no especificada, efectivo o depósito/transferencia; incluir/eliminar información bancaria y texto documental equivalente.
5. Mantenimiento: con/sin cuotas y quién las cubre; el texto actual además prevé incremento de renta si paga el arrendador.
6. Uso del inmueble: Casa Habitación no inserta cláusulas adicionales; otro uso sí inserta dos cláusulas de seguros/permisos.
7. Renovación: modifica la cláusula de depósito, no sólo un indicador visual.
8. Normalización documental: importes a letra, fechas en español y nomenclatura de carpeta/documento.
9. Reglas de captura: 17 secciones y saltos condicionales; en Laravel deben convertirse en visibilidad y validación condicional de servidor, no sólo JavaScript.

## 6. Hallazgos e inconsistencias verificadas

1. **Alias crítico.** El Form dice `Alias de la propiead en Arrendamiento`; Apps Script busca `Alias de la propiedad en Arrendamiento`. La condición `includes()` no coincide y puede dejar el alias vacío, afectando nombre de carpeta/documento y la conciliación Laravel.
2. **Expediente inexistente.** Script busca `Número de expediente`; el inventario de 97 preguntas no lo encuentra. La variable no llega a Laravel ni se usa en el flujo privado.
3. **Correo/teléfono de arrendador.** El Form contiene `Correo del Arrendador` y `Teléfono del Arrendador`, pero Script no los consume directamente. `cliente_correo`/`cliente_telefono` se obtienen de las ramas privadas de Parte Solicitante/Sociedad; puede ignorar la respuesta visible inicial.
4. **Pérdida de campos.** Script usa muchos datos para Google Docs, pero al POST sólo envía un subconjunto de alrededor de 30 claves. Laravel no recibe forma de pago, banco, mantenimiento, renovación, uso, garantía ni los datos completos de partes/representantes.
5. **Regla de tercero defectuosa.** Al eliminar tablas del tercero, la rama `else if` compara `tipo_solicitante == 'Persona Moral'` en vez de `tipo_tercero`; la salida documental puede depender indebidamente del tipo del arrendador.
6. **Datos de persona moral.** Script sustituye nombre, nacionalidad, teléfono, correo y domicilio por datos de sociedad/representante; ese resultado transitorio no se modela ni se audita como versión en Laravel.
7. **URLs de Drive.** La recepción pendiente guarda `editUrl`/`urldoc`, pero al resolver un privado no se copian al contrato definitivo. No hay ID de archivo/carpeta ni historial de regeneración.
8. **Seguridad de integración.** La ruta de intake es pública y Script intenta sortear respuestas HTML/WAF con headers tipo navegador y fallback urlencoded. No existe firma, secreto configurado, allowlist o contrato versionado en el repositorio.
9. **Hoja de respuestas.** El respaldo documenta 18,000 columnas. No se investigó ni se modificará; afecta rendimiento/operación de Google, pero no debe bloquear el diseño del DTO Laravel ni provocar una limpieza durante esta migración.
10. **Configuración ambigua.** `services.forms_contratos` no está declarado en `config/services.php`; validar la configuración real del entorno antes de depender de `direct` o de un feature flag.

## 7. Estrategia por fases

### Fase 0 — auditoría y contrato de datos

- Aprobar un diccionario canónico de campos, catálogos, obligatoriedad y reglas por rama; versionarlo en el repositorio.
- Capturar ejemplos anonimizados de cada ruta de negocio y un conjunto de documentos de referencia aprobados por negocio/legal.
- Definir el identificador externo estable, la identidad del emisor, autorización, reintentos y respuesta esperada del adaptador Google.
- Corregir sólo después de validar en sandbox los hallazgos de alias, contacto y expediente; no cambiar títulos productivos ahora.

**Salida:** matriz firmada de campo → DTO → placeholder, y fixtures de regresión.

### Fase 1 — convivencia segura

- Conservar Google Form → Apps Script → endpoint actual sin cambios funcionales.
- Añadir en una futura entrega un modelo de borrador/versiones y captura interna detrás de feature flag, sin crear contrato definitivo automáticamente.
- Guardar snapshot canónico, usuario, versión, origen y comparación contra el payload recibido de Google.
- La conciliación actual puede reutilizarse, pero debe recibir un origen distinguible y conservar URLs/IDs Google.

**Criterio de aceptación:** crear/editar borrador Laravel no altera Google; la misma operación puede seguir completándose por Form y ambos resultados son comparables.

### Fase 2 — captura desde Laravel

- Reemplazar gradualmente el enlace al Form para roles autorizados por un wizard Laravel con prellenado de cliente, propiedad e inquilino.
- Aplicar las reglas de Persona Física/Moral, tercero, garantía, pago, mantenimiento, renovación y uso tanto en UI como en Form Request/servicio de dominio.
- Permitir guardar borrador, retomar, validar y ver un resumen de datos/documento antes de generar.
- No inferir coincidencias ni crear entidades maestras sin confirmación explícita del usuario autorizado.

**Criterio de aceptación:** cada rama condicional produce un DTO canónico validado y una vista previa consistente; Google Forms sigue disponible como fallback.

### Fase 3 — generación documental desde Laravel

- Implementar un adaptador de generación que invoque el mecanismo Google aprobado (Apps Script existente o endpoint nuevo) con autenticación fuerte, payload versionado e idempotency key.
- Mantener plantillas y Drive; el adaptador devuelve file ID, folder ID, URL, plantilla, hash y estado.
- Ejecutar en cola cuando exista infraestructura aprobada; guardar errores/reintentos sin duplicar carpetas ni documentos.
- Comparar en paralelo los documentos Laravel/Google con los casos aprobados antes de declarar equivalencia.

**Criterio de aceptación:** regenerar crea una versión nueva rastreable; un reintento no duplica el documento de la misma versión.

### Fase 4 — retiro controlado de Google Forms/Apps Script

- Retirar el enlace y trigger sólo después de un periodo acordado sin discrepancias, conciliación de pendientes y aceptación legal/operativa.
- Mantener acceso de sólo lectura a documentos/URLs históricos y un runbook de reactivación temporal.
- No borrar hojas, Forms, Script ni documentos como parte del corte; archivar y documentar propietarios/permisos.

**Criterio de aceptación:** Laravel crea y regenera todos los casos de la matriz, existen respaldos verificables y un rollback operativo no pierde documentos.

## 8. Pruebas necesarias

| Fase | Pruebas |
|---|---|
| 0 | Fixtures anonimizados de las 97 preguntas, typo de alias, expediente ausente, contactos duplicados y payload JSON/urlencoded. |
| 1 | Idempotencia por `external_id`; actualización del mismo pendiente; permisos de conciliación; no crear contratos/datos por sólo guardar borrador. |
| 2 | Matriz PF/PM para las tres partes; sin tercero; con/sin garantía; tres formas de pago; mantenimiento sí/no y obligado; Casa Habitación/industrial/comercial; renovación sí/no; fechas/importes/rango de pago. |
| 3 | Selección de ambas plantillas; todos los placeholders; cláusulas insertadas/eliminadas; fecha y monto a letra; nombre carpeta/archivo; fallo/reintento; idempotencia; versionado y permisos Drive. |
| 4 | Ejecución paralela con casos de producción anonimizados; comparación humana/legal del contenido; rollback a Form; accesibilidad de URLs históricas. |

Además, antes de retirar Forms deben existir pruebas feature del endpoint actual: autorización/firma futura, payload completo, `external_id` duplicado, error de validación, creación de pendiente y preservación de `editUrl`/`urldoc` al contrato resuelto. Hoy sólo hay una prueba que verifica el registro de la ruta, no el comportamiento del intake privado.

## 9. Riesgos y rollback

| Riesgo | Mitigación | Rollback |
|---|---|---|
| Documento jurídicamente distinto | Fixtures aprobados, comparación por rama y revisión humana/legal | Volver a generar exclusivamente por Apps Script; conservar borrador Laravel sin publicar. |
| Duplicación de contratos/documentos | Claves idempotentes por versión, transacciones y estados explícitos | Marcar solicitud fallida/cancelada; no borrar historia automáticamente. |
| Matching incorrecto de cliente/propiedad/inquilino | Confirmación humana y sugerencias con evidencia | Dejar como pendiente; nunca reasignar movimientos/documentos históricos. |
| Falla o permisos Drive | Adaptador con error observable y reintento controlado | Google Form/Script continúa disponible mientras dure convivencia. |
| Exposición o falsificación de intake | Autenticación firmada/allowlist, rate limit, auditoría y secretos sólo en entorno | Desactivar sólo el nuevo endpoint/adaptador, no el webhook heredado hasta tener alternativa. |
| Cambio de plantilla | Registrar ID/version/hash de plantilla y ejecutar suite de documentos | Seleccionar la versión anterior de plantilla para nuevas generaciones. |
| Datos incompletos | Validación de servidor por rama y campos de origen | Mantener borrador; no crear contrato definitivo. |

## 10. Archivos Laravel previsiblemente involucrados en una implementación futura

- `routes/web.php`, `routes/api.php` y una configuración nueva de integración en `config/services.php` / entorno.
- `app/Http/Controllers/Api/FormsIntakeController.php` para compatibilidad explícita, autenticada e idempotente; no retirar antes de la fase 4.
- Nuevos Form Requests, DTOs/servicios de dominio y controlador/vistas del wizard de contrato.
- `app/Models/Contrato.php`, `ContratoPendiente.php`, `Cliente.php`, `Inquilino.php`, `Propiedad.php` y migraciones aditivas/reversibles para borradores, partes, versiones de documento y vínculo de renovación.
- `app/Http/Controllers/ContratoPendienteController.php`, `ContratoController.php`, `resources/views/contratos/index.blade.php`, `resources/views/clientes/show.blade.php`, las vistas de pendientes y el detalle de contrato.
- Nuevos jobs/eventos de generación y pruebas feature/unit de intake, wizard, equivalencia y adaptador Google.

No usar `migrate:fresh`, `db:wipe` ni una migración destructiva. Cualquier migración futura debe ser aditiva, reversible y desplegada antes de activar la nueva ruta.

## 11. Decisiones humanas y bloqueos

1. ¿Qué datos deben ser fuente de verdad: el maestro Cliente/Propiedad/Inquilino o el snapshot firmado del contrato cuando difieren?
2. ¿Cómo se representa realmente `Días de pago de la renta`: un día, un rango, varios días o texto contractual libre?
3. ¿`Comisión por renta` es importe fijo y `Comisión mensual` porcentaje? ¿Cuál es la unidad canónica y quién la autoriza?
4. ¿Cómo debe asociarse una renovación con el contrato anterior, depósito, saldos, movimientos y documento previo?
5. ¿Qué campos jurídicos deben persistir estructurados y cuáles sólo como snapshot de versión documental?
6. ¿Qué plantilla corresponde a cada combinación de tercero, persona física/moral y uso? Confirmar IDs, propietarios, permisos y versión de las plantillas.
7. ¿Quién puede crear, editar borradores, confirmar, generar, regenerar y ver URLs/documentos? ¿Las URLs de Drive pueden mostrarse a todos los usuarios autenticados?
8. ¿Cuál es el mecanismo de autenticación permitido entre Laravel y Google durante la fase híbrida (cuenta de servicio, OAuth, firma HMAC, Web App privado)?
9. ¿Debe el endpoint histórico seguir público durante convivencia o puede protegerse coordinadamente con Apps Script? No cambiarlo sin esa coordinación.
10. Confirmar si los campos visibles `Correo/Teléfono del Arrendador` deben prevalecer sobre los campos privados, y si el typo de alias debe preservarse en una capa de compatibilidad.
11. Confirmar si la hoja de 18,000 columnas es necesaria para operación/histórico; este plan no propone alterarla.

## Resultado de esta auditoría

Laravel ya tiene una base útil para recepción idempotente de pendientes y conciliación humana, pero no captura ni persiste la mayor parte del contrato documental y no genera Google Docs. La migración debe empezar por un esquema canónico/borradores y pruebas de equivalencia, no por desactivar Forms ni por activar la ruta `direct` existente.
