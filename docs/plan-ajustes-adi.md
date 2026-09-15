# Plan técnico — ajustes ADI

Fecha de auditoría: 2026-09-15. Alcance: revisión estática de la rama `main`; no se ejecutaron migraciones, comandos de negocio, cambios de datos ni pruebas que inicialicen base de datos.

## Resumen de arquitectura relevante

- Laravel 12/PHP 8.2, Blade/Vite/Alpine y Tom Select cargado desde CDN. Los roles son `admin`, `agent` y `viewer`: admin administra usuarios, aprueba movimientos y elimina; admin/agente administran registros; viewer es consulta.
- El reporte mensual se genera con `barryvdh/laravel-dompdf` desde `ReporteMensualController` y `ReporteFinancieroService`. La fuente financiera común ya distingue aprobado, cancelado, liquidado y pendiente. `CorteMensualService` también existe y debe revisarse antes de consolidar más cálculos.
- `movimientos` tiene fecha de periodo (`fecha`), `fecha_liquidacion`, aprobación, estado de pago, folio y relación directa a cliente/propiedad/inquilino; no tiene `contrato_id` ni vínculo de movimiento origen/destino.
- Los comprobantes nuevos se almacenan en R2 privado por defecto (`config/movimientos.php`), con metadata de disco, MIME, nombre y tamaño. Los históricos con disco nulo se interpretan como `public`; la ruta autenticada genera URL temporal de R2. Límite actual: 50 MiB.
- Contratos privados entran todavía mediante Google Forms hacia `POST /api/forms/contratos`, y por defecto se guardan como `contratos_pendientes`; el listado conserva el enlace directo al Google Form. Justicia Alternativa consulta un Web App de Apps Script configurado por variables de entorno y conserva payload/mapeo JSON.
- No existen actualmente relación Contrato→movimientos, Contrato→documentos, Contrato→pendiente ni vista/ruta de detalle del contrato. Sí existen las relaciones inversas suficientes para diseñarlas: propiedad tiene movimientos/documentos/contratos y cliente tiene contratos/documentos.
- Cobertura de pruebas: autenticación y pruebas de movimientos/R2/gestión. No hay pruebas específicas para reporte mensual, contratos, filtros o Google.

### Observaciones transversales de seguridad y producción

- Las rutas `__run_migrate__`, `__migrate_dry_run__`, `__migrate_status__`, backfill y limpieza de caché están declaradas sin autenticación en `routes/web.php`. No forman parte de estos 17 cambios, pero deben restringirse o retirarse en un PR de hardening separado antes de un despliegue amplio.
- No inspeccionar ni registrar credenciales de `.env`; la configuración R2 y Google debe verificarse por despliegue con una cuenta autorizada.
- Futuras migraciones serán sólo aditivas, con índices y `down()` probado. Nunca usar `migrate:fresh`, `db:wipe` ni operaciones masivas no reversibles.

## Matriz de requerimientos

