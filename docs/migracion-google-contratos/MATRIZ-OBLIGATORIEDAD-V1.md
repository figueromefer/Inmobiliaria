# Matriz de obligatoriedad y visibilidad V1

## Propósito y criterio de lectura

Esta matriz cierra el análisis técnico de DH-05 sin inventar requisitos jurídicos. Se contrastó contra `CONTRATO-DATOS-V1.md`, el plan de migración, el inventario visual y lógica condicional del Form, el mapeo documental y `appsscript/Código.js`.

El respaldo del Form registra **97 de 97 preguntas como obligatorias**. La columna **GF** no significa que una pregunta sea exigible en todos los contratos: es `Sí` sólo cuando Google la marca requerida *y la rama vuelve visible su sección*. Una sección saltada no produce respuesta y por tanto su campo no puede exigirse en esa ruta.

Abreviaturas: **GF** = requerido actualmente por Google Form; **AS** = consumido por Apps Script; **Doc** = afecta contenido, selección de plantilla, nombre de archivo/carpeta o cláusula del Google Doc; **V1** = requerido técnicamente para validar/publicar un DTO completo, no una conclusión jurídica. `R*` indica que la obligatoriedad final requiere confirmación humana/jurídica. `—` significa no visible/no aplicable.

## 1. Navegación que determina visibilidad real

| Selector | Ruta visible | Ramas que quedan fuera |
|---|---|---|
| Arrendador PF / PM | PF → sección 5; PM → sección 6 | La otra sección de arrendador |
| Arrendatario PF / PM | PF → sección 8; PM → sección 9 | La otra sección de arrendatario |
| Tercero | ninguno → sección 14; PF → sección 11; PM → sección 12 | Datos del tercero no seleccionado |
| Garantía de tercero PF/PM | sí → sección 13; no → sección 14 | Sección 13 |
| Forma de pago | transferencia → sección 15; efectivo/no especificado → sección 16 | Datos bancarios para efectivo/no especificado |
| Mantenimiento | sí → sección 17; no → enviar | Pagador de mantenimiento para no |

Consecuencia técnica: Laravel debe conservar la visibilidad condicional, pero validar por objeto/rama V1 y no por el atributo HTML global de Google Forms.

## 2. Matriz de campos comunes, metadatos e inmueble arrendado

