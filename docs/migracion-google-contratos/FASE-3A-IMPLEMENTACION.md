# Fase 3A — Adapter documental y preparación Google

## Alcance aplicado

`ContractDocumentPayloadBuilder` convierte exclusivamente el `canonical_payload` inmutable de una `ContractDraftVersion` en una representación documental determinista. No usa APIs, credenciales, archivos, colas ni URLs de Google.

La salida contiene `template_key`, `template_id` nullable, `destination_folder_id` nullable, `snapshot_hash`, placeholders, operaciones documentales declarativas y nombres propuestos. Los valores futuros se reservan en `config/services.php` y `.env.example`; no hay IDs reales en el repositorio.

## Selección de plantilla y estado

- `guarantor.type = none` selecciona `lease_without_guarantor`.
- `guarantor.type = fisica|moral` selecciona `lease_with_guarantor`.
- La selección no depende de `lessor` ni replica la condición defectuosa del Apps Script basada en `tipo_solicitante`.
- `blocked`: faltan datos mínimos para una representación equivalente (partes, inmueble, uso, vigencia, renta mensual o fecha de firma).
- `requires_review`: el snapshot está completo, pero el uso tiene múltiples valores, `other`, o una combinación sin cláusula documental aprobada.
- `ready`: sólo para la representación técnicamente determinable; no significa publicación jurídica ni contrato definitivo.

## Mapping y formateadores

El adapter centraliza los placeholders hallados en el mapeo/documento legacy: partes PF/PM, RFC, contactos, domicilio, datos de representantes, inmueble, fechas, vigencia, renta, depósito, regla `rent_due_rule.raw_text`, pago/banco y marcadores de mantenimiento, garantía, uso y renovación.

Los formateadores son locales y testeables: fecha larga en español, moneda formateada, cantidad a letra con centavos y texto de pago. `dias_pago` se toma literalmente de `raw_text`; por ejemplo, `05 a 10` no se convierte a número. El domicilio PF del arrendatario sólo sale del snapshot: nunca se infiere del inmueble.

## Operaciones documentales intermedias

`document_operations` expresa `replace_placeholders`, selección/eliminación de bloques de partes, bloque bancario, inserción/eliminación de garantía, mantenimiento, renovación y uso. Es una representación intermedia para una futura capa Google: todavía no ejecuta `replaceText`, no elimina tablas ni inserta párrafos en Drive.

Para renovaciones se conserva, cuando exista, el snapshot de cláusula legacy. No se promueve `deposit_treatment` a catálogo contractual: sigue pendiente de definición humana. El uso residencial retira el marcador legacy; un único uso comercial/industrial se expresa como cláusula pendiente de renderer; multiuso no selecciona una cláusula y queda en revisión.

## Solicitud documental local y preview

Rutas protegidas por `auth` y `can:manage-records`:

- `GET /contratos/borradores/{draft}/previsualizacion-documental`
- `POST /contratos/borradores/{draft}/previsualizacion-documental/solicitud`

La pantalla muestra estado, plantilla, operaciones y placeholders resueltos, sin `raw_legacy_payload` ni JSON bruto. Si el estado es `ready`, el POST crea una `ContractDocumentVersion` con `not_requested`, versión documental, hash del snapshot, llave idempotente UUID y actor. Es un registro de intención local: no genera documentos, no encola trabajos y no contacta Google.

## Diferencias deliberadas frente a legacy

- El alias Laravel se lee de `leased_property.alias`; el typo del Form no participa.
- RFC PF se conserva desde V1.
- `guarantor.type` gobierna plantilla y bloque de tercero, incluso para tercero PM.
- Los valores bancarios sólo se exponen cuando `payment.method = bank_transfer`.
- La garantía se toma de `guarantee_property`, no de variables legacy con ambigüedad.
- La implementación de texto monetario reproduce el formato funcional legado (`… pesos NN/100 MN`) de forma local.

## Fixtures y pruebas

`ContractDocumentPayloadBuilderTest` usa snapshots anonimizados para PF, PM/tercero, RFC PF, transferencia, efectivo, mantenimiento, renovación, garantía, uso residencial, multiuso, `05 a 10`, ausencia de domicilio de arrendatario y datos críticos faltantes. `ContractDocumentPreviewTest` cubre autorización, preview, solicitud local `not_requested` e imposibilidad de solicitar cuando el estado está bloqueado.

