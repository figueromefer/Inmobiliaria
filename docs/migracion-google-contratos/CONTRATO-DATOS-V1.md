# Contrato de datos canónico V1 — contratos de arrendamiento

## Propósito, alcance y convenciones

Este es el contrato técnico de datos para la futura captura Laravel y su generación documental híbrida en Google Drive. No es una migración ni una decisión jurídica. Se verificó contra `PLAN-MIGRACION-LARAVEL.md`, `appsscript/Código.js`, los inventarios del Form y el código Laravel actual.

- Las claves de este documento son estables, en `snake_case`, y no dependen de títulos de Google Forms.
- `null` significa «sin valor aplicable o aún no capturado»; nunca se sustituye por texto de presentación como `No aplica`.
- `S` significa que debe persistirse estructurado; `D` que debe conservarse también en el snapshot de la versión documental; `L` que hoy llega a Laravel por el payload de Apps Script.
- Las etiquetas visibles en español viven en catálogos/presentación, no en las claves almacenadas.
- El DTO raíz propuesto se denomina `contract_data_v1`. Los objetos de parte usan roles `lessor`, `lessee` y `guarantor`.

## 1. DTO raíz canónico

```json
{
  "schema_version": "contract_data_v1",
  "metadata": {},
  "lessor": { "person": {}, "representative": null },
  "lessee": { "person": {}, "representative": null },
  "guarantor": {
    "type": "none",
    "person": null,
    "representative": null
  },
  "leased_property": {},
  "guarantee_property": {
    "exists": "no",
    "address": null,
    "title_deed": null
  },
  "term": {},
  "amounts": {},
  "payment": {},
  "maintenance": {},
  "renewal": {},
  "document_generation": {},
  "audit": {}
}
```

El DTO no elimina la información maestra existente: `cliente_id`, `propiedad_id` e `inquilino_id` son referencias opcionales de conciliación. Los nombres, domicilios y demás datos que aparecen en un documento se conservan como snapshot de la versión, aun si después cambia el maestro.

## 2. Catálogos canónicos

| Catálogo | Claves internas | Etiqueta actual/visible |
|---|---|---|
| `person_type` | `fisica`, `moral` | Persona Física, Persona Moral |
| `guarantor_type` | `none`, `fisica`, `moral` | No hay Tercero Interesado, Persona Física, Persona Moral |
| `payment_method` | `unspecified`, `cash`, `bank_transfer` | No se especifica, Efectivo, Depósito o Transferencia |
| `property_use` | `residential`, `industrial`, `commercial`, `other` | Casa Habitación, Industrial, Comercial, Otro (si se autoriza) |
| `maintenance_exists` | `yes`, `no` | Sí, No |
| `maintenance_payer` | `lessor`, `lessee` | El ARRENDADOR, EL ARRENDATARIO |
| `renewal_status` | `yes`, `no` | Sí, No |
| `guarantee_property_exists` | `yes`, `no` | Sí, No |
| `generation_status` | `not_requested`, `queued`, `processing`, `generated`, `failed`, `cancelled` | Estado interno; no reutilizar texto de UI |
| `draft_status` | `draft`, `validated`, `awaiting_confirmation`, `published`, `superseded`, `cancelled` | Estado interno |

Durante compatibilidad, `property_use_codes: string[]` es la única fuente de verdad para el uso del inmueble: el Form actual permite potencialmente varias selecciones. No se deriva ni persiste un `primary_property_use`. Mientras no se defina la semántica jurídica/documental del multiuso, el generador debe conservar el arreglo y aplicar únicamente la plantilla/cláusulas legacy que correspondan a cada código conocido; si coexisten códigos con cláusulas incompatibles o sin regla aprobada, debe detener la generación para revisión humana, no elegir una cláusula dominante.

## 3. Esquema canónico completo

### 3.1 Metadatos del contrato

| Clave Laravel | Descripción / tipo | Nullable | Obligatorio condicional | Google Form → Apps Script | Documento | L | S/D |
|---|---|:---:|---|---|---|:---:|---|
| `metadata.contract_kind` | enum; inicialmente `private_lease` | No | Siempre para V1 | Implícito | Nombre documento «Contrato de arrendamiento» | No | S/D |
| `metadata.contract_reference` | string, identificador humano no jurídico | Sí | Sólo si negocio lo define | `Número de expediente` → `numero_expediente` | Ninguno | No | S/D |
| `metadata.contract_date` | date, fecha de firma | Sí | Definir si obligatoria antes de publicar | Fecha de firma → `fecha_firma` | `{{fecha_firma}}` | No | S/D |
| `metadata.source` | enum `laravel`, `google_form`, `legacy_import` | No | Siempre | `responseId`/origen Script | No aplica | Parcial | S/D |
| `metadata.external_id` | string, ID estable del emisor | Sí | Requerido para adapter legacy | `responseId` → `external_id`, `response_id` | No aplica | Sí | S/D |
| `metadata.google_form_edit_url` | URL | Sí | Sólo fuente Google | `editUrl` | No aplica | Sí | S/D |
| `metadata.cliente_id` | FK `clientes.pk_cliente` | Sí | Al publicar un contrato definitivo | Conciliación Laravel | No aplica | No | S |
| `metadata.propiedad_id` | FK `propiedades.pk_propiedad` | Sí | Al publicar un contrato definitivo | Conciliación Laravel | No aplica | No | S |
| `metadata.inquilino_id` | FK `inquilinos.id` | Sí | Al publicar si se vincula maestro | Conciliación Laravel | No aplica | No | S |

### 3.2 Arrendador