| Clave V1 | Pregunta actual del Form | Rama | Visible | GF | AS | Doc | V1 | Razón / hallazgo | Decisión humana |
|---|---|---|---|---:|---:|---:|---|---|---:|
| `metadata.contract_date` | Fecha de firma de documentación | todas | Sí | Sí | Sí | Sí `{{fecha_firma}}` | R* | El Script la reemplaza directamente; no llega a Laravel actual. | Sí |
| `amounts.rental_commission` | Comisión por renta | todas | Sí | Sí | Sí | No | R* | Llega al payload Laravel, pero no se inserta en el Doc actual. | Sí |
| `amounts.monthly_commission_value` + `unit` | Comisión mensual % | todas | Sí | Sí | Sí | No | R* | Llega a Laravel; unidad histórica ambigua. V1 preserva valor+unidad. | No (unidad ya resuelta técnicamente) |
| `metadata.source` / `external_id` | No es pregunta; `responseId` | todas | — | — | Sí | No | Sí para adapter | ID de submit y edición, no campo de captura. | No |
| `metadata.google_form_edit_url` | No es pregunta; `editUrl` | todas | — | — | Sí | No | No | Debe persistirse para trazabilidad, no condiciona validez documental. | No |
| `leased_property.alias` | Alias de la **propiead** en Arrendamiento | todas | Sí | Sí | **No**: Script busca `propiedad` | Sólo nombre de carpeta/documento vía fallback `SIN ALIAS` | R* | Typo impide coincidencia `includes`; no garantiza alias útil. | Sí |
| `leased_property.address` | Domicilio completo del Inmueble en Arrendamiento | todas | Sí | Sí | Sí | Sí `{{inmueble_arrendado}}` | Sí | Es también `propiedad_domicilio`; Script lo asigna al domicilio de arrendatario PF antes de reemplazar. | No |
| `leased_property.property_use_codes` | Indica el uso que tendrá el Inmueble en Arrendamiento | todas | Sí | Sí | Sí | Sí `{{tipo}}`; activa cláusulas no residenciales | Sí como arreglo | Pregunta de casillas: multiuso posible; Script compara como si fuera un único texto. | Sí |
| `term.start_date` | Fecha de inicio de vigencia del Contrato | todas | Sí | Sí | Sí | Sí `{{fecha_inicial}}` | Sí | Formatea la fecha en Script. | No |
| `term.end_date` | Fecha de terminación de vigencia del Contrato | todas | Sí | Sí | Sí | Sí `{{fecha_final}}` | Sí | Formatea la fecha en Script. | No |
| `term.duration_label` | Meses de vigencia del Contrato | todas | Sí | Sí | Sí | Sí `{{parcialidades}}`, `{{vigencia}}` | Sí | Opciones 12/24/36/48; conservar etiqueta documental. | No |
| `term.rent_due_rule.raw_text` | Días de pago de la renta Ej. 05 a 10 | todas | Sí | Sí | Sí | Sí `{{dia_pago}}` | Sí | V1 conserva texto; no convertir rango a entero. | No |
| `amounts.total_rent` | Monto por concepto de Renta Total | todas | Sí | Sí | Sí | Sí monto/letra | Sí | Script convierte a letra; requiere parseo decimal sin pérdida. | No |
| `amounts.monthly_rent` | Monto por concepto de Renta Mensual | todas | Sí | Sí | Sí | Sí monto/letra | Sí | Script convierte a letra. | No |
| `amounts.security_deposit` | Monto por concepto de Depósito en Garantía | todas | Sí | Sí | Sí monto/letra y cláusula | Sí | Alimenta importe y cláusula de depósito. | No |

## 3. Arrendador

### 3.1 Selector y persona física

| Clave V1 | Pregunta | Rama | Visible | GF | AS | Doc | V1 | Razón / hallazgo | Humana |
|---|---|---|---|---:|---:|---:|---|---|---:|
| `lessor.person.person_type` | La Parte Solicitante (Arrendador) es | todas | Sí | Sí | Sí | Sí, tablas/cláusulas | Sí | Determina sección PF/PM. | No |
| `lessor.person.full_name` | Nombre completo o Razón Social del Arrendador | todas | Sí | Sí | Sí | Sí `{{arrendador}}`, carpeta | Sí | Para PF el nombre documental; para PM el Script reutiliza el mismo campo como razón social. | Sí |
| `lessor.person.rfc` | RFC del Arrendador | todas | Sí | Sí | Sí (`cliente_rfc`) | **No confiable PF** | R* | El placeholder `{{arrendador_rfc}}` recibe `rfc_sociedad_solicitante`, no `cliente_rfc`; PF puede quedar vacío. | Sí |
| `lessor.person.contact_email_entered` | Correo del Arrendador | todas | Sí | Sí | No | No | No | Capturado, pero no hay `includes()` compatible en Script. | Sí |
| `lessor.person.contact_phone_entered` | Teléfono del Arrendador | todas | Sí | Sí | No | No | No | Capturado, pero no hay `includes()` compatible en Script. | Sí |
| `lessor.person.nationality` | Nacionalidad de la Parte Solicitante | PF | Sí | Sí | Sí | Sí | R* | Placeholder de PF. | Sí |
| `lessor.person.birth_place` | Lugar de nacimiento de la Parte Solicitante | PF | Sí | Sí | Sí | Sí | R* | Placeholder de PF. | Sí |
| `lessor.person.birth_date` | Fecha de nacimiento de la Parte Solicitante | PF | Sí | Sí | Sí | Sí | R* | Placeholder de PF. | Sí |
| `lessor.person.marital_status` | Estado civil de la Parte Solicitante | PF | Sí | Sí | Sí | Sí | R* | Placeholder de PF. | Sí |
| `lessor.person.occupation` | Ocupación de la Parte Solicitante | PF | Sí | Sí | Sí | Sí | R* | Placeholder de PF. | Sí |
| `lessor.person.address` | Domicilio completo de la Parte Solicitante | PF | Sí | Sí | Sí | Sí | R* | Alimenta también cliente legacy. | Sí |
| `lessor.person.identification_type` | Tipo de identificación de la Parte Solicitante | PF | Sí | Sí | Sí | Sí | R* | Placeholder de PF. | Sí |
| `lessor.person.phone` | Teléfono de la Parte Solicitante | PF | Sí | Sí | Sí | Sí | R* | Script lo usa para Doc y cliente legacy. | Sí |
| `lessor.person.email` | Correo electrónico de la Parte Solicitante | PF | Sí | Sí | Sí | Sí | R* | Script lo usa para Doc y cliente legacy. | Sí |