La siguiente validación manual, sin Google, consiste en abrir la previsualización de un borrador completo para: PF/PF sin tercero; PM/PM con tercero PF; PF/PM con tercero PM y garantía; transferencia; efectivo; mantenimiento por ambas partes; renovación; uso residencial; industrial/comercial y multiuso.

## Pendiente para la fase Google real

Se requiere confirmar IDs por entorno, autenticación Laravel↔Google, permisos de regeneración, los marcadores exactos de cada plantilla activa y la semántica jurídica del multiuso. La futura capa renderer debe consumir estas operaciones sin registrar payloads completos ni PII. Ningún endpoint legacy, Apps Script, Google Form, maestro ni contrato definitivo fue modificado en esta fase.

## Correcciones post-auditoría

La solicitud documental ahora recibe la versión mostrada (`expected_draft_version_id`) y una llave UUID de idempotencia emitida por la previsualización. El servicio bloquea el borrador, compara la versión actual dentro de la transacción y rechaza con HTTP 409 si cambió; por tanto no sustituye silenciosamente el snapshot N por N+1. Un reenvío con la misma llave reutiliza la misma `ContractDocumentVersion` `not_requested`.

La validación documental es independiente de publicación jurídica. `blocked` aplica cuando falta un dato que el renderer legacy necesita: partes y representantes de la rama seleccionada, domicilio personal del arrendatario, inmueble, fecha, vigencia, regla literal de pago, renta total/mensual, depósito, banco/beneficiario/CLABE de transferencia, pagador de mantenimiento o dirección/título de garantía. `requires_review` queda para `other` o multiuso; `ready` exige que no falte ninguna entrada técnica y que exista una regla documental conocida.

El importe mostrado conserva el valor canónico bruto V1: no existe aún un `display_value` histórico separado. Para convertir a letra se acepta compatibilidad local con `$` y comas, igual que el Script. Las fechas reproducen `DD DE MES DEL YYYY` y las letras reproducen la capitalización observable del Script. Esta diferencia estructural de valor bruto vs. presentación queda documentada para una futura decisión de esquema, sin alterar `contract_data_v1`.

## MARCADORES REQUERIDOS POR EL RENDERER

Los elementos siguientes se verificaron primero contra el código legacy y después físicamente, en modo lectura, contra las plantillas activas. La aplicabilidad final por plantilla se define en la sección de correcciones posterior.

| Clase | Marcador/identificador | Uso legacy | Acción declarada |
|---|---|---|---|
| Texto | `{{arrendador_*}}`, `{{arrendatario_*}}`, `{{fiador_*}}` | Sustitución de datos PF/PM | `replace_placeholders` |
| Texto | `{{representante_arrendador_*}}`, `{{representante_arrendatario_*}}`, `{{representante_fiador_*}}` | Sustitución de representantes | `replace_placeholders` |
| Texto | `{{monto}}`, `{{monto_letra}}`, `{{monto_mensualidad}}`, `{{monto_mensualidad_letra}}`, `{{dia_pago}}`, `{{vigencia}}`, `{{parcialidades}}`, `{{fecha_inicial}}`, `{{fecha_final}}`, `{{fecha_firma}}`, `{{tipo}}`, `{{forma_pago}}` | Valores de contrato | `replace_placeholders` / `replace_marker` |
| Tabla | `__T1__`, `__T2__`, `__T3__` | Arrendador PF/PM/representante | Eliminar tabla o marcador según rama |
| Tabla | `__T4__`, `__T5__`, `__T6__` | Arrendatario PF/PM/representante | Eliminar tabla o marcador según rama |
| Tabla | `__T7__`, `__T8__`, `__T9__` | Fiador PF/PM/representante | Eliminar por `guarantor.type` |
| Párrafo | `__I1__` a `__I6__` | Declaraciones PF de partes | Limpiar marcadores; renderer 3B verificará párrafos literales PM |
| Cláusula | `{{clausula_deposito}}` | Cláusula 2.1 normal o renovación | Reemplazar marcador y eliminarlo |
| Cláusula | `{{mantenimiento_quien}}` | Cláusula 1.4BIS | Reemplazar o eliminar marcador |
| Cláusula | `{{uso_inmueble}}` | Cláusulas 4.7/4.8 no residencial | Reemplazar, eliminar residencial o revisión |
| Cláusula | `{{si_inmueble}}` | Cláusula 13.3 de garantía, sólo con tercero | Reemplazar o eliminar marcador |
| Tabla | `INSTITUCIÓN BANCARIA` | Tabla de banco | Preservar transferencia; eliminar efectivo/no especificado |
| Firma | `{{arrendador_representante}}`, `{{arrendatario_representante}}`, `{{fiador_representante}}` | Limpieza PF del Script | Operación literal de limpieza; el Script también usa los placeholders `{{representante_*}}`, inconsistencia que debe verificarse en plantilla |

