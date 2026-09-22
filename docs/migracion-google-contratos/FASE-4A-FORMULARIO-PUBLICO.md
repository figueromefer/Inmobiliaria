# Fase 4A — Formulario público Laravel

La captura pública usa `ContractDraft` y `ContractDraftVersion` con `source=public_form`; no existe un segundo DTO contractual. `contract_public_requests` sólo conserva la referencia pública ULID, hash SHA-256 del token, expiración, revocación y envío.

Rutas: `GET/POST /contrato/solicitud`, y enlaces privados `/{reference}/{token}/{step}`. El token aleatorio no incluye PII ni IDs internos; se guarda únicamente como hash, expira a los 30 días y deja de ser válido al enviar o revocar. Todas las rutas públicas llevan throttle y CSRF; el campo honeypot `website` rechaza bots. Los datos sensibles sólo se envían por cuerpo POST/PUT y no se registran.

Cada guardado agrega una versión inmutable usando `expected_version_id`; conflictos no sobrescriben. Al enviar, el borrador pasa a `submitted`, se registra `submitted_at` y el enlace de edición queda cerrado. No crea contratos, maestros ni documentos Google. Admin/agent ven el origen y estado en el listado interno y pueden usar después el flujo documental existente.

Google Forms, Apps Script, `FormsIntakeController`, `/api/forms/contratos` y `ContratoPendiente` no forman parte de esta fase y se conservan sin cambios.

## Validación automatizada

`tests/Feature/PublicContractRequestTest.php` cubre el inicio sin login, token no persistido en claro, guardados versionados, datos incompletos bloqueados por el builder, expiración y revocación, honeypot, rechazo de IDs/campos administrativos, conflicto de versión y envío completo. El envío probado sólo cambia el estado del borrador y registra `submitted_at`: no crea `Contrato` ni `ContractDocumentVersion`, y deja cerrado el enlace de edición.

## Matriz de captura

| Paso | Campos / ramas públicas | Estado |
|---|---|---|
| Generales | fecha, alias, domicilio | Captura canónica |
| Arrendador / arrendatario | partial común PF/PM y representante | Reutilizado del wizard interno |
| Tercero | none/PF/PM y partial común | Limpieza en `ContractDraftPayload` |
| Garantía | sí/no, domicilio y título | Limpieza canónica con tercero none |
| Vigencia/importes | fechas, literal de pago e importes | Captura canónica |
| Uso | lista multiuso sin valor primario | Captura canónica |
| Pago | efectivo/indefinido/transferencia y datos bancarios | Limpieza canónica |
| Mantenimiento | existe/pagador | Limpieza canónica |
| Renovación | estado y contrato previo | Limpieza canónica |
| Resumen | envío sin IDs internos | Builder bloquea snapshots incompletos |