### 3.2 Persona moral y representante

| Clave V1 | Pregunta | Rama | Visible | GF | AS | Doc | V1 | Razón / hallazgo | Humana |
|---|---|---|---|---:|---:|---:|---|---|---:|
| `lessor.person.legal_name` | Nombre completo o Razón Social del Arrendador | PM | Sí | Sí | Sí | Sí `{{arrendador}}` | R* | No hay pregunta separada; Script copia `cliente_nombre` a razón social. | Sí |
| `lessor.representative.full_name` | Representante de la Parte Solicitante | PM | Sí | Sí | Sí | Sí | R* | Placeholder de representante. | Sí |
| `lessor.person.incorporation_deed` | Acta constitutiva de la Sociedad de la Parte Solicitante | PM | Sí | Sí | Sí | Sí | R* | Placeholder de sociedad. | Sí |
| `lessor.representative.authority_deed` | Acta de facultades del representante de la Parte Solicitante | PM | Sí | Sí | Sí | Sí | R* | Placeholder de facultades. | Sí |
| `lessor.representative.nationality` | Nacionalidad del representante de la Parte Solicitante | PM | Sí | Sí | Sí | Sí | R* | También sustituye nacionalidad mostrada del arrendador PM. | Sí |
| `lessor.representative.birth_place` | Lugar de nacimiento del representante de la Parte Solicitante | PM | Sí | Sí | Sí | Sí combinado | R* | Se concatena con fecha, sin separador semántico. | Sí |
| `lessor.representative.birth_date` | Fecha de nacimiento del representante de la Parte Solicitante | PM | Sí | Sí | Sí | Sí combinado | R* | Se concatena con lugar. | Sí |
| `lessor.representative.occupation` | Ocupación del representante de la Parte Solicitante | PM | Sí | Sí | Sí | Sí | R* | Placeholder de representante. | Sí |
| `lessor.person.address` | Domicilio completo de la Sociedad de la Parte Solicitante | PM | Sí | Sí | Sí | Sí | R* | Script lo guarda en variable de representante y lo muestra como domicilio de arrendador. | Sí |
| `lessor.representative.identification_type` | Tipo de identificación del representante de la Parte Solicitante | PM | Sí | Sí | Sí | Sí | R* | Placeholder de representante. | Sí |
| `lessor.person.phone` | Teléfono de la Sociedad de la Parte Solicitante | PM | Sí | Sí | Sí | Sí | R* | Se muestra como teléfono del arrendador PM. | Sí |
| `lessor.person.email` | Correo electrónico de la Sociedad de la Parte Solicitante | PM | Sí | Sí | Sí | Sí | R* | Se muestra como correo del arrendador PM. | Sí |
| `lessor.person.rfc` | RFC de la Sociedad de la Parte Solicitante | PM | Sí | Sí | Sí | Sí `{{arrendador_rfc}}` | R* | Es la única fuente usada por ese placeholder. | Sí |

## 4. Arrendatario

### 4.1 Selector y persona física

