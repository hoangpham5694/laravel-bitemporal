<?php

namespace HoangPhamDev\Bitemporal\Traits;

use HoangPhamDev\Bitemporal\Builders\BitemporalBuilder;
use HoangPhamDev\Bitemporal\Support\BitemporalDefaults;
use Illuminate\Database\Eloquent\Builder;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait HasBitemporal
{
    public static function getBitemporalColumns(): array
    {
        return [
            'valid_from',
            'valid_to',
            'transaction_from',
            'transaction_to',
        ];
    }

    public static function getInfinityDatetime(): string
    {
        return BitemporalDefaults::INFINITY_DATETIME;
    }

    public static function bootHasBitemporal(): void
    {
        static::addGlobalScope('bitemporal_current', function (Builder $builder): void {
            $builder->applyCurrentSnapshot();
        });
    }

    public function scopeCurrent(Builder $query)
    {
        return $query->applyCurrentSnapshot();
    }

    public function scopeAsOf(Builder $query, $validAt = null, $transactionAt = null)
    {
        return $query->applyCurrentSnapshot($validAt, $transactionAt);
    }

    public function scopeWithoutBitemporal(Builder $query)
    {
        return $query->withoutGlobalScope('bitemporal_current');
    }

    public function newEloquentBuilder($query): BitemporalBuilder
    {
        return new BitemporalBuilder($query);
    }

    public function delete($validAt = null)
    {
        return DB::connection($this->getConnectionName())->transaction(function () use ($validAt) {
            return $this->bitemporalDelete($validAt, Carbon::now());
        });
    }

    public function bitemporalDelete($validAt = null, $transactionAt = null): bool
    {
        if (! $this->exists) {
            return false;
        }

        $validAt = $validAt instanceof CarbonInterface
            ? Carbon::instance($validAt)
            : Carbon::parse($validAt ?? now());

        $transactionAt = $transactionAt instanceof CarbonInterface
            ? Carbon::instance($transactionAt)
            : Carbon::parse($transactionAt ?? now());

        $clone = $this->replicate();
        $clone->forceFill([
            'valid_to' => $validAt,
            'transaction_from' => $transactionAt,
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $this->forceFill([
            'transaction_to' => $validAt,
        ]);

        if (! $this->save()) {
            return false;
        }

        return $clone->save();
    }

    public static function destroy($ids): int
    {
        $ids = is_array($ids) ? $ids : func_get_args();
        $ids = array_values(array_unique(array_filter(Arr::flatten($ids))));

        if (empty($ids)) {
            return 0;
        }

        $model = new static;

        return DB::connection($model->getConnectionName())->transaction(function () use ($model, $ids) {
            $models = $model->newQueryWithoutScopes()->whereKey($ids)->get();

            foreach ($models as $record) {
                $record->hardDelete();
            }

            return $models->count();
        });
    }

    public function hardDelete(): bool|null
    {
        if (! $this->exists) {
            return false;
        }

        return (bool) $this->newQueryWithoutScopes()
            ->toBase()
            ->where($this->getQualifiedKeyName(), $this->getKey())
            ->delete();
    }
}