La estructura `lessor.person` reutiliza el mismo diccionario de parte que arrendatario y fiador. Para persona física, `full_name` es el valor documental; para moral, `legal_name` es el valor documental y `full_name` puede conservarse como alias de presentación si negocio lo requiere.

| Clave Laravel | Descripción / tipo | N | Condición | Google Form → Apps Script | Documento | L | S/D |
|---|---|:---:|---|---|---|:---:|---|
| `lessor.person.person_type` | enum `fisica|moral` | No | Siempre | Parte Solicitante es → `tipo_solicitante` | Selección de tablas `__T1__..__T3__` | Sí | S/D |
| `lessor.person.full_name` | string | Sí | PF: sí | Nombre/Razón Social → `cliente_nombre`, `nombre_solicitante` | `{{arrendador}}` | Sí | S/D |
| `lessor.person.legal_name` | string | Sí | PM: sí | Mismo campo actual → `razon_social_solicitante` | `{{arrendador}}` | No como clave separada | S/D |
| `lessor.person.rfc` | string | Sí | PM: sí; PF: decisión humana | RFC Arrendador/Sociedad → `cliente_rfc`, `rfc_sociedad_solicitante` | `{{arrendador_rfc}}` | Sí, ambiguo | S/D |
| `lessor.person.nationality` | string | Sí | PF: sí; PM: sólo si se documenta representante | Nacionalidad solicitante/representante | `{{arrendador_nacionalidad}}` | No | S/D |
| `lessor.person.birth_place` | string | Sí | PF: sí | Lugar nacimiento solicitante | `{{arrendador_lugar_nacimiento}}` | No | S/D |
| `lessor.person.birth_date` | date | Sí | PF: sí | Fecha nacimiento solicitante | `{{arrendador_fecha_nacimiento}}` | No | S/D |
| `lessor.person.marital_status` | string/catalogable | Sí | PF: sí | Estado civil solicitante | `{{arrendador_estado_civil}}` | No | S/D |
| `lessor.person.occupation` | string | Sí | PF: sí | Ocupación solicitante | `{{arrendador_ocupacion}}` | No | S/D |
| `lessor.person.address` | text | Sí | PF/PM: sí según rama | Domicilio solicitante/sociedad | `{{arrendador_domicilio}}` | Sí, fuente ambigua | S/D |
| `lessor.person.identification_type` | string/catalogable | Sí | PF: sí; PM: representante | Identificación solicitante | `{{arrendador_identificacion}}` | No | S/D |
| `lessor.person.phone` | string | Sí | PF/PM: sí según rama | Teléfono solicitante/sociedad | `{{arrendador_telefono}}` | Sí, fuente ambigua | S/D |
| `lessor.person.email` | string/email | Sí | PF/PM: sí según rama | Correo solicitante/sociedad | `{{arrendador_correo}}` | Sí, fuente ambigua | S/D |
| `lessor.person.incorporation_deed` | text | Sí | PM: sí | Acta constitutiva sociedad solicitante | `{{arrendador_acta_constitutiva}}` | No | S/D |

Los campos visibles iniciales `Correo del Arrendador` y `Teléfono del Arrendador` se reciben como `lessor.person.contact_email_entered` y `lessor.person.contact_phone_entered` en el adaptador legacy, no como sustitutos silenciosos de `email`/`phone`; la precedencia se resolverá por decisión humana.

### 3.3 Representante de arrendador

| Clave Laravel | Descripción / tipo | N | Condición | Google Form → Apps Script | Documento | L | S/D |
|---|---|:---:|---|---|---|:---:|---|
| `lessor.representative.full_name` | string | Sí | PM: sí | Representante solicitante → `representante_solicitante` | `{{representante_arrendador}}` | No | S/D |
| `lessor.representative.nationality` | string | Sí | PM: sí | Nacionalidad representante | `{{representante_arrendador_nacionalidad}}` | No | S/D |
| `lessor.representative.birth_place` | string | Sí | PM: sí | Lugar nacimiento representante | Parte de `{{representante_arrendador_lugar_fecha_nacimiento}}` | No | S/D |
| `lessor.representative.birth_date` | date | Sí | PM: sí | Fecha nacimiento representante | Mismo placeholder combinado | No | S/D |
| `lessor.representative.occupation` | string | Sí | PM: sí | Ocupación representante | `{{representante_arrendador_ocupacion}}` | No | S/D |
| `lessor.representative.address` | text | Sí | PM: sí | Domicilio sociedad/representante | Se reutiliza domicilio arrendador | No | S/D |
| `lessor.representative.identification_type` | string | Sí | PM: sí | Identificación representante | `{{representante_arrendador_identificacion}}` | No | S/D |
| `lessor.representative.authority_deed` | text | Sí | PM: sí | Acta facultades representante | `{{representante_arrendador_acta_facultades}}` | No | S/D |

### 3.4 Arrendatario y representante