| Clave V1 | Pregunta | Rama | Visible | GF | AS | Doc | V1 | Razón / hallazgo | Humana |
|---|---|---|---|---:|---:|---:|---|---|---:|
| `lessee.person.person_type` | La Parte Complementaria (Arrendatario) es | todas | Sí | Sí | Sí | Sí, tablas/cláusulas | Sí | Determina sección PF/PM. | No |
| `lessee.person.full_name` | Nombre completo de la Parte Complementaria | PF | Sí | Sí | Sí | Sí `{{arrendatario}}` | R* | Para PM el Script usa otra pregunta. | Sí |
| `lessee.person.nationality` | Nacionalidad de la Parte Complementaria | PF | Sí | Sí | Sí | Sí | R* | Placeholder de PF. | Sí |
| `lessee.person.birth_place` | Lugar de nacimiento de la Parte Complementaria | PF | Sí | Sí | Sí | Sí | R* | Placeholder de PF. | Sí |
| `lessee.person.birth_date` | Fecha de nacimiento de la Parte Complementaria | PF | Sí | Sí | Sí | Sí | R* | Placeholder de PF. | Sí |
| `lessee.person.marital_status` | Estado civil de la Parte Complementaria | PF | Sí | Sí | Sí | Sí | R* | Placeholder de PF. | Sí |
| `lessee.person.occupation` | Ocupación de la Parte Complementaria | PF | Sí | Sí | Sí | Sí | R* | Placeholder de PF. | Sí |
| `lessee.person.identification_type` | Tipo de identificación de la Parte Complementaria | PF | Sí | Sí | Sí | Sí | R* | Placeholder de PF. | Sí |
| `lessee.person.phone` | Teléfono de la Parte Complementaria | PF | Sí | Sí | Sí | Sí | R* | También llega a Laravel legacy. | Sí |
| `lessee.person.email` | Correo electrónico de la Parte Complementaria | PF | Sí | Sí | Sí | Sí | R* | También llega a Laravel legacy. | Sí |
| `lessee.person.address` | **No hay pregunta PF específica** | PF | — | — | Sí (derivado) | Sí | R* | Script fuerza el domicilio del inmueble como domicilio de arrendatario PF. No hay dato capturado. | Sí |

### 4.2 Persona moral y representante

| Clave V1 | Pregunta | Rama | Visible | GF | AS | Doc | V1 | Razón / hallazgo | Humana |
|---|---|---|---|---:|---:|---:|---|---|---:|
| `lessee.person.legal_name` | Razón Social de la Parte Complementaria | PM | Sí | Sí | Sí | Sí `{{arrendatario}}` | R* | Script asigna esta razón social a nombre documental. | Sí |
| `lessee.representative.full_name` | Representante de la Parte Complementaria | PM | Sí | Sí | Sí | Sí | R* | Placeholder de representante. | Sí |
| `lessee.person.incorporation_deed` | Acta constitutiva de la Sociedad de la Parte Complementaria | PM | Sí | Sí | Sí | Sí | R* | Placeholder de sociedad. | Sí |
| `lessee.representative.authority_deed` | Acta de facultades del representante de la Parte Complementaria | PM | Sí | Sí | Sí | Sí | R* | Placeholder de facultades. | Sí |
| `lessee.representative.nationality` | Nacionalidad del representante de la Parte Complementaria | PM | Sí | Sí | Sí | Sí | R* | También sustituye nacionalidad documental de sociedad. | Sí |
| `lessee.representative.birth_place` | Lugar de nacimiento del representante de la Parte Complementaria | PM | Sí | Sí | Sí | Sí combinado | R* | Se concatena con fecha. | Sí |
| `lessee.representative.birth_date` | Fecha de nacimiento del representante de la Parte Complementaria | PM | Sí | Sí | Sí | Sí combinado | R* | Se concatena con lugar. | Sí |
| `lessee.representative.occupation` | Ocupación del representante de la Parte Complementaria | PM | Sí | Sí | Sí | R* | Placeholder de representante. | Sí |
| `lessee.person.address` | Domicilio completo de la Sociedad de la Parte Complementaria | PM | Sí | Sí | Sí | Sí | R* | Script lo expone como domicilio de arrendatario. | Sí |
| `lessee.representative.identification_type` | Tipo de identificación del representante de la Parte Complementaria | PM | Sí | Sí | Sí | Sí | R* | Placeholder de representante. | Sí |
| `lessee.person.phone` | Teléfono de la Sociedad de la Parte Complementaria | PM | Sí | Sí | Sí | Sí | R* | Placeholder de sociedad. | Sí |
| `lessee.person.email` | Correo electrónico de la Sociedad de la Parte Complementaria | PM | Sí | Sí | Sí | Sí | R* | Placeholder de sociedad. | Sí |
| `lessee.person.rfc` | RFC de la Sociedad de la Parte Complementaria | PM | Sí | Sí | Sí | Sí `{{arrendatario_rfc}}` | R* | No hay RFC PF en el Form. | Sí |