| ID | Módulo | Estado propuesto | Complejidad | Riesgo | Dependencias | Archivos probables | Migración | Aclaración |
|---|---|---|---|---|---|---|---|---|
| 1 | Reporte PDF | Implementable | Baja | Bajo | Dompdf, logo local | ReporteMensualController, mensual_pdf | No | No |
| 2 | Contratos | Diseñar/implementar lectura | Media | Medio | relaciones derivadas, permisos | Contrato*, modelos, rutas, vista nueva | No | Sí |
| 3 | Igualas | Bloqueado; validar legado | Alta | Alto | regla financiera, vínculo de origen | comando existente, Movimiento, Contrato, servicio nuevo | Sí | Sí |
| 4 | Comisión primer mes | Bloqueado | Alta | Alto | regla depósito/comisión | Contrato, Movimiento, servicio nuevo | Sí | Sí |
| 5 | Reporte + anexos | Spike técnico y luego implementación | Alta | Alto | R2/public, ensamblador PDF | Reporte*, servicio de anexos, composer | Probable | Sí |
| 6 | Reporte PDF | Implementable | Baja | Bajo | valores numéricos del servicio | mensual, mensual_pdf | No | No |
| 7 | Cargas | Implementable por componente | Media | Medio | formularios multipart, límites PHP/web | layout/app, JS, 3 vistas de carga | No | Sí |
| 8 | Google→Laravel | Descubrimiento/bloqueado por acceso | Muy alta | Alto | Forms, Drive, Apps Script, decisión de contrato | FormsIntake, ContratoPendiente, Google | Sí | Sí |
| 9 | Movimientos/reporte | Implementable de presentación | Baja | Medio | definición terminológica | Movimiento*, reporte | No | Sí |
| 10 | Reporte PDF | Spike Dompdf e implementación | Media | Medio | maquetación real de páginas | mensual_pdf | No | No |
| 11 | Selects | Diagnóstico reproducible | Baja | Bajo | Tom Select, navegador/SO | layout/app, vistas | No | Sí |
| 12 | Tablas | Patrón servidor gradual | Alta | Medio | índices y reglas de cada módulo | controladores/listados | Probable | Sí |
| 13 | Movimientos | Implementable tras pruebas | Media | Alto | aprobación individual, auditoría | MovimientoController, índice, tests | No | No |
| 14 | Movimientos/renta | Implementable con regla mínima | Media | Alto | contrato activo y validación backend | MovimientoController, API, create | No | Sí |
| 15 | Clientes/saldo | Bloqueado por definición | Media | Alto | ReporteFinancieroService/CorteMensualService | ClienteController, servicio, índice | No | Sí |
| 16 | Propiedades | Bloqueado por definición | Media | Alto | contrato, fecha esperada, pagos | PropiedadController, servicio, índice | No | Sí |
| 17 | Renovaciones | Diseño; bloqueado | Muy alta | Alto | reglas financieras + Google | contratos, intake, documentos | Sí | Sí |

## Hallazgos por requerimiento

1. **Logo y cliente.** `pdf()` obtiene el cliente por `pk_cliente`, lo entrega como `$cliente` y Dompdf renderiza `reportes.mensual_pdf`. El PDF ya nombra el archivo con el cliente, pero el encabezado no lo muestra. La ruta actual tiene un error tipográfico: `public_path('imgages/logo.png')`; el archivo real es `public/images/logo.png`. Usar `public_path('images/logo.png')` como ruta local, no URL pública.

2. **Detalle de contrato.** `Contrato` contiene FKs de cliente/propiedad/inquilino, partes/tipos, fechas, renta, depósito, comisiones, días de pago, URLs Google, origen, expediente JA, importación y payload JA. El listado sólo tiene `index`; no hay `show`, ruta ni vista, ni una relación de movimientos/documentos/pendiente en el modelo. La pantalla debe cargar cliente, propiedad (incluido archivado), inquilino, pendiente por `contrato_id`, documentos por propiedad/cliente/inquilino y movimientos por propiedad/cliente/inquilino como secciones separadas y rotuladas como asociaciones derivadas, hasta que exista un FK de contrato. Los campos provenientes de Google se conservan en `raw_payload`/`mapped_payload` del pendiente, `edit_url`, `urldoc` y los campos normalizados por `FormsIntakeController`. Todos los autenticados pueden consultar actualmente el listado; no hay políticas de lectura por registro. Admin/agente ven acciones de entrada/importación; viewer debe permanecer sólo lectura.

3. **Iguala.** `comision_mensual` es el candidato: `getComisionMensualFractionAttribute()` acepta 10 como 10% y .10 como 10%. Existe `movimientos:generar-igualas`, no ejecutado en esta auditoría, que toma rentas aprobadas no canceladas, busca contrato activo por propiedad/cliente, calcula porcentaje sobre importe y además suma `comision_renta` una sola vez según reglas legacy. Su detección de duplicado es por cliente/propiedad/mes, no por movimiento origen; por ello no satisface aún trazabilidad/idempotencia/reversión solicitadas. Propuesta: una tabla aditiva de automatizaciones o columnas de origen (`movimiento_origen_id`, `contrato_id`, `tipo_regla`, `periodo`, `idempotency_key`) con índice único; un servicio transaccional crea/revierte ajuste explícitamente desde un único evento confirmado. No activar hasta definir evento, IVA/redondeo, periodicidad y mutaciones.

