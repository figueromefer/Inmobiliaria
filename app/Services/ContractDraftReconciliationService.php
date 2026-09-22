<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Cliente;
use App\Models\ContractDraft;
use App\Models\Inquilino;
use App\Models\Propiedad;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Conciliación manual de referencias maestras. Nunca altera el snapshot V1
 * ni crea versiones del borrador: las FKs son datos operativos revisables.
 */
class ContractDraftReconciliationService
{
    private const ENTITY_CONFIG = [
        'cliente' => ['column' => 'cliente_id', 'model' => Cliente::class, 'key' => 'pk_cliente'],
        'propiedad' => ['column' => 'propiedad_id', 'model' => Propiedad::class, 'key' => 'pk_propiedad'],
        'inquilino' => ['column' => 'inquilino_id', 'model' => Inquilino::class, 'key' => 'id'],
    ];

    /** @return array<string, array{query:string, results:LengthAwarePaginator|null}> */
    public function searches(Request $request): array
    {
        $searches = [];

        foreach (array_keys(self::ENTITY_CONFIG) as $entity) {
            $query = trim((string) $request->query("{$entity}_q", ''));
            $searches[$entity] = [
                'query' => mb_substr($query, 0, 100),
                'results' => $query === '' ? null : $this->search($entity, mb_substr($query, 0, 100)),
            ];
        }

        return $searches;
    }

    public function link(ContractDraft $draft, string $entity, int $entityId, User $actor, Request $request): ContractDraft
    {
        return DB::transaction(function () use ($draft, $entity, $entityId, $actor, $request) {
            $config = $this->config($entity);
            $lockedDraft = ContractDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $master = $this->find($entity, $entityId);
            $column = $config['column'];
            $oldValue = $lockedDraft->{$column};

            if ((int) $oldValue !== (int) $master->getKey()) {
                $lockedDraft->{$column} = $master->getKey();
                $lockedDraft->save();

                $this->log($lockedDraft, $actor, 'linked', $entity, $oldValue, $master->getKey(), $request);
            }

            return $lockedDraft;
        });
    }

    public function unlink(ContractDraft $draft, string $entity, User $actor, Request $request): ContractDraft
    {
        return DB::transaction(function () use ($draft, $entity, $actor, $request) {
            $config = $this->config($entity);
            $lockedDraft = ContractDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $column = $config['column'];
            $oldValue = $lockedDraft->{$column};

            if ($oldValue !== null) {
                $lockedDraft->{$column} = null;
                $lockedDraft->save();

                $this->log($lockedDraft, $actor, 'unlinked', $entity, $oldValue, null, $request);
            }

            return $lockedDraft;
        });
    }

    /** @return array<string, list<array{label:string,draft:mixed,master:mixed,status:string}>> */
    public function comparisons(ContractDraft $draft): array
    {
        $payload = $draft->currentVersion?->canonical_payload ?? [];

        return [
            'cliente' => $draft->cliente ? $this->compareRows([
                ['Nombre / razón social', data_get($payload, 'lessor.person.full_name') ?: data_get($payload, 'lessor.person.legal_name'), $draft->cliente->nombre, 'text'],
                ['RFC', data_get($payload, 'lessor.person.rfc'), $draft->cliente->rfc, 'rfc'],
                ['Teléfono', data_get($payload, 'lessor.person.phone'), $draft->cliente->celular ?: $draft->cliente->fijo, 'phone'],
                ['Correo', data_get($payload, 'lessor.person.email'), $draft->cliente->correo, 'text'],
                ['Domicilio', data_get($payload, 'lessor.person.address'), $draft->cliente->domicilio, 'text'],
            ]) : [],
            'propiedad' => $draft->propiedad ? $this->compareRows([
                ['Alias', data_get($payload, 'leased_property.alias'), $draft->propiedad->alias, 'text'],
                ['Domicilio', data_get($payload, 'leased_property.address'), $draft->propiedad->domicilio, 'text'],
            ]) : [],
            'inquilino' => $draft->inquilino ? $this->compareRows([
                ['Nombre / razón social', data_get($payload, 'lessee.person.full_name') ?: data_get($payload, 'lessee.person.legal_name'), $draft->inquilino->nombre, 'text'],
                ['Teléfono', data_get($payload, 'lessee.person.phone'), $draft->inquilino->telefono, 'phone'],
                ['Correo', data_get($payload, 'lessee.person.email'), $draft->inquilino->correo, 'text'],
                ['Domicilio', data_get($payload, 'lessee.person.address'), $draft->inquilino->domicilio, 'text'],
                ['Nacionalidad', data_get($payload, 'lessee.person.nationality'), $draft->inquilino->nacionalidad, 'text'],
            ]) : [],
        ];
    }