## 5. Tercero/fiador y garantía

| Clave V1 | Pregunta | Rama | Visible | GF | AS | Doc | V1 | Razón / hallazgo | Humana |
|---|---|---|---|---:|---:|---:|---|---|---:|
| `guarantor.type` | El Tercero Interesado (Obligado Solidario y/o Fiador) es | todas | Sí | Sí | Sí | Sí, selecciona plantilla | Sí | `none` salta a operación. | No |
| `guarantor.person.*` | Nombre, nacionalidad, lugar/fecha nacimiento, estado civil, ocupación, domicilio, identificación, teléfono y correo del Tercer Interesado | tercero PF | Sí | Sí (cada pregunta) | Sí | Sí placeholders `fiador_*` | R* | Todos son obligatorios sólo con tercero PF. | Sí |
| `guarantee_property.exists` | Indica si habrá inmueble en garantía | tercero PF | Sí | Sí | Sí | Sí, cláusula 13.3 | Sí | `no` salta sección 13. | No |
| `guarantor.person.legal_name` | Razón Social del Tercer Interesado | tercero PM | Sí | Sí | Sí | Sí `{{fiador}}` | R* | Script sustituye nombre por razón social. | Sí |
| `guarantor.representative.*` | Representante, actas, nacionalidad, lugar/fecha, ocupación, domicilio, identificación, teléfono y correo de Sociedad del Tercero | tercero PM | Sí | Sí (cada pregunta) | Sí | Sí placeholders de fiador/representante | R* | Todos pertenecen a PM; Script mezcla algunos datos del representante con la sociedad. | Sí |
| `guarantor.person.rfc` | RFC de la Sociedad del Tercero Interesado | tercero PM | Sí | Sí | Sí `{{fiador_rfc}}` | R* | No existe RFC PF. | Sí |
| `guarantee_property.exists` | Indica si habrá inmueble en garantía de la Sociedad del Tercero Interesado | tercero PM | Sí | Sí | **No confiable** | Sí si llega `habra_inmueble` | Sí | El `includes("Indica si habrá inmueble en garantía")` sí coincide con ambos títulos; depende del orden de respuestas para sobrescritura. | Sí |
| `guarantee_property.address` | Domicilio completo del inmueble en garantía | garantía sí | Sí | Sí | Sí | Sí, cláusula 13.3 | Sí | Sólo visible al elegir garantía sí. | No |
| `guarantee_property.title_deed` | Título de propiedad del inmueble en garantía | garantía sí | Sí | Sí | Sí | Sí, cláusula 13.3 | Sí | Sólo visible al elegir garantía sí. | No |
| `guarantor.person` / `representative` | No hay preguntas | tercero ninguno | — | — | No | Plantilla sin tercero | No: ambos `null` | V1 exige objeto `guarantor` con `type=none`, no datos de persona. | No |

**Defecto observado:** para eliminar tablas de tercero moral, el Script compara `tipo_solicitante == "Persona Moral"` en vez de `tipo_tercero`. Por tanto, aunque los campos PM sean visibles y obligatorios en Google, la plantilla puede conservar/eliminar el bloque incorrecto. La matriz V1 usa exclusivamente `guarantor.type`.

## 6. Pago, mantenimiento, uso y renovación