4. **Comisión primer mes.** `comision_renta` es decimal y el comando legacy lo trata como cantidad fija, no porcentaje, y la agrega a una iguala ligada a una renta; no hay relación técnica con un depósito ni evidencia de que “se toma del depósito”. No hay concepto separado de comisión ni estado/autor de origen. Requiere decidir si se descuenta contablemente del depósito, si genera gasto/iguala nuevo, su disparador y si renovaciones aplican. Debe compartir la misma infraestructura de origen, idempotencia y reversión del punto 3.

5. **Anexos.** Dompdf produce hoy un solo HTML/PDF y no combina PDFs externos. R2 privado está correctamente abstraído por `Storage`; no debe consumir URL temporal para compilar. Spike recomendado: descargar cada objeto R2/public a directorio temporal no público con stream, validar MIME/tamaño/páginas, crear portada por anexo, rasterizar imágenes a página y combinar PDFs con una librería compatible (por ejemplo FPDI/FPDF más el PDF generado por Dompdf). Justificar la dependencia: Dompdf no importa ni concatena PDF existente. Orden determinista `fecha`, `folio`, `id`; registrar omisiones en una sección final del reporte/log sin exponer path o URL. Ejecutar en job/cola para anexos grandes, aplicar límites configurables, timeouts y `finally` para limpieza.

6. **Ceros.** Las filas resumen se imprimen siempre en ambas vistas y sus entradas se convierten a `float` desde el servicio; filtrar con comparación numérica exacta (`(float)$valor !== 0.0`), antes de formatear. Las secciones de movimientos ya se omiten cuando la colección está vacía, no cuando sus importes suman cero.

7. **Cargas.** Hay multipart en movimiento, documento y comentarios de ticket; movimiento acepta 50 MiB, documento aún 10 MiB y ticket debe verificarse contra su request. Un componente global puede detectar `form[enctype="multipart/form-data"]`, deshabilitar submit, mostrar estado accesible y `beforeunload`; el POST HTML tradicional no proporciona progreso real. Para barra real se requiere adaptar dichos tres formularios a XHR/fetch/Axios (progreso de upload), manejo de 422 que restaure botones y fallback progresivo. También alinear `upload_max_filesize`, `post_max_size`, proxy y PHP-FPM con 50 MiB más margen.

8. **Google.** Integración localizada: enlace Forms en listado, `POST /api/forms/contratos`, `FormsIntakeController`, `contratos_pendientes`, `ContratoPendienteController`, `JusticiaAlternativaImportService`, `JUSTICIA_ALTERNATIVA_WEBAPP_URL/TOKEN`, `edit_url`/`urldoc`, `raw_payload` y `raw_justicia_alternativa`. No se hallaron jobs ni comandos de Drive/Formularios. Falta: esquema exacto de Form, identidad/autenticación del webhook, plantillas/merge fields, contrato de Apps Script, IDs de Drive, permisos, versionado, reintentos y flujo de regeneración. Convivencia: mantener endpoint y Forms actuales; nuevo borrador Laravel usa tabla propia/versionada y al publicar llama al adaptador Apps Script, guardando respuesta inmutable; activar por feature flag y no cortar el webhook hasta conciliación y aceptación.

9. **Fechas de renta.** `fecha` es la fecha usada para filtrar reporte, periodo y contrato activo; `fecha_liquidacion` se fuerza nula si pendiente/cancelado y por defecto toma `fecha` si liquidado. Aprobación individual sólo cambia aprobación, no fecha/estado. La UI actual muestra `fecha` como “Fecha” y muestra una fecha sin etiqueta debajo de estado pago; el PDF dice “Fecha”. Fase inmediata: etiquetas “Periodo/fecha a la que corresponde” y “Fecha de liquidación”, ambas sólo donde aplique. Confirmar si para renta siempre representa mes completo o día de vencimiento; no cambiar el filtro financiero sin ello.

