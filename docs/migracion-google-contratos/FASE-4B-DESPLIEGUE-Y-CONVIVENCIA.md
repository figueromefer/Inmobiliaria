# Fase 4B — Despliegue controlado, convivencia y transición a producción

## Estado y regla de operación

Esta fase prepara el despliegue. No autoriza apagar ni redirigir el Google Form, Apps Script, `/api/forms/contratos` ni `ContratoPendiente`. El formulario Laravel convivirá con el intake legacy y sólo se compartirá con el equipo Dorantes para un piloto controlado.

Antes de ejecutar cualquier comando de producción, el operador debe identificar el SHA que está desplegado (`<PROD_SHA>`) y el método de release. El inventario exacto se obtiene con:

```sh
git fetch --tags origin
git diff --name-status <PROD_SHA>..HEAD
```

Si se despliega el worktree de esta rama, incluir además los nuevos archivos aún no presentes en Git; no se debe desplegar un worktree sucio como sustituto de un release identificado.

## Checklist de predeploy

- [ ] Confirmar dominio y URL final: `https://<dominio-produccion>/contrato/solicitud`.
- [ ] Confirmar `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` canónica HTTPS y parámetros `DB_*` de producción.
- [ ] Registrar `<PROD_SHA>`, release candidato y ventana de mantenimiento; conservar el release actual como rollback.
- [ ] Verificar PHP 8.2+, extensiones `openssl`, `json`, `pdo_mysql` y acceso saliente HTTPS a `oauth2.googleapis.com`, `www.googleapis.com` y `docs.googleapis.com`.
- [ ] Verificar MySQL/InnoDB y una versión que soporte `JSON` (MySQL 5.7+). Las tablas existentes `contratos`, `clientes`, `propiedades`, `inquilinos` y `users` deben conservar sus PK/FK actuales.
- [ ] Verificar que existe la tabla de cache si `CACHE_STORE=database`, o que el store configurado (Redis, Memcached, etc.) es accesible por PHP-FPM.
- [ ] Confirmar que la cuenta de servicio tiene acceso a ambas plantillas y a la carpeta destino productiva del Shared Drive.
- [ ] Confirmar que Drive API y Google Docs API están habilitadas en el proyecto de la cuenta de servicio.
- [ ] Crear y validar backups antes de cambiar código o ejecutar migraciones.
- [ ] Revisar el diff real contra `<PROD_SHA>`; no incluir secretos, `.env`, credenciales JSON, `vendor/`, `node_modules/`, `storage/`, `.DS_Store`, fixtures ni bases temporales.

## Inventario del release

Los componentes nuevos o modificados de la migración que deben llegar al release son:

- `app/Contracts/GoogleContractDocumentClient.php`
- `app/Exceptions/ContractDraftVersionConflictException.php`
- `app/Http/Controllers/ContractDraftController.php`, `ContractDraftWizardController.php`, `ContractDocumentPreviewController.php`, `PublicContractRequestController.php`
- `app/Http/Requests/StoreContractDraftRequest.php`, `UpdateContractDraftRequest.php`, `SaveContractDraftWizardStepRequest.php`, `LinkContractDraftEntityRequest.php`
- `app/Models/ContractDraft.php`, `ContractDraftVersion.php`, `ContractDocumentVersion.php`, `ContractPublicRequest.php`
- `app/Services/ContractPayloadCanonicalizer.php`, `ContractDraftPayload.php`, `ContractDraftVersioningService.php`, `ContractDocumentVersioningService.php`, `ContractDraftReconciliationService.php`, `ContractDocumentPayloadBuilder.php`, `GoogleServiceAccountTokenProvider.php`, `GoogleApiContractDocumentClient.php`, `GoogleContractDocumentRenderer.php`
- `app/Providers/AppServiceProvider.php`, `config/services.php`, `routes/web.php`
- `database/migrations/2026_09_15_000000_create_contract_drafts_table.php` a `2026_09_15_000002_create_contract_document_versions_table.php`, y `2026_09_17_000003_create_contract_public_requests_table.php`
- `resources/views/contrato/` y `resources/views/contratos/borradores/`, además de los cambios de listado interno en `resources/views/contratos/index.blade.php`.

Las pruebas, fixtures y documentos son parte del repositorio/revisión, no un requisito de runtime. No hay cambio de fuente Vite/JS/CSS de esta fase que exija `npm run build`: el formulario público usa Blade y Tailwind CDN. Si el release incluye otros cambios de frontend, seguir su pipeline normal; nunca copiar `node_modules/`.