| Clave V1 | Pregunta | Rama | Visible | GF | AS | Doc | V1 | Razón / hallazgo | Humana |
|---|---|---|---|---:|---:|---:|---|---|---:|
| `payment.method` | Forma de pago | todas | Sí | Sí | Sí | Sí, texto y tabla bancaria | Sí | Selector gobierna sección bancaria. | No |
| `payment.bank_name` | Institución Bancaria | transferencia | Sí | Sí | Sí | Sí `{{banco}}` | Sí | Efectivo/no especificado saltan sección 15 y el Script elimina tabla. | No |
| `payment.beneficiary` | Beneficiario | transferencia | Sí | Sí | Sí | Sí `{{beneficiario}}` | Sí | Igual regla de visibilidad. | No |
| `payment.clabe` | CLABE | transferencia | Sí | Sí | Sí | Sí `{{clabe}}` | Sí | Igual regla de visibilidad; no hay validación de formato en el respaldo. | Sí |
| `maintenance.exists` | Existen cuotas de mantenimiento | todas | Sí | Sí | Sí | Sí, controla cláusula | Sí | `no` envía el Form. | No |
| `maintenance.payer` | Parte obligada a pagar las cuotas de mantenimiento | mantenimiento sí | Sí | Sí | Sí | Sí, cláusula 1.4BIS | Sí | Si llega cualquier valor distinto a arrendador, Script usa texto de arrendatario. | Sí |
| `maintenance.payer` | No hay pregunta | mantenimiento no | — | — | No | No | No: `null` | V1 no retiene texto `No aplica`; debe ser `null`. | No |
| `leased_property.property_use_codes` | Indica el uso que tendrá el Inmueble en Arrendamiento | residencial | Sí | Sí | Sí | Sí; no agrega cláusulas | Sí como arreglo | Script sólo trata exactamente `Casa Habitación` como residencial. | Sí |
| `leased_property.property_use_codes` | Misma pregunta | industrial/comercial | Sí | Sí | Sí | Sí; agrega cláusulas 4.7/4.8 | Sí como arreglo | Ambos toman la misma cláusula actual; no se distingue el código. | Sí |
| `leased_property.property_use_codes` | Misma pregunta de casillas | multiuso | Sí | Sí | Sí, semántica ambigua | Sí, conservar arreglo | El Script compara una respuesta potencialmente múltiple con string único; no hay regla documental aprobada de composición. | Sí |
| `renewal.is_renewal` | ¿Es renovación? | todas | Sí | Sí | Sí | Sí, función de cláusula depósito | Sí | El Script modifica cláusula, pero Form no captura contrato previo ni variantes de depósito. | Sí |
| `renewal.previous_contract_id` | No hay pregunta | renovación sí | — | — | No | No | No hasta vínculo explícito | No inferir ni trasladar historia. | Sí |
| `renewal.deposit_treatment` | No hay pregunta | renovación sí | — | — | Sí, sólo regla embebida | Snapshot legado, no requerido | V1 guarda cláusula legacy, no inventa catálogo contractual. | Sí |

## 7. Hallazgos de obligatoriedad y riesgo documental

### 7.1 Obligatorio en Google, pero no exigible en todos los caminos

- Todos los campos PF y PM de una misma parte aparecen obligatorios en el inventario, pero sólo una de sus secciones es visible por submit.
- Los datos del tercero PF/PM y garantía no son exigibles si `guarantor.type=none`; garantía tampoco se exige cuando su selector es `no`.
- Banco, beneficiario y CLABE son obligatorios únicamente con transferencia; efectivo y no especificado saltan esa sección.
- El obligado de mantenimiento es obligatorio únicamente cuando existen cuotas.

### 7.2 Datos necesarios para el documento que la navegación no garantiza correctamente

