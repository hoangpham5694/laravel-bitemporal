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
    public function current(): static
    {
        return $this->asOf();
    }

    public function asOf($validAt = null, $transactionAt = null): static
    {
        return $this->applySnapshotConstraints(clone $this, $validAt, $transactionAt);
    }

    public function applyCurrentSnapshot($validAt = null, $transactionAt = null): static
    {
        return $this->applySnapshotConstraints($this, $validAt, $transactionAt);
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

        return $this->current()->whereKey($id)->first($columns);
    }

    public function findSole($id, $columns = ['*'])
    {
        return $this->current()->whereKey($id)->sole($columns);
    }

    public function findMany($ids, $columns = ['*'])
    {
        $ids = $ids instanceof Arrayable ? $ids->toArray() : $ids;

        if (empty($ids)) {
            return $this->model->newCollection();
        }

        return $this->current()->whereKey($ids)->get($columns);
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

    public function delete($validAt = null): int
    {
        $models = $this->get();

        if ($models->isEmpty()) {
            return 0;
        }

        $transactionAt = now();

        return DB::connection($this->model->getConnectionName())->transaction(function () use ($models, $validAt, $transactionAt) {
            foreach ($models as $model) {
                $model->bitemporalDelete($validAt, $transactionAt);
            }

            return $models->count();
        });
    }

    public function hardDelete(): int
    {
        return (int) $this->toBase()->delete();
    }

    protected function applySnapshotConstraints(self $query, $validAt = null, $transactionAt = null): static
    {
        $validAt = $this->normalizeDateTime($validAt ?? now());
        $transactionAt = $this->normalizeDateTime($transactionAt ?? now());

        return $query
            ->where($this->model->qualifyColumn('valid_from'), '<=', $validAt)
            ->where($this->model->qualifyColumn('valid_to'), '>', $validAt)
            ->where($this->model->qualifyColumn('transaction_from'), '<=', $transactionAt)
            ->where($this->model->qualifyColumn('transaction_to'), '>', $transactionAt);
    }

    protected function normalizeDateTime($value): Carbon
    {
        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        if ($value === BitemporalDefaults::INFINITY_DATETIME) {
            return Carbon::parse($value);
        }

        return Carbon::parse($value);
    }
}