    /** @return LengthAwarePaginator */
    private function search(string $entity, string $search): LengthAwarePaginator
    {
        return match ($entity) {
            'cliente' => Cliente::query()
                ->when(Schema::hasColumn('clientes', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
                ->where(fn ($query) => $query->where('nombre', 'like', "%{$search}%")->orWhere('rfc', 'like', "%{$search}%")->orWhere('correo', 'like', "%{$search}%"))
                ->orderBy('nombre')->paginate(10, ['pk_cliente', 'nombre', 'rfc', 'correo', 'celular', 'fijo', 'domicilio'], 'cliente_page')->withQueryString(),
            'propiedad' => Propiedad::query()
                ->where(fn ($query) => $query->where('alias', 'like', "%{$search}%")->orWhere('domicilio', 'like', "%{$search}%"))
                ->orderBy('alias')->paginate(10, ['pk_propiedad', 'alias', 'domicilio'], 'propiedad_page')->withQueryString(),
            'inquilino' => Inquilino::query()
                ->where(fn ($query) => $query->where('nombre', 'like', "%{$search}%")->orWhere('correo', 'like', "%{$search}%")->orWhere('telefono', 'like', "%{$search}%"))
                ->orderBy('nombre')->paginate(10, ['id', 'nombre', 'correo', 'telefono', 'domicilio', 'nacionalidad'], 'inquilino_page')->withQueryString(),
            default => throw ValidationException::withMessages(['entity' => 'La entidad de conciliación no es válida.']),
        };
    }

    private function find(string $entity, int $id): Model
    {
        $config = $this->config($entity);
        $model = $config['model'];
        $query = $model::query();

        if (in_array($entity, ['cliente', 'propiedad'], true) && Schema::hasColumn((new $model)->getTable(), 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->findOrFail($id);
    }

    /** @return array{column:string,model:class-string<Model>,key:string} */
    private function config(string $entity): array
    {
        if (!isset(self::ENTITY_CONFIG[$entity])) {
            throw ValidationException::withMessages(['entity' => 'La entidad de conciliación no es válida.']);
        }

        return self::ENTITY_CONFIG[$entity];
    }

    /** @param list<array{0:string,1:mixed,2:mixed,3:string}> $rows
     * @return list<array{label:string,draft:mixed,master:mixed,status:string}>
     */
    private function compareRows(array $rows): array
    {
        return array_map(fn (array $row) => [
            'label' => $row[0],
            'draft' => $row[1],
            'master' => $row[2],
            'status' => $this->comparisonStatus($row[1], $row[2], $row[3]),
        ], $rows);
    }

    private function comparisonStatus(mixed $draft, mixed $master, string $kind): string
    {
        if ($this->isBlank($draft)) {
            return 'sin dato en draft';
        }
        if ($this->isBlank($master)) {
            return 'sin dato en maestro';
        }

        return $this->normalizeForComparison((string) $draft, $kind) === $this->normalizeForComparison((string) $master, $kind)
            ? 'coincide'
            : 'diferente';
    }

    private function normalizeForComparison(string $value, string $kind): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return match ($kind) {
            'phone' => preg_replace('/\D+/', '', $value) ?? '',
            'rfc' => strtoupper(str_replace(' ', '', $value)),
            default => mb_strtolower($value),
        };
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    private function log(ContractDraft $draft, User $actor, string $action, string $entity, mixed $oldValue, mixed $newValue, Request $request): void
    {
        ActivityLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'model_type' => ContractDraft::class,
            'model_id' => $draft->id,
            'module' => 'contract_draft_reconciliation',
            'old_values' => [$entity . '_id' => $oldValue],
            'new_values' => [$entity . '_id' => $newValue],
        ]);
    }
}