| Dato / placeholder | Situación comprobada | Riesgo |
|---|---|---|
| `leased_property.alias` / carpeta | Google exige título con `propiead`; Script busca `propiedad`. | Alias vacío y nomenclatura `SIN ALIAS`. |
| `{{arrendador_rfc}}` para PF | Google exige RFC genérico, pero Script reemplaza con RFC de sociedad, no con `cliente_rfc`. | Placeholder vacío en PF. |
| Domicilio de arrendatario PF | No existe pregunta PF; Script copia domicilio del inmueble. | Documento puede declarar un domicilio no capturado como propio. |
| Tercero PM / tablas | Form sí entrega datos PM, pero Script decide la eliminación de tablas con tipo de arrendador. | Bloque documental incorrecto pese a respuestas completas. |
| Garantía PM | Ambos títulos de garantía coinciden con el mismo `includes`. | El último orden de respuesta puede decidir `habra_inmueble`. |
| Multiuso | Form permite casillas; Script espera comparación de un texto. | Cláusulas de uso no deterministas o incompletas. |
| `{{mantenimiento_quien}}` | El Script inserta cláusula sólo si encuentra marcador; si no, sólo registra log. | Documento sin cláusula pese a respuesta requerida. |
| `{{uso_inmueble}}` / `{{si_inmueble}}` | Son anclas para insertar cláusulas, no valores finales. | Si la plantilla cambia, la regla puede no insertarse sin fallo de submit. |

### 7.3 Capturado sin efecto actual en Script, Laravel o documento

| Campo | Estado observado |
|---|---|
| Correo del Arrendador | Google lo exige; Script no lo lee, no llega a Laravel y no rellena placeholder. |
| Teléfono del Arrendador | Google lo exige; Script no lo lee, no llega a Laravel y no rellena placeholder. |
| Número de expediente | Apps Script tiene un lector, pero la pregunta no existe en el inventario Form; no se usa en Doc ni payload Laravel. |

Las comisiones sí llegan a Laravel actual, aunque no afecten el documento; por ello no pertenecen a esta lista.

## 8. Casos de prueba representativos (diseño, sin datos reales)

| ID | Fixture anonimizado | Validaciones de matriz |
|---|---|---|
| OBL-01 | Arrendador PF, arrendatario PF, sin tercero, residencial, efectivo, sin mantenimiento, no renovación. | Sólo ramas PF visibles; `guarantor.type=none`; banco/pagador/garantía nulos; verificar RFC PF y domicilio de arrendatario como hallazgos. |
| OBL-02 | Arrendador PM, arrendatario PM, tercero PF sin garantía, transferencia, mantenimiento paga arrendador, renovación. | Secciones PM + tercero PF + banco + mantenimiento; placeholders de representantes; cláusula 1.4BIS de arrendador; snapshot de renovación. |
| OBL-03 | Arrendador PF, arrendatario PM, tercero PM con garantía, transferencia, mantenimiento paga arrendatario, no renovación. | Sección tercero PM, cláusula 13.3, tabla bancaria y prueba de defecto de tablas del tercero. |
| OBL-04 | Cualquier combinación válida, pago no especificado. | No se muestran campos bancarios; tabla bancaria se elimina y se usa texto legacy de pago. |
| OBL-05 | Cualquier combinación válida, mantenimiento no. | No se muestra pagador ni cláusula de mantenimiento. |
| OBL-06 | Uso industrial únicamente y uso comercial únicamente. | Ambas activan cláusulas no residenciales actuales; registrar que el texto no se diferencia por código. |
| OBL-07 | Uso residencial + comercial como respuesta de casillas. | Preservar `property_use_codes[]`; no escoger uso principal; bloquear sólo generación si no hay composición aprobada. |
| OBL-08 | Alias con título actual `propiead`. | Adaptador V1 acepta la respuesta; caso legacy evidencia que Script actual deja alias vacío. |
| OBL-09 | `dias_pago="05 a 10"`. | `raw_text` idéntico; nunca convertir a `510`. |
| OBL-10 | Renovación con depósito y sin vínculo a contrato anterior. | Guardar snapshot de cláusula legacy; no inventar `previous_contract_id` ni tratamiento de depósito. |

## 9. Decisiones para aprobación humana