La salida incluye contenido literal de las cláusulas legacy de depósito, mantenimiento, garantía y uso no residencial. No inserta esos contenidos todavía; 3B sólo deberá ejecutar esta representación contra una plantilla previamente verificada.

## Fixtures / golden y pruebas post-auditoría

Los snapshots anonimizados cubren PF/PF sin tercero, PM/PM, tercero PF, tercero PM con garantía, transferencia, efectivo/no especificado, mantenimiento por ambas partes, renovación, industrial/comercial, multiuso/`other` y `dias_pago="05 a 10"`. El golden base reside en `tests/Fixtures/contract-document/ready-without-guarantor.json`; los casos ramificados se prueban como proyecciones deterministas de operaciones y placeholders para evitar fijar PII o texto documental completo duplicado.

## Correcciones tras verificación física de plantillas

**Gate físico: APROBADO CON CORRECCIONES APLICADAS.** La lectura autenticada y sin escritura de las dos plantillas activas confirmó los marcadores del inventario siguiente. No se modificaron los documentos de Drive.

`ContractDocumentPayloadBuilder::templateMarkerInventory()` es la fuente central del plan físico por `template_key`. Devuelve `required_markers`, `optional_markers` y `not_applicable_markers`; el renderer 3B deberá consumir exclusivamente operaciones y reemplazos aplicables a ese inventario.

| Plantilla | Required físicos confirmados | No aplican |
|---|---|---|
| `lease_without_guarantor` | `__T1__`–`__T6__`, `__I1__`–`__I4__`, datos de arrendador/arrendatario y sus representantes, inmueble, importes, pago, `{{clausula_deposito}}`, `{{mantenimiento_quien}}`, `{{uso_inmueble}}`, tabla `INSTITUCIÓN BANCARIA` y firmas de ambas partes | `__T7__`–`__T9__`, `__I5__`–`__I6__`, todos los `{{fiador_*}}`, `{{representante_fiador_*}}`, `{{fiador_representante}}` y `{{si_inmueble}}` |
| `lease_with_guarantor` | Lo anterior, más `__T7__`–`__T9__`, `__I5__`–`__I6__`, datos/representante/firma del fiador y `{{si_inmueble}}` | Ningún marcador de tercero |

`{{garantia}}` y `{{garantia_monto}}` aparecen en el Apps Script legacy, pero **no existen físicamente en ninguna plantilla activa**. Se retiraron de los reemplazos y marcadores requeridos; se conservan sólo como conocimiento legacy/no aplicable. Esto no elimina `amounts.security_deposit`: el importe sigue siendo requisito documental porque alimenta literalmente `{{clausula_deposito}}`.

También se eliminó la operación abstracta `lease_with_guarantor`: nunca fue un marcador de Google Docs y ya no puede llegar al renderer. Para un borrador sin tercero, el adapter no emite operaciones ni placeholders de fiador o garantía; no delega al renderer la decisión de ignorarlos.

Las familias reales permanecen separadas:

- Datos de representante: `{{representante_arrendador}}`, `{{representante_arrendatario}}`, `{{representante_fiador}}` y sus subcampos.
- Firmas: `{{arrendador_representante}}`, `{{arrendatario_representante}}`, `{{fiador_representante}}`.

Para persona moral se resuelve la firma con el nombre del representante. Para persona física las operaciones de limpieza legacy remueven la frase de representación; no se confunden las dos familias.

La validación `ready`/`blocked` ya usa el inventario seleccionado para no exigir `{{si_inmueble}}` ni datos de garantía cuando la plantilla sin tercero no los contiene. En cambio, `{{clausula_deposito}}` sigue requerido para ambas plantillas, y depósito en garantía sigue siendo un dato indispensable para construir su cláusula literal.