10. **Firma huérfana.** La firma es un `div` final con margen fijo y sin control de paginación. Dompdf no expone una medición de altura previa de bloques HTML fiable; usar CSS paginado `page-break-inside: avoid`/`break-inside: avoid` sobre un contenedor que agrupe el cierre del resumen y la firma, con margen sin altura fija; probar contenido de varias longitudes. Si no basta, usar el canvas de Dompdf para pie fijo sólo si negocio acepta firma en cada página; no usar saltos fijos.

11. **Scroll de selects.** Los selects simples son nativos; `.js-searchable-select` usa Tom Select 2.3.1. No se encontraron listeners `wheel`/`scroll`, inversión CSS ni transformación que altere el eje. Reproducir antes de cambiar: navegador/versión, SO, dispositivo (trackpad/ratón), dirección natural de scroll, URL/campo exacto, si el menú Tom Select está abierto, video y resultado en select nativo de la misma página. No registrar listener global.

12. **Tablas.** Todos los listados usan paginación servidor: clientes, contratos, inquilinos, movimientos, propiedades, documentos, usuarios, bitácora y archivados; reporte es un resultado no paginado. Contratos e inquilinos ya implementan whitelist de orden y preservan query; clientes/propiedades/documentos/movimientos tienen búsqueda/filtros pero orden fijo; usuarios/bitácora tienen filtros parciales. Las cantidades reales no se deben inferir del código: medir con consulta de sólo lectura autorizada en producción/staging antes de seleccionar índices. Propuesta: objeto `TableQuery`/trait por módulo con whitelist de columnas, filtros validados, `paginate()->withQueryString()` y componentes Blade de cabecera/filtro; ordenar joins sólo con selección explícita y añadir índices tras EXPLAIN.

13. **Aprobación masiva.** Existe `approve()` de admin, que comprueba `pending` y llama `approveBy`, y `ActivityObserver` registra cambios. Diseñar endpoint POST/PATCH con IDs visibles enviados por formulario, autorización admin, deduplicación, bloqueo/consulta de pendientes, transacción y resultados `approved/skipped/failed`. Reusar `approveBy` o extraer servicio para no divergencia. “Todos” sólo marca checkboxes renderizados de la página; no enviar filtro como permiso implícito. Agregar token/botón disabled y confirmación.

14. **Previsualización de renta.** La propiedad no contiene renta; el único monto vigente es `contratos.monto_mensual`. El controlador sólo resuelve contrato activo cuando se asigna por inquilino, y entonces prioriza fecha actual. Crear endpoint autenticado que, para propiedad y fecha de periodo, resuelva exactamente un contrato no archivado cuyo rango cubra esa fecha; devolver referencia y contrato. Al guardar una renta asignada a propiedad, validar de nuevo; por ahora sólo advertir discrepancia, no sobrescribir ni rechazar importe sin regla de negocio. Si no existe/son varios, no mostrar monto y exigir captura/confirmación según decisión.

15. **Disponible cliente.** `ReporteFinancieroService` ya expone `saldo_disponible_para_pago` = saldo de movimientos aprobados, que afectan saldo y están liquidados (ingresos menos gasto/gasto_cliente/iguala/pago_cliente); excluye cancelados. También expone saldo contable, que incluye pendientes. `CorteMensualService` puede contener lógica complementaria y debe consolidarse antes de UI. No calcular por cliente en un loop: una consulta agregada por cliente o extender el servicio para colección; definir fecha de corte y semántica exacta antes.

16. **Semáforo.** Hay contratos con rango y `dias_pago`, movimientos con estado/fecha, y propiedad con relaciones, pero no pagos parciales ni contrato_id en movimiento. El reporte ya infiere desocupada usando contratos del mes/rentas, lo cual no equivale al semáforo. Posibles reglas sólo para discusión: sin contrato activo=desocupada; contrato activo y saldo de renta vencida tras gracia=atrasada; de otro modo=al corriente. No implementar hasta confirmar día, gracia, pagos parciales, contratos futuros/terminados, periodos y definición de deuda.