| Clave Laravel | Descripción / tipo | N | Condición | Google Form → Apps Script | Documento | L | S/D |
|---|---|:---:|---|---|---|:---:|---|
| `lessee.person.person_type` | enum | No | Siempre | Parte Complementaria es → `tipo_complementaria` | `__T4__..__T6__` | Sí | S/D |
| `lessee.person.full_name` | string | Sí | PF: sí | Nombre Complementaria → `nombre_complementaria` | `{{arrendatario}}` | Sí | S/D |
| `lessee.person.legal_name` | string | Sí | PM: sí | Razón Social Complementaria → `razon_social_complementaria` | `{{arrendatario}}` | No | S/D |
| `lessee.person.rfc` | string | Sí | PM: sí; PF: decisión | RFC Sociedad Complementaria | `{{arrendatario_rfc}}` | No | S/D |
| `lessee.person.nationality` | string | Sí | PF: sí; PM: valor documental a confirmar | Nacionalidad complementaria/representante | `{{arrendatario_nacionalidad}}` | Sí sólo PF | S/D |
| `lessee.person.birth_place` | string | Sí | PF: sí | Lugar nacimiento Complementaria | `{{arrendatario_lugar_nacimiento}}` | No | S/D |
| `lessee.person.birth_date` | date | Sí | PF: sí | Fecha nacimiento Complementaria | `{{arrendatario_fecha_nacimiento}}` | No | S/D |
| `lessee.person.marital_status` | string | Sí | PF: sí | Estado civil Complementaria | `{{arrendatario_estado_civil}}` | No | S/D |
| `lessee.person.occupation` | string | Sí | PF: sí | Ocupación Complementaria | `{{arrendatario_ocupacion}}` | No | S/D |
| `lessee.person.address` | text | Sí | PF/PM: sí según rama | Domicilio complementaria/sociedad | `{{arrendatario_domicilio}}` | Sí sólo PF | S/D |
| `lessee.person.identification_type` | string | Sí | PF: sí | Identificación Complementaria | `{{arrendatario_identificacion}}` | No | S/D |
| `lessee.person.phone` | string | Sí | PF/PM: sí según rama | Teléfono Complementaria/Sociedad | `{{arrendatario_telefono}}` | Sí sólo PF | S/D |
| `lessee.person.email` | string/email | Sí | PF/PM: sí según rama | Correo Complementaria/Sociedad | `{{arrendatario_correo}}` | Sí sólo PF | S/D |
| `lessee.person.incorporation_deed` | text | Sí | PM: sí | Acta constitutiva Complementaria | `{{arrendatario_acta_constitutiva}}` | No | S/D |
| `lessee.representative.*` | mismo conjunto de ocho campos del representante de arrendador | Sí | PM: sí | Variables `*_representante_complementaria` | `{{representante_arrendatario_*}}` | No | S/D |

`Inquilino` actual sólo reutiliza nombre, nacionalidad, domicilio, teléfono y correo. Los demás datos de V1 son snapshot contractual, no campos que deban sobrescribir automáticamente el maestro.

### 3.5 Tercero/fiador y representante

| Clave Laravel | Descripción / tipo | N | Condición | Google Form → Apps Script | Documento | L | S/D |
|---|---|:---:|---|---|---|:---:|---|
| `guarantor.type` | enum `none|fisica|moral` | No | Siempre; el objeto `guarantor` siempre existe | Tercero interesado es → `tipo_tercero` | Selecciona plantilla | Sí | S/D |
| `guarantor.person.*` | mismo conjunto de parte: tipo, nombre/razón, RFC, nacionalidad, nacimiento, estado civil, ocupación, domicilio, identificación, teléfono, correo, acta | Sí | Sólo PF/PM | Preguntas Tercero/ Sociedad Tercero → variables `*_tercero` | `{{fiador_*}}` | No excepto tipo | S/D |
| `guarantor.representative.*` | mismo conjunto de representante: nombre, nacionalidad, nacimiento, ocupación, domicilio, identificación, facultades | Sí | Sólo PM | Variables `*_representante_tercero` | `{{representante_fiador_*}}` | No | S/D |

`guarantor` siempre tiene la forma `{type, person, representative}`. Con `type=none`, `person=null`, `representative=null` y `guarantee_property.exists=no`; no se guardan valores de ramas no elegidas. Con `type=fisica`, `person.person_type=fisica` y `representative=null`; con `type=moral`, `person.person_type=moral` y `representative` contiene el representante. Esto elimina la ambigüedad entre ausencia de objeto y ausencia de tercero.

### 3.6 Inmueble arrendado e inmueble en garantía

| Clave Laravel | Descripción / tipo | N | Condición | Google Form → Apps Script | Documento | L | S/D |
|---|---|:---:|---|---|---|:---:|---|
| `leased_property.alias` | string | Sí | Obligatorio al crear propiedad nueva; opcional si selecciona existente | Alias con typo → `propiedad_alias` | Nombre carpeta/documento | Sí, puede vacío | S/D |
| `leased_property.address` | text | No | Siempre para publicación documental | Domicilio arrendamiento → `domicilio_inmueble_arrendamiento`, `propiedad_domicilio` | `{{inmueble_arrendado}}` | Sí | S/D |
| `leased_property.property_use_codes` | array enum | No | Siempre; validar catálogo | Uso inmueble → `uso_inmueble` | `{{tipo}}`, `{{uso_inmueble}}` | No | S/D |
| `leased_property.master_property_id` | FK | Sí | Al publicar contrato con propiedad existente/nueva | Conciliación Laravel | No aplica | No | S |
| `guarantee_property.exists` | enum `yes|no` | No | Si tercero PF/PM; para `none` se conserva `no` | Habrá inmueble garantía → `habra_inmueble` | Activa `{{si_inmueble}}`/cláusula 13.3 | No | S/D |
| `guarantee_property.address` | text | Sí | Sólo `exists=yes` | Domicilio inmueble garantía | Cláusula 13.3 | No | S/D |
| `guarantee_property.title_deed` | text | Sí | Sólo `exists=yes` | Título propiedad garantía | Cláusula 13.3 | No | S/D |

### 3.7 Vigencia e importes

