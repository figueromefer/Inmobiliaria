# Fase 5 — Publicación de contrato privado

## Cadena inmutable

La publicación usa un único snapshot `ContractDraftVersion` de `contract_data_v1`:

`ContractDraft` → `finalization_draft_version_id` → `ContractDocumentVersion` → Google Drive → `Contrato`.

El `Contrato` nuevo conserva referencias a la versión de borrador y a la versión documental. Los datos jurídicos se mapean desde el snapshot congelado; cliente, propiedad e inquilino sólo aportan FKs operativas ya conciliadas.

Los contratos históricos y los importados de Justicia Alternativa mantienen esas referencias en `NULL`.

## Saga e idempotencia

1. Se bloquea el borrador, se valida la versión esperada y que el plan documental esté `ready`.
2. Se persisten una única `finalization_key` y `finalization_draft_version_id`.
3. La misma clave se usa como `ContractDocumentVersion.idempotency_key`.
4. Google crea o reutiliza carpeta y documento fuera de la transacción SQL.
5. Sólo con documento `generated`, una transacción crea el `Contrato`, vincula el draft y lo marca `published`.

Si Google falla, no se crea contrato y el reintento conserva la misma versión documental, carpeta y archivo cuando ya existen. Si la persistencia SQL falla después de Google, el reintento vuelve a usar los mismos activos Drive y completa únicamente la publicación.

Un borrador `published` no admite nuevas versiones ni cambios de conciliación. Una nueva invocación de publicación devuelve el contrato ya vinculado.

## Días de pago

`contratos.dias_pago` es un entero legacy. Sólo se llena si el snapshot contiene explícitamente `term.rent_due_rule.day_of_month` entre 1 y 31. El texto libre (`raw_text`, por ejemplo `25 al 30`) no se convierte ni se pierde: permanece en el snapshot contractual.