17. **Renovaciones.** No hay modelo/vínculo de renovación ni creación interna de contrato privado. Diseñar tablas/adiciones: `contratos.contrato_anterior_id` nullable, `tipo_origen`/estado explícito o entidad `renovaciones`, y referencias de generación documental/versiones. Flujo: seleccionar contrato anterior → snapshot/prefill editable → validar solapamiento activo por propiedad → crear nuevo contrato sin mover movimientos/documentos históricos → enlazar y auditar → solicitar generación al adaptador Google → almacenar ID/enlace/versiones. Justicia Alternativa sólo puede ser origen/prefill hasta que se confirme si sus documentos se regeneran. La continuidad de saldos debe ser una consulta, nunca reasignación automática.

## Elementos implementables de inmediato

1. PR de reporte sin reglas financieras: corregir ruta local del logo, título con cliente, etiquetas de las dos fechas, ocultar filas resumen exactamente cero y corregir firma con pruebas de render.
2. PR de detalle de contrato sólo lectura, con relaciones existentes/derivadas y autorización coherente; sin edición, migración ni escritura.
3. PR de aprobación masiva reutilizando una única regla de aprobación, con pruebas de permisos, estados e idempotencia de solicitud.
4. PR de previsualización de renta como referencia y validación de contrato activo; el comportamiento frente a importe distinto depende de una pregunta pendiente.
5. PR de diagnóstico Tom Select, sólo si se aporta evidencia reproducible.

## Bloqueados por acceso a Google

- Sustituir Forms, generar documentos desde Laravel, preservar plantillas y realizar versiones/regeneraciones.
- Confirmar contrato de webhook, campos/mapeos, IDs Drive, permisos, Apps Script, reintentos y qué sistema queda como autoridad durante convivencia.
- No eliminar el enlace, endpoint ni flujos de pendiente/Justicia Alternativa hasta tener acceso, inventario y prueba en entorno aislado.

## Bloqueados por definición de negocio

- Evento, porcentaje, IVA, redondeo, periodicidad, reversión y contabilización de iguala.
- Comisión de primer mes/deposito, tipo de importe y aplicación a renovaciones.
- Definición de disponible, fecha de corte y tratamiento de pendientes.
- Semáforo, vencimiento, gracia, parcialidades y ocupación.
- Renovaciones: solapamiento, saldos, adeudos, documentos y origen Justicia Alternativa.
- Semántica de periodo de renta y política ante importe distinto a renta vigente.

## Preguntas exactas para el cliente

1. ¿`comision_mensual` es el porcentaje de iguala? ¿Valores como `10` y `0.10` deben significar ambos 10%?
2. ¿La iguala nace al registrar renta, aprobarla, liquidarla o al cierre? ¿Su fecha es la de periodo, liquidación o fin de mes?
3. ¿La iguala lleva IVA, qué método de redondeo usa y qué debe pasar si se modifica/cancela/elimina la renta o cambia el contrato?
4. ¿`comision_renta` es monto fijo o porcentaje? ¿Se descuenta realmente del depósito, genera qué concepto y aplica en renovaciones?
5. Para una renta, ¿`fecha` representa el mes de renta, día de vencimiento o fecha de captura? ¿Cómo se registra un pago parcial?
6. ¿“Monto disponible” es el saldo liquidado para pago ya calculado por el reporte, el saldo contable u otra fórmula? ¿A qué fecha de corte?
7. ¿Cuál es el contrato activo cuando hay traslape, qué día vence la renta y cuántos días de gracia hay? ¿Cómo se clasifican contratos futuros, terminados y sin contrato?
8. ¿En previsualización, una renta distinta al monto del contrato debe bloquearse, permitir excepción con motivo o sólo advertirse?
9. ¿Quién puede consultar el detalle de contrato y las URLs/documentos: viewer, agent, admin? ¿Debe ocultarse payload crudo de Google?
10. Para anexos, ¿se incluyen sólo movimientos que afectan saldo y están aprobados, también pendientes, y en qué orden/criterio se identifica cada anexo?
11. ¿Cuál es el máximo aceptable de anexos/tamaño/tiempo y se acepta generación asíncrona con aviso/descarga posterior?
12. Entreguen acceso/documentación de Form, Drive, Apps Script, plantillas, campos, respuesta de generación, cuentas de servicio y política de regeneración/versiones.
13. Para renovación, ¿cuándo puede coexistir con el anterior, qué saldos continúan sólo visualmente y qué documentos se regeneran para privado y Justicia Alternativa?