| Clave Laravel | Descripción / tipo | N | Condición | Google Form → Apps Script | Documento | L | S/D |
|---|---|:---:|---|---|---|:---:|---|
| `term.start_date` | date | No | Siempre | Fecha inicio → `fecha_inicio_contrato` | `{{fecha_inicial}}` | Sí | S/D |
| `term.end_date` | date | No | Siempre; `>= start_date` | Fecha terminación → `fecha_terminacion_contrato` | `{{fecha_final}}` | Sí | S/D |
| `term.duration_label` | string | Sí | Mientras negocio defina duración derivada | Meses vigencia → `meses_contrato` | `{{parcialidades}}`, `{{vigencia}}` | No | S/D |
| `term.rent_due_rule` | object `{raw_text, day_from?, day_to?, day_of_month?}` | No | Siempre; no inferir semántica | Días pago → `dias_pago` | `{{dia_pago}}` | Sí, corruptible | S/D |
| `amounts.total_rent` | decimal(14,2) | Sí | Definir si puede derivarse | Renta total → `monto_total` | `{{monto}}`, `{{monto_letra}}` | Sí | S/D |
| `amounts.monthly_rent` | decimal(14,2) | No | Siempre para V1 | Renta mensual → `monto_mensual` | `{{monto_mensualidad}}`, letra | Sí | S/D |
| `amounts.security_deposit` | decimal(14,2) | No | Siempre mientras Form lo exige | Depósito → `monto_deposito` | `{{garantia}}`, letra, cláusula | Sí | S/D |
| `amounts.rental_commission` | decimal(14,2) | Sí | Según decisión financiera | Comisión renta → `comision_renta` | No detectado | Sí | S/D |
| `amounts.monthly_commission_value` | decimal(9,4) | Sí | Según decisión financiera | Comisión mensual % → `comision_mensual` | No detectado | Sí | S/D |
| `amounts.monthly_commission_unit` | enum `percent|fraction|fixed|unknown` | No | Siempre si hay comisión | No existe | No aplica | No | S/D |

### 3.8 Forma de pago, datos bancarios, mantenimiento y renovación

| Clave Laravel | Descripción / tipo | N | Condición | Google Form → Apps Script | Documento | L | S/D |
|---|---|:---:|---|---|---|:---:|---|
| `payment.method` | enum | No | Siempre | Forma pago → `forma_pago` | `{{forma_pago}}` | No | S/D |
| `payment.bank_name` | string | Sí | Sólo `bank_transfer` | Institución bancaria → `institucion_bancaria` | `{{banco}}` | No | S/D |
| `payment.beneficiary` | string | Sí | Sólo `bank_transfer` | Beneficiario → `beneficiario` | `{{beneficiario}}` | No | S/D |
| `payment.clabe` | string, validar longitud/formato sólo si negocio lo aprueba | Sí | Sólo `bank_transfer` | CLABE → `clabe` | `{{clabe}}` | No | S/D |
| `maintenance.exists` | enum | No | Siempre | Cuotas mantenimiento → `existen_cuotas_mantenimiento` | `{{mantenimiento_quien}}` | No | S/D |
| `maintenance.payer` | enum | Sí | Sólo `exists=yes` | Parte obligada → `obligada_pagar_cuotas` | Cláusula 1.4BIS | No | S/D |
| `renewal.is_renewal` | enum | No | Siempre | Es renovación → `renovacion` | Cláusula depósito | No | S/D |
| `renewal.previous_contract_id` | FK contratos | Sí | Sólo renovación cuando negocio defina vínculo | No existe | No aplica | No | S |
| `renewal.deposit_treatment` | objeto contractual o `null`; no es catálogo temporal | Sí | Sólo renovación y únicamente cuando negocio defina variantes reales | No existe como dato explícito | Cláusula depósito de renovación | No | S/D |
| `renewal.legacy_deposit_clause_snapshot` | text o referencia inmutable al texto/cláusula legacy aplicada | Sí | Sólo al reproducir un documento legacy de renovación | Regla actual de Apps Script | Cláusula depósito de renovación | No | D |

### 3.9 Generación documental y auditoría/versionado

| Clave Laravel | Descripción / tipo | N | Condición | Google Form → Apps Script | Documento | L | S/D |
|---|---|:---:|---|---|---|:---:|---|
| `document_generation.template_key` | clave estable, p.ej. `lease_with_guarantor` | No | Al solicitar generación | Decisión por `tipo_tercero` | Plantilla Google | No | S/D |
| `document_generation.template_id` | string externo | Sí | Al generar | ID codificado en Script | Google Drive | No | S/D |
| `document_generation.document_version` | integer positivo | No | Cada generación | No existe | Documento generado | No | S/D |
| `document_generation.drive_file_id` | string | Sí | Al responder generación | Derivable de `urldoc`, no enviado explícito | Google Doc | No | S/D |
| `document_generation.drive_folder_id` | string | Sí | Al responder generación | Carpeta creada, no enviada | Drive folder | No | S/D |
| `document_generation.url` | URL | Sí | Al responder generación | `urldoc` | Enlace documento | Sí | S/D |
| `document_generation.status` | enum `generation_status` | No | Siempre por versión | No existe | No aplica | No | S |
| `document_generation.idempotency_key` | UUID/string | No | Cada solicitud | No existe | No aplica | No | S |
| `document_generation.error_code` / `error_detail` | string/text saneado | Sí | Sólo fallo | Log Script, no payload | No aplica | No | S/D |
| `audit.schema_version` | literal `contract_data_v1` | No | Siempre | No existe | No aplica | No | S/D |
| `audit.draft_version` | integer positivo | No | Siempre | No existe | No aplica | No | S |
| `audit.payload_hash` | SHA-256 del JSON canónico normalizado | No | Siempre al guardar/publicar | No existe | No aplica | No | S/D |
| `audit.raw_legacy_payload` | JSON inmutable | Sí | Sólo origen legacy | Payload POST | No aplica | Sí en pendiente | D |
| `audit.created_by` / `published_by` | FK usuario | Sí | Según acción interna | No existe | No aplica | No | S |

