<?php

namespace HoangPhamDev\Bitemporal\Builders;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use HoangPhamDev\Bitemporal\Support\BitemporalDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Arrayable;
use Closure;
use Illuminate\Support\Facades\DB;

class BitemporalBuilder extends Builder
{
    protected ?CarbonInterface $bitemporalValidAt = null;

    public function current(): static
    {
        return $this->asOf();
    }

    public function asOf($validAt = null): static
    {
        $query = clone $this;
        $query->withoutGlobalScope('bitemporal_current');
        $query->bitemporalValidAt = $this->normalizeDateTime($validAt ?? now());

        return $this->applySnapshotConstraints(
            $this->stripCurrentSnapshotConstraints($query),
            $validAt
        );
    }

    public function applyCurrentSnapshot($validAt = null): static
    {
        return $this->applySnapshotConstraints($this, $validAt);
    }

    public function history(): static
    {
        return clone $this;
    }

    public function find($id, $columns = ['*'])
    {
        if (is_array($id) || $id instanceof Arrayable) {
            return $this->findMany($id, $columns);
        }

        return $this->whereKey($id)->first($columns);
    }

    public function findSole($id, $columns = ['*'])
    {
        return $this->whereKey($id)->sole($columns);
    }

    public function findMany($ids, $columns = ['*'])
    {
        $ids = $ids instanceof Arrayable ? $ids->toArray() : $ids;

        if (empty($ids)) {
            return $this->model->newCollection();
        }

        return $this->whereKey($ids)->get($columns);
    }

    public function findOrFail($id, $columns = ['*'])
    {
        $result = $this->find($id, $columns);
        $id = $id instanceof Arrayable ? $id->toArray() : $id;

        if (is_array($id)) {
            if (count($result) !== count(array_unique($id))) {
                throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(
                    get_class($this->model),
                    array_diff($id, $result->modelKeys())
                );
            }

            return $result;
        }

        if (is_null($result)) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(
                get_class($this->model),
                $id
            );
        }

        return $result;
    }

    public function findOrNew($id, $columns = ['*'])
    {
        if (! is_null($model = $this->find($id, $columns))) {
            return $model;
        }

        return $this->model->newInstance();
    }

    public function findOr($id, $columns = ['*'], ?Closure $callback = null)
    {
        if ($columns instanceof Closure) {
            $callback = $columns;
            $columns = ['*'];
        }

        if (! is_null($model = $this->find($id, $columns))) {
            return $model;
        }

        return $callback();
    }

    public function touch($column = null)
    {
        $models = $this->get();

        if ($models->isEmpty()) {
            return 0;
        }

        $touched = 0;

        DB::connection($this->model->getConnectionName())->transaction(function () use ($models, $column, &$touched) {
            foreach ($models as $model) {
                if ($model->touch($column)) {
                    $touched++;
                }
            }
        });

        return $touched;
    }

    public function getModels($columns = ['*'])
    {
        $models = parent::getModels($columns);

        if (! is_null($this->bitemporalValidAt)) {
            foreach ($models as $model) {
                if (method_exists($model, 'setBitemporalValidAt')) {
                    $model->setBitemporalValidAt($this->bitemporalValidAt);
                }
            }
        }

        return $models;
    }

    protected function applySnapshotConstraints(self $query, $validAt = null): static
    {
        $validAt = $this->normalizeDateTime($validAt ?? now());

        return $query
            ->where($this->model->qualifyColumn('valid_from'), '<=', $validAt)
            ->where($this->model->qualifyColumn('valid_to'), '>', $validAt)
            ->where($this->model->qualifyColumn('transaction_to'), '=', BitemporalDefaults::INFINITY_DATETIME);
    }

    protected function normalizeDateTime($value): Carbon
    {
        $timezone = config('app.timezone', date_default_timezone_get());

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value)->setTimezone($timezone);
        }

        if ($value === BitemporalDefaults::INFINITY_DATETIME) {
            return Carbon::parse($value, $timezone);
        }

        return Carbon::parse($value, $timezone);
    }

    protected function stripCurrentSnapshotConstraints(self $query): static
    {
        $queryBuilder = $query->getQuery();
        $wheres = $queryBuilder->wheres ?? [];

        $columnsToStrip = [
            $this->model->qualifyColumn('valid_from'),
            $this->model->qualifyColumn('valid_to'),
            $this->model->qualifyColumn('transaction_to'),
        ];

        $removed = [];

        for ($index = count($wheres) - 1; $index >= 0; $index--) {
            $where = $wheres[$index];

            if (($where['type'] ?? null) !== 'Basic') {
                continue;
            }

            $column = $where['column'] ?? null;

            if (! in_array($column, $columnsToStrip, true)) {
                continue;
            }

            $removed[] = $index;

            if (count($removed) === 3) {
                break;
            }
        }

        if (empty($removed)) {
            return $query;
        }

        rsort($removed);

        foreach ($removed as $index) {
            array_splice($wheres, $index, 1);
        }

        $queryBuilder->wheres = $wheres;
        $queryBuilder->bindings['where'] = $this->rebuildWhereBindings($wheres);

        return $query;
    }

    protected function rebuildWhereBindings(array $wheres): array
    {
        $bindings = [];

        foreach ($wheres as $where) {
            if (($where['type'] ?? null) !== 'Basic') {
                continue;
            }

            if (! array_key_exists('value', $where)) {
                continue;
            }

            $bindings[] = $where['value'];
        }

        return $bindings;
    }

}