## Estrategia de pruebas

- Unitarias: resolución de contrato activo, formato/filtrado numérico de resumen, regla de aprobación y futuras claves idempotentes.
- Feature: roles, detalle sólo lectura, filtros/orden/paginación, aprobación masiva con estados mixtos, previsualización/validación server-side, R2 y fallback `public` con `Storage::fake`.
- PDF: fixture con logo local, cliente, ceros positivos/negativos y contenido corto/largo; renderizar, inspeccionar texto/páginas y hacer revisión visual de la firma.
- Anexos: fixtures PDF multipágina, JPG/PNG, objeto R2/local inexistente, MIME dañado y límites de tamaño; verificar limpieza temporal y ausencia de URL R2 en salida/logs.
- Integración Google: sandbox separado con payloads capturados anonimizados, reintentos y duplicados; nunca producción hasta aceptación.
- Antes de índices/filtros globales: `EXPLAIN` y conteos sólo lectura en réplica/staging o ventana autorizada.

## Despliegue y reversión sin pérdida de datos

- Cada PR independiente, con feature flag para automatizaciones/Google/anexos; backup verificable y prueba de restauración fuera de producción.
- Ejecutar migraciones aditivas una sola vez en ventana aprobada; mantener columnas/lecturas antiguas durante una versión de transición. No borrar comprobantes ni datos históricos en rollback.
- Para R2, validar credenciales, subida, lectura autenticada y fallback público en staging; no habilitar URLs públicas ni registrar URLs temporales.
- Para cálculos, iniciar en modo simulación/auditoría, comparar contra movimientos manuales y requerir aprobación explícita antes de crear movimientos. Revertir con movimientos compensatorios auditables, no con eliminación.
- Monitorear errores PDF/R2/cola, tiempo y memoria; disponer de interruptor de desactivación y volver al reporte sin anexos si falla.

## Fases y PRs recomendados

### Fase 0 — hardening y línea base (PR separado, recomendado antes de despliegues)

Implementada en la rama `fix/harden-operational-routes`.

| Ruta encontrada | Propósito | Decisión aplicada | Razón/alternativa segura |
|---|---|---|---|
| `GET /__backfill_contratos_fk__` | Ejecutar el backfill HTTP de FKs de contratos | Sólo `local`/`testing`; ausente en producción y staging | El backfill CLI `contratos:backfill-fk` requiere ejecución explícita y controlada desde hosting/CLI. |
| `GET /__migrate_status__` | Ejecutar `migrate:status` | Sólo `local`/`testing` | En staging/producción, ejecutar `php artisan migrate:status` en consola autenticada. |
| `GET /__migrate_dry_run__` | Ejecutar `migrate --pretend` | Sólo `local`/`testing` | Usar CLI temporal del hosting o pipeline de despliegue con control de acceso. |
| `GET /__run_migrate__` | Ejecutar `migrate --force` | Sólo `local`/`testing` | Migraciones únicamente por pipeline/CLI autorizado, con respaldo y ventana aprobada. |
| `GET /__clear_caches__` | Limpiar cachés de config, app, rutas y vistas | Sólo `local`/`testing` | Usar CLI autenticada (`optimize:clear` o comandos específicos) durante despliegue; nunca endpoint HTTP. |

No se encontraron otras rutas HTTP que invoquen `Artisan`, migraciones, backfills, limpieza de caché u operaciones de mantenimiento equivalentes. `routes/console.php` conserva tareas CLI/schedule legítimas y no expone HTTP. La clase `BackfillContratosController` permanece por compatibilidad de desarrollo, pero no tiene ruta HTTP fuera de `local`/`testing`; no debe usarse como mecanismo de despliegue.