## 4. Reglas condicionales exactas V1

| Disparador | Aparecen y son obligatorios | Se ignoran / deben quedar `null` |
|---|---|---|
| Arrendador `fisica` | `lessor.person.full_name`, nacionalidad, lugar/fecha nacimiento, estado civil, ocupación, domicilio, identificación, teléfono, correo | `lessor.person.legal_name`, acta constitutiva y todo `lessor.representative` |
| Arrendador `moral` | `legal_name`, RFC, domicilio, teléfono, correo, acta constitutiva y todos los campos de representante | Campos personales de PF no se piden; no copiar implícitamente datos del representante al objeto sociedad |
| Arrendatario `fisica` | Equivalente PF de arrendador | Razón social, RFC/acta y representante PM |
| Arrendatario `moral` | Equivalente PM de arrendador | Campos personales de PF no se piden |
| Tercero `none` | `guarantor={type:none, person:null, representative:null}` y `guarantee_property.exists=no` | `guarantee_property.address/title_deed=null`; plantilla sin tercero |
| Tercero `fisica` | Parte PF de fiador y `guarantee_property.exists` | Razón social, acta y representante de tercero |
| Tercero `moral` | Parte PM de fiador y representante; `guarantee_property.exists` | Campos PF no se piden |
| Garantía `yes` | Domicilio y título del inmueble garantía | Ninguno de los dos puede normalizarse a cadena vacía en documento publicado |
| Garantía `no` | Sólo `exists=no` | Domicilio y título en `null`; no insertar cláusula 13.3 |
| Pago `cash` | `payment.method=cash` | Banco, beneficiario y CLABE `null`; eliminar/omitir tabla bancaria |
| Pago `bank_transfer` | Banco, beneficiario, CLABE | No eliminar tabla bancaria |
| Pago `unspecified` | Sólo método | Banco, beneficiario, CLABE `null`; usar texto contractual legado, omitir tabla bancaria |
| Mantenimiento `yes` | `maintenance.payer` | Ninguno; insertar cláusula según pagador |
| Mantenimiento `no` | `exists=no` | `payer=null`; no insertar cláusula |
| Uso `residential` exclusivamente | `property_use_codes=[residential]` | No insertar cláusulas adicionales de seguro/permisos actuales |
| Uso no residencial exclusivamente | `property_use_codes` con códigos no residenciales | Aplicar únicamente las cláusulas legacy mapeadas a cada código; no asumir que industrial y comercial tienen idéntico texto futuro |
| Multiuso | Conservar todos los valores de `property_use_codes` en orden canónico | No derivar uso principal ni elegir cláusula dominante. Si las cláusulas no pueden componerse de forma aprobada, bloquear sólo la generación documental y solicitar revisión humana. |
| Renovación `yes` | `renewal.is_renewal`, vínculo explícito a contrato previo cuando exista, y snapshot de cláusula legacy si se reproduce | No mover movimientos ni depósito histórico automáticamente. `deposit_treatment` permanece `null` hasta conocer variantes contractuales reales. |
| Renovación `no` | `is_renewal=no` | `previous_contract_id`, `deposit_treatment` y snapshot de cláusula de renovación en `null` |

Las reglas de visibilidad no sustituyen la validación de servidor. Al cambiar un selector, la UI debe limpiar los campos de la rama no aplicable antes de publicar, pero la versión cruda puede conservar la respuesta original en auditoría legacy.

## 5. Resolución de hallazgos conocidos

| Caso | Comportamiento actual verificado | Riesgo | Representación V1 | Decisión humana pendiente |
|---|---|---|---|---|
| A. Alias typo | Form `propiead`; Script busca `propiedad`; `includes()` no coincide | Alias vacío, carpeta/documento y match erróneos | `leased_property.alias`; legacy adapter acepta ambas etiquetas y registra `source_field_title` | Corregir texto del Form, conservar typo o ambos durante transición |
| B. Expediente ausente | Script lo lee, Form actual no lo tiene; no llega a Laravel | Campo muerto/confusión de identidad | `metadata.contract_reference` nullable, separado de `external_id` | Si debe volver al Form y si es folio legal, operativo o sólo referencia |
| C. Contacto arrendador | Form tiene correo/teléfono visibles; Script usa campos privados PF/PM para `cliente_*` | Pérdida de valor visible o precedencia silenciosa | Conservar `contact_*_entered` legacy y `person.email/phone` canónicos; comparar y exigir resolución si difieren | Qué dato prevalece y si ambos deben persistir |
| D. `dias_pago` | `toInt()` elimina no dígitos; `05 a 10` se vuelve `510` | Corrupción irreversible de regla contractual | `term.rent_due_rule.raw_text` obligatorio + campos opcionales `day_from`, `day_to`, `day_of_month`; adaptador jamás convierte rango a entero | Si representa rango, fecha única, varios días o texto libre |
| E. Tercero PM | Script usa `else if(tipo_solicitante == 'Persona Moral')` al tratar tablas del tercero | Selección/eliminación de tablas depende del arrendador, no fiador | Regla V1 depende sólo de `guarantor.type`; `none` usa plantilla sin tercero, `fisica/moral` su bloque | Validar legalmente la salida esperada de cada plantilla antes de corregir legado |
| F. `editUrl`/`urldoc` | Pendiente los guarda en JSON; resolver privado no los copia al `Contrato` | Contrato definitivo pierde trazabilidad/URL | `metadata.google_form_edit_url` y `document_generation.url`/IDs en versión documental; mantener referencia inmutable al pendiente | Política de visibilidad y si URLs históricas deben preservarse en contrato resumen |