Para despliegue por Git, crear después de aprobación un commit/release con el SHA identificable y desplegar sólo ese SHA. Para copia manual, transferir exclusivamente el inventario anterior y las dependencias instaladas por Composer; no copiar `.env` ni ningún JSON de credenciales.

## Variables de entorno de producción

Configurar en el `.env` de producción (nunca en Git):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<dominio-produccion>

DB_CONNECTION=mysql
DB_HOST=<host>
DB_PORT=3306
DB_DATABASE=<base>
DB_USERNAME=<usuario>
DB_PASSWORD=<secreto>

# Mantener el store que producción ya usa; no cambiarlo por esta fase.
CACHE_STORE=<store-existente>

GOOGLE_CONTRACT_TEMPLATE_WITHOUT_GUARANTOR_ID=<id-plantilla-productiva-sin-fiador>
GOOGLE_CONTRACT_TEMPLATE_WITH_GUARANTOR_ID=<id-plantilla-productiva-con-fiador>
GOOGLE_CONTRACT_DESTINATION_FOLDER_ID=<id-carpeta-productiva-shared-drive>
GOOGLE_SERVICE_ACCOUNT_JSON_PATH=/etc/inmobiliaria/secrets/google-contracts-service-account.json
GOOGLE_CONTRACT_TIMEOUT=20
```

Dejar `GOOGLE_SERVICE_ACCOUNT_JSON` vacío al usar el archivo. Los IDs de Google no son secretos, pero deben ser los recursos productivos, no los IDs del sandbox. La carpeta de destino debe estar dentro del Shared Drive productivo. Si no existe o no se puede confirmar su pertenencia al Shared Drive, la generación Google queda como pendiente operativo y no se ejecuta el smoke Google.

## Credencial de cuenta de servicio

El JSON debe permanecer fuera del repositorio y fuera del directorio del release. Sustituir los marcadores por los valores reales del servidor y usuario de PHP-FPM:

```sh
sudo install -d -o <php_user> -g <php_group> -m 0700 /etc/inmobiliaria/secrets
sudo install -o <php_user> -g <php_group> -m 0600 \
  /ruta-segura-origen/google-contracts-service-account.json \
  /etc/inmobiliaria/secrets/google-contracts-service-account.json
sudo -u <php_user> test -r /etc/inmobiliaria/secrets/google-contracts-service-account.json
```

Transferir el archivo por el canal seguro aprobado por infraestructura; no pegarlo en terminal compartida, chat, repositorio ni variables de CI visibles. Confirmar el correo de la cuenta con `sudo -u <php_user> jq -r .client_email /etc/inmobiliaria/secrets/google-contracts-service-account.json` y compartir **ese correo** con ambas plantillas y con la carpeta Shared Drive productiva, con permiso suficiente para crear carpetas y copias (normalmente Content manager).

El proveedor usa el store de cache configurado y guarda el token por 50 minutos bajo `google-contracts-service-account-token`. Antes del smoke Google, comprobar el store real sin modificar su configuración:

```sh
php artisan about --only=cache
php artisan tinker --execute="dump(config('cache.default')); Cache::put('deploy-google-cache-check', 'ok', 60); dump(Cache::pull('deploy-google-cache-check')));"
```

El resultado debe ser el store productivo esperado y `ok`; si falla, resolver el store antes de generar documentos. No se requiere ni recomienda cambiar `CACHE_STORE` por esta fase.

## Migraciones y base de datos

Orden de las migraciones nuevas:

1. `2026_09_15_000000_create_contract_drafts_table`
2. `2026_09_15_000001_create_contract_draft_versions_table`
3. `2026_09_15_000002_create_contract_document_versions_table`
4. `2026_09_17_000003_create_contract_public_requests_table`

Son sólo `create table`, índices y FK nuevas; no alteran ni borran registros existentes. La segunda migración agrega la FK de `contract_drafts.current_version_id` después de crear `contract_draft_versions`. Los `down()` revierten en orden inverso; el rollback funcional preferido no baja tablas, porque puede haber borradores y solicitudes públicas reales. Las FK requieren InnoDB y son compatibles con las PK `BIGINT UNSIGNED` existentes.

Antes de aplicar:

```sh
php artisan migrate:status
php artisan migrate --pretend --force
```

Comando productivo autorizado después del backup y revisión del pretend:

```sh
php artisan migrate --force
```

No usar `migrate:fresh`, `db:wipe` ni una reversión destructiva.

## Backups y rollback de release

Concretar la ruta y mecanismo de respaldo del proveedor antes del deploy. Para MySQL administrado, solicitar snapshot consistente y registrar su identificador. Para host autogestionado, un ejemplo seguro es:

```sh
umask 077
mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 \
  -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p "$DB_DATABASE" \
  > /ruta-backups-restringida/inmobiliaria-pre-4b-$(date +%Y%m%d-%H%M%S).sql