Pruebas de línea base agregadas: `OperationalRoutesTest` crea un proceso aislado de `route:list --env=production --json` y comprueba que las cinco rutas no se registran; además comprueba la presencia de clientes, contratos, movimientos, Justicia Alternativa y `POST /api/forms/contratos`. Validaciones ejecutadas: `php artisan route:list` (115 rutas en el entorno local; las cinco rutas aparecen allí de forma intencional) y `php artisan test` (54 correctas; 2 fallos preexistentes en `RegistrationTest` porque `/register` devuelve 404 y el alta pública está deshabilitada). La prueba nueva pasa: 2 pruebas/13 aserciones. Acción manual de despliegue: eliminar cualquier bookmark/runbook que invoque esas URLs y usar el control de hosting, pipeline o CLI autenticada; no requiere migración ni cambio de datos.

### Fase 1 — reporte mensual sin anexos

Implementada en la rama `feat/reporte-mensual-pdf`: el PDF usa `public_path('images/logo.png')`, identifica al cliente en la primera página, muestra las etiquetas de periodo y liquidación, y filtra cada fila de resumen por su valor numérico antes de formatear. El bloque de resumen y firma se agrupa con `page-break-inside: avoid` y `break-inside: avoid`, sin salto fijo ni margen vertical fijo. La interfaz de reporte y movimientos conserva los mismos datos/cálculos y sólo aclara las etiquetas.

Aceptación: PDF con cliente/logo, negativos visibles, ceros ausentes y pruebas de render con reporte corto y largo. La revisión visual del primer folio se realizó mediante Quick Look; el entorno no tiene Poppler/PyPDF para extraer una imagen de la última página, por lo que la prueba automatizada comprueba que el PDF largo tiene múltiples páginas y que el cierre usa el contenedor de no separación. La revisión final en staging debe incluir el último folio de un reporte de varias páginas antes de desplegar.

### Fase 2 — detalle de contratos de sólo lectura

Ruta/vista/show, relaciones cargadas y permisos de consulta. Aceptación: cada enlace abre datos completos sin mutar, incluidos orígenes Google/JA con información sensible controlada.

### Fase 3 — operaciones de movimientos

PR 3A aprobación masiva; PR 3B previsualización de renta. Aceptación: sólo admin aprueba IDs visibles pendientes; previsualización usa contrato vigente y backend no confía en navegador.

### Fase 4 — experiencia de cargas

Componente global y adaptación XHR de formularios con archivo si se aprueba progreso real. Aceptación: bloquea duplicados/salida, informa accesiblemente y restablece tras 422.

### Fase 5 — tablas por módulo

Empezar movimientos/clientes/propiedades; después documentos, usuarios, bitácora y archivados. Aceptación: whitelist de orden, filtros persistentes y paginación servidor; índices justificados por EXPLAIN.

### Fase 6 — anexos de comprobantes

Spike de ensamblado primero; después job/cola, límites y anexo. Aceptación: PDF/imágenes de R2 y public se anexan en orden, archivos fallidos no rompen reporte y temporales se limpian.

### Fase 7 — reglas financieras

PR 7A modelo de origen/idempotencia y simulador; PR 7B iguala; PR 7C comisión primer mes; PR 7D saldo disponible. Aceptación: reglas firmadas, una fuente de verdad, auditoría/reversión y cero duplicados.

### Fase 8 — semáforo de propiedades

Servicio de estado y UI tras las reglas acordadas. Aceptación: fixtures cubren desocupada/atrasada/al corriente, gracia, parcialidades y límites de contrato.

### Fase 9 — coexistencia Google/Laravel

Inventario Google, borradores, adaptador de generación y versionado detrás de feature flag. Aceptación: ambos flujos viven en paralelo y se reconcilian sin pérdida de documentos.

### Fase 10 — renovaciones

Modelo/vínculos, validación de solapamiento, documentos y trazabilidad. Aceptación: se crea contrato nuevo enlazado, no se reasigna historia y reglas de saldo/documentos están aprobadas.

## Primer bloque recomendado

Implementar **Fase 1** en un PR acotado: es independiente de reglas financieras, no exige migración y corrige defectos verificables del PDF. En paralelo, solicitar las respuestas de negocio para fases 3B y 7, y el acceso/documentación Google para Fase 9.