## 6. Versionado y regeneración

1. `schema_version` identifica el significado del DTO (`contract_data_v1`) y nunca cambia dentro de una versión publicada.
2. `draft_version` aumenta cada vez que se guarda un cambio de borrador. La publicación congela un snapshot JSON canónico y su `payload_hash` SHA-256.
3. `document_version` aumenta por cada solicitud de generación, incluso regeneraciones con el mismo `draft_version`.
4. Una versión de documento guarda `template_key`, `template_id`, hash del snapshot, clave idempotente, Drive file/folder ID, URL, estado, intentos, errores y timestamps.
5. `payload_hash` se calcula con JSON normalizado (orden de claves, fechas ISO, decimales canónicos) para distinguir cambios reales de formato.
6. Una regeneración nunca actualiza URL, plantilla ni hash de una versión previa; crea una fila nueva ligada al mismo borrador publicado.
7. Un reintento técnico de la misma solicitud conserva la misma `idempotency_key`; una solicitud humana de regeneración usa una nueva clave y nuevo `document_version`.

## 7. Modelo de persistencia propuesto

### Estructuras existentes que se reutilizan

- `clientes`, `propiedades`, `inquilinos`: maestros operativos. Sólo se vinculan tras conciliación/confirmación; no son sustituto de los snapshots contractuales.
- `contratos`: contrato definitivo mínimo y relaciones ya existentes. Puede seguir siendo el resumen operativo y recibir en una fase posterior una relación a la versión publicada; no es suficiente para almacenar todas las ramas/documentos.
- `contratos_pendientes`: intake legacy y conciliación. Mantener durante convivencia; no convertirlo en el modelo de borrador V1 porque sus estados/payload se orientan a importación externa.

### Nuevas estructuras conceptuales

| Modelo/tablas | Responsabilidad | Relaciones / datos principales |
|---|---|---|
| `contract_drafts` | Raíz de captura Laravel y snapshots publicables | `schema_version`, `draft_version`, `status`, referencias opcionales a cliente/propiedad/inquilino/contrato, `canonical_payload` JSON, `payload_hash`, origen, `external_id`, usuarios y timestamps. Una fila por versión de borrador o raíz + tabla de revisiones, según decisión de implementación. |
| `contract_parties` | Partes estructuradas por versión | Pertenece a borrador; `role`, `person_type`, nombre/razón social, RFC, contacto, datos personales/societarios, snapshot. Evita añadir decenas de columnas a `contratos`. |
| `contract_party_representatives` | Representante legal de una parte moral | Pertenece a `contract_parties`; nombre, identidad, nacimiento, domicilio, ocupación, facultades. `null` para parte física. |
| `contract_property_snapshots` | Inmueble arrendado y, opcionalmente, garantía | Pertenece a borrador; `kind=leased|guarantee`, alias/domicilio/título/uso, FK opcional a propiedad maestra. |
| `contract_document_versions` | Historia inmutable de documentos Google | Pertenece al borrador publicado y opcionalmente al contrato; `document_version`, plantilla, IDs Drive, URL, estado, hash, clave idempotente, error y actor. |
| `contract_generation_attempts` | Reintentos técnicos auditables | Pertenece a versión documental; solicitud/respuesta saneadas, intento, estado, error, timestamps. |
| `contract_audit_events` | Trazabilidad de cambios y decisiones | Polimórfica o ligada al borrador/contrato; evento, actor, origen, metadata sin secretos. |

No se recomienda crear una tabla por cada pregunta del Form. Las columnas que se consultarán/validarán deben ser estructuradas; el snapshot canónico JSON conserva fidelidad documental y permite evolución de esquema. Las migraciones futuras deberán ser aditivas y reversibles.

### Modelo físico mínimo recomendado para Fase 1

El análisis de `Contrato` y `ContratoPendiente` confirma que pueden continuar como resumen operativo e intake legacy, respectivamente, pero ninguno contiene borradores versionados ni historia documental. Para la convivencia de Fase 1 no se justifica crear tablas de parte, representante, inmueble, eventos y reintentos por separado: todas esas ramas pueden permanecer en el `canonical_payload` JSON versionado hasta que exista una necesidad real de consulta transversal.

**A) Requerido en Fase 1 — tres estructuras nuevas, como máximo.**

| Tabla/modelo mínimo | Responsabilidad y campos mínimos | Relaciones / idempotencia |
|---|---|---|
| `contract_drafts` | Identidad estable del borrador: `source`, `external_id` nullable, `status`, referencias opcionales a `contrato`, `cliente`, `propiedad`, `inquilino`, `current_version_id`, creador/publicador y timestamps. | Una raíz por flujo interno o importado. Índice/único parcial o regla de servicio para `(source, external_id)` cuando éste exista; nunca sustituye `contratos_pendientes` durante convivencia. |
| `contract_draft_versions` | Snapshot inmutable: `contract_draft_id`, `draft_version`, `schema_version`, `canonical_payload` JSON, `payload_hash`, `raw_legacy_payload` JSON nullable, acción/origen/actor y timestamps. | Único `(contract_draft_id, draft_version)` y, para una misma raíz, hash/versiones inmutables. Cubre borradores versionados, snapshot canónico y auditoría básica de cambio sin tablas de partes. |
| `contract_document_versions` | Resultado/solicitud documental: `contract_draft_version_id`, `document_version`, `template_key`, `template_id`, `snapshot_hash`, `idempotency_key`, Drive file/folder IDs, URL, estado, contador de intentos, último error saneado y timestamps. | Únicos `(contract_draft_version_id, document_version)` y `idempotency_key`. Un reintento reutiliza su clave y actualiza sólo su intento/estado; una regeneración crea nueva fila/versionado. |