| Campo / decisión | Comportamiento actual | Propuesta V1 | Decisión necesaria |
|---|---|---|---|
| `metadata.contract_date` | Google la exige y Script la imprime; no existe regla visible que defina si una fecha vacía invalida jurídicamente el documento. | Capturar como fecha/snapshot y exigirla sólo al publicar cuando se apruebe la matriz jurídica. | Confirmar si fecha de firma es requisito jurídico de publicación y su formato/autoridad. |
| Datos PF de arrendador, arrendatario y fiador | Google exige todos los campos de su rama y el Doc los usa. | Preservarlos y marcarlos `R*`, sin afirmar que todos son jurídicamente indispensables. | Aprobar por parte cuáles datos personales son obligatorios para publicar. |
| Datos PM y representantes | Google exige todos los campos PM y el Doc los usa, mezclando datos de sociedad/representante en algunos placeholders. | Persistir por objeto correcto (sociedad/representante) y conservar snapshot. | Aprobar qué datos pertenecen a la sociedad, al representante y cuáles son jurídicamente obligatorios. |
| RFC de persona física | Form tiene RFC genérico; documento actual no lo usa correctamente. | Conservar RFC PF si se captura y no reutilizar RFC de sociedad. | Confirmar si RFC PF debe aparecer en contrato y en qué cláusula/tabla. |
| Domicilio de arrendatario PF | No se pregunta; Script usa domicilio del inmueble. | No inferir; `lessee.person.address` queda pendiente/vacío si no existe fuente real. | Confirmar si debe capturarse domicilio del arrendatario PF o si la equivalencia actual es intencional. |
| `leased_property.alias` | Form lo exige con typo; Script no lo consume. | Adapter acepta ambas grafías y conserva origen. | Confirmar si alias es obligatorio para documento, conciliación o sólo nomenclatura. |
| Garantía de tercero PM | Dos títulos similares llegan a una sola variable por coincidencia parcial. | Una única clave V1 `guarantee_property.exists` por tercero. | Confirmar que la garantía de tercero PM tiene la misma semántica y cláusula que la de PF. |
| Forma de pago / CLABE | Transferencia exige tres campos; no hay especificación de formato CLABE en fuentes. | Exigir presencia por transferencia; aplicar validación de formato sólo con regla aprobada. | Confirmar validación y política de privacidad/visualización de datos bancarios. |
| Mantenimiento | Si no se reconoce exactamente arrendador, Script escribe cláusula de arrendatario. | Enum estricto `lessor|lessee`; rechazar valor desconocido en publicación. | Confirmar texto jurídico, especialmente incremento de renta cuando paga arrendador. |
| Multiuso | Google permite casillas; Script sólo tiene regla binaria residencial/no residencial. | Preservar arreglo; no generar combinación sin regla aprobada. | Definir cláusulas cuando residencial, industrial y/o comercial coexisten. |
| Renovación | Sólo indicador sí/no; Script cambia cláusula de depósito. | Snapshot legacy + vínculo explícito opcional; sin catálogo contractual inventado. | Definir variantes de depósito y si toda renovación debe vincular contrato previo. |

## 10. Gate DH-05

**DH-05 está resuelto técnicamente en cuanto a visibilidad, rutas, fuentes y campos que el flujo actual exige o consume.** La matriz permite implementar la mecánica de ramas y almacenar borradores sin adivinar requisitos jurídicos.

**DH-05 no puede cerrarse como autorización para publicar contratos desde Laravel** hasta que se aprueben las decisiones humanas concretas de la sección 9: obligatoriedad jurídica por rama PF/PM, fecha de firma, RFC PF, domicilio de arrendatario PF, pertenencia de datos de sociedad/representante, garantía PM, formato/privacidad bancaria, texto de mantenimiento, cláusulas multiuso y variantes de renovación/depósito.

Por tanto, Fase 1 puede preparar persistencia de borradores y comparación controlada con Google sólo después de aprobar la matriz operativa; la captura con validación/publicación y cualquier generación documental deben permanecer bloqueadas hasta la aprobación jurídica indicada.