sha256sum /ruta-backups-restringida/inmobiliaria-pre-4b-*.sql
cp .env /ruta-backups-restringida/inmobiliaria-env-pre-4b-$(date +%Y%m%d-%H%M%S)
chmod 600 /ruta-backups-restringida/inmobiliaria-env-pre-4b-*
```

Conservar el release actual (o `git archive <PROD_SHA>`) y sus artefactos Composer. Si hay que revertir: retirar de comunicación la URL nueva o bloquear sólo el grupo `/contrato/solicitud` en el proxy, desactivar la generación manual Laravel si fuera necesario, y volver al release anterior. Mantener el Google Form y Apps Script activos. Las tablas nuevas pueden permanecer sin uso; no eliminar datos ni ejecutar `migrate:rollback` salvo una necesidad aprobada y una evaluación de los datos ya capturados.

## Secuencia de deploy

1. Poner el release candidato identificado, conservar el release anterior y restaurar/validar `.env` productivo.
2. Instalar dependencias sin desarrollo:

   ```sh
   composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
   php artisan migrate --force
   php artisan optimize:clear
   php artisan config:cache
   php artisan view:cache
   ```

3. No ejecutar `php artisan route:cache`: `routes/web.php` contiene closures, por lo que no es compatible con route caching.
4. Verificar que el proceso PHP-FPM/web y, si existe, workers de cola, leen el release y configuración nuevos. Reiniciar sólo mediante el mecanismo normal del hosting.

## Smoke post-deploy

### Interno

Con cuenta admin/agent y datos ficticios:

1. Iniciar sesión y abrir **Borradores internos de contrato y solicitudes públicas**.
2. Crear un borrador interno, recorrer el wizard y verificar una nueva versión por guardado.
3. Abrir previsualización documental. Si Google productivo no está confirmado, detenerse aquí: preparar/previsualizar no debe generar documento.
4. Confirmar que el listado distingue `legacy`, `public_form` e `internal/laravel` por origen y estado, sin mezclar solicitudes.

### Formulario público

Usar sólo datos ficticios y la URL final `https://<dominio-produccion>/contrato/solicitud`:

1. Abrir sin login, iniciar solicitud, guardar al menos dos pasos y reabrir con el enlace privado.
2. Completar una solicitud ficticia y enviar.
3. Confirmar `submitted`, `submitted_at`, origen `public_form` y presencia en borradores internos.
4. Confirmar que el enlace ya no permite editar, que no hay `Contrato` definitivo, ni maestros nuevos, ni `ContractDocumentVersion` automática.

### Google controlado

Ejecutar sólo si la cuenta de servicio, ambas plantillas y la carpeta Shared Drive productiva están confirmadas. Usar un borrador ficticio listo y generar manualmente desde la previsualización interna. Confirmar:

- carpeta y copia creadas dentro del Shared Drive productivo;
- URL persistida;
- plantilla original sin cambios;
- cero marcadores requeridos residuales;
- `ContractDocumentVersion.status=generated`.

Archivar o eliminar el documento de prueba sólo según la política aprobada del Shared Drive; no borrar plantillas.

## Piloto y convivencia

Compartir la URL Laravel exclusivamente con el equipo Dorantes para un conjunto pequeño de casos reales. Mantener el Google Form como fallback, sus triggers Apps Script y `/api/forms/contratos` sin redirección ni cambios. Registrar por caso: arrendador, arrendatario, tercero, garantía, vigencia, importes, pago, mantenimiento, uso, renovación, texto final, firmas, ubicación/nombre Drive y resultado de generación. Comparar el documento resultante con el flujo legacy y registrar incidencias, referencia pública, versión de borrador y responsable.

No retirar Google Form hasta contar con varios casos reales completos, generación Google estable, ramas sin errores, aprobación explícita de Dorantes sobre los documentos y rollback probado/documentado. Cualquier incidencia crítica vuelve al fallback legacy, sin destruir solicitudes Laravel ni datos existentes.