**B) Recomendable posteriormente, no requisito de Fase 1.**

- `contract_generation_attempts` cuando se necesite conservar cada request/response y varios errores por versión documental, no sólo el último intento.
- `contract_audit_events` cuando se requiera una bitácora consultable de permisos, transiciones y acciones fuera de las versiones inmutables.
- `contract_parties`, `contract_party_representatives` y `contract_property_snapshots` sólo si se requiere búsqueda, reportes o relaciones SQL por parte/inmueble. No deben duplicar el JSON antes de que exista ese caso de uso.

**C) Datos que pueden permanecer dentro de `canonical_payload` JSON inicialmente.**

Las ramas completas de arrendador, arrendatario, fiador y representantes; inmueble arrendado/en garantía; pago/banco; mantenimiento; renovación y la matriz de uso del inmueble. El JSON es el snapshot documental autoritativo de una versión. Las referencias a maestros se mantienen también en `contract_drafts` sólo para conciliación y consulta operativa, no para reemplazar el snapshot. `raw_legacy_payload` conserva evidencia de entrada, separado del payload canónico.

## 8. LEGACY ADAPTER V1

El adapter recibe el payload actual de `/api/forms/contratos` sin alterar esa ruta durante convivencia. Produce un DTO V1 y guarda el payload original inmutable.

| Payload / título legado | Traducción V1 |
|---|---|
| `external_id` o `response_id` | `metadata.external_id`; si faltan, usar fallback legado sólo para pendiente y marcar `identity_quality=weak`. |
| `cliente_nombre`, `nombre_solicitante`, `razon_social_solicitante` | `lessor.person.full_name` y/o `legal_name` según `tipo_solicitante`; conservar qué clave ganó. |
| `cliente_rfc`, `rfc_solicitante` | `lessor.person.rfc`; no inventar si es RFC de PF o sociedad. |
| `cliente_correo`, `cliente_telefono`, `correo_solicitante`, `telefono_solicitante` | Contacto canónico más `contact_*_entered` si existen fuentes distintas. |
| Alias typo o corregido | Aceptar ambos títulos/keys, normalizar a `leased_property.alias`, conservar título original. |
| `propiedad_domicilio`, `domicilio_inmueble_arrendamiento` | `leased_property.address`; si difieren, no elegir silenciosamente: conservar ambos en `legacy_conflicts` y bloquear publicación hasta revisión. |
| `fecha_inicio_contrato`, `fecha_terminacion_contrato`, importes/comisiones | Convertir a ISO/decimal con normalizador sin pérdida; guardar valor bruto si hubo ambigüedad. |
| `dias_pago` | Siempre llenar `term.rent_due_rule.raw_text` con texto original; sólo poblar campos numéricos si un parser explícito y probado reconoce un formato aprobado. |
| `nombre_complementaria` y datos básicos | `lessee.person`; campos no presentes quedan `null`, no se infieren. |
| `tipo_tercero` | `guarantor={type, person, representative}`. El payload legado actual no trae detalle de tercero, pago, mantenimiento, renovación, uso ni garantía: usar `person=null`, `representative=null`, `guarantee_property.exists=no` y clasificar el draft como incompleto; conservar siempre la estructura completa del fiador. |
| `editUrl`, `urldoc` | Metadato Form y primera versión documental con `generation_status=generated` y `source=legacy_import`; no derivar Drive IDs sin validación. |

El adapter no crea automáticamente clientes, propiedades, inquilinos ni contratos. Continúa alimentando `ContratoPendiente` en Fase 1 y, en paralelo controlado, puede crear un borrador V1 marcado `source=google_form`/`status=draft` para comparación. Cualquier cambio de autenticación del endpoint histórico requiere coordinación con Apps Script y se pospone fuera de esta Fase 0.

## 9. Fixtures y casos de prueba V1 (diseño, no implementación)

| ID | Fixture mínimo | Verificaciones |
|---|---|---|
| V1-01 | Arrendador PF, arrendatario PF, sin tercero, casa habitación | Campos PF obligatorios; `guarantor={type:none,person:null,representative:null}`; `guarantee_property.exists=no`; plantilla sin tercero y ramas PM nulas. |
| V1-02 | Arrendador PM con representante | Sociedad/representante obligatorios, snapshot separado, no mezclar identidad de representante con sociedad. |
| V1-03 | Arrendatario PM con representante | Equivalente PM y placeholders de arrendatario. |
| V1-04 | Tercero PF sin garantía | Bloque fiador PF, sin cláusula 13.3. |
| V1-05 | Tercero PM con garantía | Bloque PM según `guarantor.type`, representante, garantía y cláusula; detecta la discrepancia del Script legado. |
| V1-06 | Pago efectivo | Banco/beneficiario/CLABE nulos; tabla bancaria omitida. |
| V1-07 | Pago transferencia | Banco/beneficiario/CLABE obligatorios; tabla presente. |
| V1-08 | Pago no especificado | Datos bancarios nulos; texto documental legado correcto. |
| V1-09 | Mantenimiento sí, paga arrendador / arrendatario | Cada texto/cláusula correcto; pagador requerido. |
| V1-10 | Mantenimiento no | Pagador nulo y cláusula ausente. |
| V1-11 | Uso industrial/comercial | Cláusulas adicionales presentes; residencial no las inserta. |
| V1-12 | Renovación | Cláusula de depósito de renovación, sin mover historial; referencia previa aún pendiente de decisión. |
| V1-13 | `dias_pago=05` | `raw_text=05`, parser opcional day 5 sólo si regla aprobada. |
| V1-14 | `dias_pago=05 a 10` | `raw_text` idéntico; nunca `510`; campos de rango nulos hasta regla aprobada. |
| V1-15 | Alias `propiead` y alias corregido | Ambos adaptan a la misma clave, con procedencia distinta. |
| V1-16 | Reintento de generación y regeneración humana | Mismo intento no duplica; regeneración crea `document_version` nueva sin sobreescribir URL anterior. |

## 10. Decisiones técnicas resueltas

| ID | Decisión cerrada | Aplicación V1 |
|---|---|---|
| DH-01 | Conservar siempre el texto original de `dias_pago`; no inferir un rango sin regla explícita. | `term.rent_due_rule.raw_text` es obligatorio. Los campos estructurados sólo se llenan por parser aprobado. |
| DH-03 | `contract_reference` es nullable e independiente de `external_id`. | Ninguno participa como sustituto del otro ni se usa para deduplicar sin regla adicional. |
| DH-04 | Las comisiones se guardan como valor y unidad explícita; nunca se infiere unidad por magnitud. | `monthly_commission_value` + `monthly_commission_unit`; los valores legacy ambiguos se preservan para revisión. |
| DH-06 | Durante compatibilidad se preserva `property_use_codes` como arreglo. | No existe `primary_property_use` ni una segunda fuente de verdad. |
| DH-07 | Una renovación relacionada usa vínculo explícito al contrato previo y nunca reasigna historial automáticamente. | `renewal.previous_contract_id` es nullable hasta que exista el vínculo; movimientos, saldos y documentos históricos no se transfieren. |
| DH-08 | Cada documento registra `template_key`, `template_id`, versión y hash del snapshot cuando sea posible. | `contract_document_versions` mantiene historial inmutable de generación/regeneración. |
| DH-11 | La hoja Google de 18,000 columnas no se modifica durante esta migración. | No forma parte de cambios Laravel ni del adaptador V1. |

## 11. Decisiones humanas abiertas

| ID | Pregunta | Por qué importa | Opciones posibles | Impacto técnico | Recomendación técnica neutral |
|---|---|---|---|---|---|
| DH-02 | ¿Cuál contacto del arrendador prevalece si difiere? | Datos de cliente y documento | Visible, privado PF/PM, ambos con prioridad | Reglas adapter/conciliación | Conservar ambos y exigir decisión de precedencia. |
| DH-05 | ¿Qué campos son jurídicamente obligatorios por rama? | Form actual marca todos obligatorios, incluso ramas saltadas | Matriz legal aprobada | Form Request y UI | Aprobar matriz antes de activar captura Laravel. |
| DH-09 | ¿Quién ve/genera/regenera documentos Drive? | Privacidad y operación | Admin, agent, viewer, por cliente | Políticas y URLs | Definir permisos por acción y no exponer URLs por defecto. |
| DH-10 | ¿Cómo autenticar la fase híbrida? | Endpoint actual público | HMAC, OAuth, cuenta servicio, Web App privado | Adapter/secretos/rollback | Elegir mecanismo con identidad verificable; no token en query string. |

## 12. Fase 0 — Gate de salida

No se autoriza código de la Fase 1 sólo por la existencia de este documento. Antes se debe registrar la evidencia indicada para cada estado.

| Estado | Condiciones |
|---|---|
| **RESUELTO** | DTO V1 con `guarantor` de forma constante; `guarantee_property` con `exists` constante; compatibilidad de alias typo; `dias_pago.raw_text`; `contract_reference` separado; comisiones con valor/unidad; `property_use_codes` como arreglo sin valor primario; renovación con vínculo explícito sin transferencia histórica; versionado de plantilla/documento; y no intervención de la hoja Google. También quedó definido el mínimo físico de tres tablas para Fase 1, sujeto a revisión de implementación. |
| **PENDIENTE NO BLOQUEANTE** | DH-02 no impide guardar ambos contactos en borrador/snapshot ni comparar con Google; impide sólo elegir automáticamente un contacto maestro o documental. La semántica jurídica de multiuso y las variantes reales de tratamiento de depósito pueden permanecer como snapshot legacy y bloquear sólo la generación específica que no tenga regla aprobada. |
| **BLOQUEANTE PARA FASE 1** | DH-05: debe existir una matriz jurídica aprobada de obligatoriedad por rama antes de habilitar la captura Laravel con validación/publicación. Además, deben existir fixtures anonimizados representativos y el mapeo de cada campo del Form a V1/placeholder revisado contra documentos de referencia. Sin ello sólo se permite análisis, no un flujo de captura operativo. |
| **BLOQUEANTE SÓLO PARA FASE 3** | DH-09 y DH-10: políticas de quién puede ver/generar/regenerar documentos y autenticación verificable Laravel ↔ Google. También se requieren IDs/propietarios/permisos reales de plantillas y Drive, pruebas de idempotencia y aceptación documental de las cláusulas multiuso/renovación antes de ejecutar generación desde Laravel. No bloquean la persistencia de borradores de Fase 1. |

## Resultado de Fase 0

V1 permite capturar todo lo que el Form y Apps Script necesitan para documentos sin depender de títulos frágiles, preserva valores legacy sin pérdida y separa datos maestros, snapshots contractuales y versiones de documento. Ninguna decisión jurídica, financiera ni de operación de Google queda implícita en este diseño.
