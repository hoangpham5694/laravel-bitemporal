<?php

namespace HoangPhamDev\Bitemporal\Traits;

use Carbon\Carbon;
use HoangPhamDev\Bitemporal\Builders\BitemporalBuilder;
use HoangPhamDev\Bitemporal\Support\BitemporalDefaults;
use Illuminate\Database\Eloquent\Builder;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

trait HasBitemporal
{
    protected ?CarbonInterface $bitemporalValidAt = null;

    public static function getBitemporalColumns(): array
    {
        return [
            'record_uuid',
            'operated_at',
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

        static::creating(function ($model): void {
            if (empty($model->record_uuid)) {
                $model->record_uuid = (string) Str::uuid();
            }

            if (empty($model->operated_at)) {
                $model->operated_at = now();
            }
        });
    }

    public function scopeCurrent(Builder $query)
    {
        return $query->applyCurrentSnapshot();
    }

    public function scopeAsOf(Builder $query, $validAt = null)
    {
        return $query->asOf($validAt);
    }

    public function scopeWithoutBitemporal(Builder $query)
    {
        return $query->withoutGlobalScope('bitemporal_current');
    }

    public function newEloquentBuilder($query): BitemporalBuilder
    {
        return new BitemporalBuilder($query);
    }

    public function setBitemporalValidAt($validAt): static
    {
        if ($validAt instanceof CarbonInterface) {
            $this->bitemporalValidAt = Carbon::instance($validAt);

            return $this;
        }

        if (is_null($validAt)) {
            $this->bitemporalValidAt = null;

            return $this;
        }

        $timezone = config('app.timezone', date_default_timezone_get());
        $this->bitemporalValidAt = Carbon::parse($validAt, $timezone);

        return $this;
    }

    public function getBitemporalValidAt(): ?CarbonInterface
    {
        return $this->bitemporalValidAt;
    }

    public function bitemporalDelete($validAt = null): bool
    {
        if (! $this->exists) {
            return false;
        }

        $validAt = $validAt ?? $this->getBitemporalValidAt() ?? now();

        return $this->bitemporalDeleteSince(
            (string) $this->getAttribute('record_uuid'),
            $validAt
        );
    }

    public function bitemporalUpdate(array $data = [], $validAt = null): bool
    {
        if (! $this->exists) {
            return false;
        }

        $recordUuid = (string) $this->getAttribute('record_uuid');

        if ($recordUuid === '') {
            return false;
        }

        $timezone = config('app.timezone', date_default_timezone_get());
        $validAt = $validAt instanceof CarbonInterface
            ? Carbon::instance($validAt)->setTimezone($timezone)
            : Carbon::parse($validAt ?? now(), $timezone);

        if ($this->bitemporalDeleteSince($recordUuid, $validAt) === false) {
            return false;
        }

        $record = $this->newInstance();
        $record->forceFill(array_merge([
            'record_uuid' => $recordUuid,
            'operated_at' => now(),
            'valid_from' => $validAt,
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => now(),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ], $data));

        return $record->save();
    }

    protected function bitemporalDeleteSince(string $recordUuid, $validAt = null): bool
    {
        $timezone = config('app.timezone', date_default_timezone_get());
        $validAt = $validAt instanceof CarbonInterface
            ? Carbon::instance($validAt)->setTimezone($timezone)
            : Carbon::parse($validAt ?? now(), $timezone);

        $transactionAt = now();

        return DB::connection($this->getConnectionName())->transaction(function () use ($recordUuid, $validAt, $transactionAt) {
            $currentRecord = $this->newQueryWithoutScopes()
                ->where('record_uuid', $recordUuid)
                ->where('valid_from', '<=', $validAt)
                ->where('valid_to', '>', $validAt)
                ->where('transaction_to', BitemporalDefaults::INFINITY_DATETIME)
                ->orderByDesc('valid_from')
                ->first();

            if (is_null($currentRecord)) {
                return false;
            }

            $recordsToClose = $this->newQueryWithoutScopes()
                ->where('record_uuid', $recordUuid)
                ->where('valid_to', '>', $validAt)
                ->where('transaction_to', BitemporalDefaults::INFINITY_DATETIME)
                ->get();

            foreach ($recordsToClose as $record) {
                $record->newQueryWithoutScopes()
                    ->whereKey($record->getKey())
                    ->toBase()
                    ->update([
                        'transaction_to' => $transactionAt,
                    ]);
            }

            $clone = $currentRecord->replicate();
            $clone->forceFill([
                'valid_to' => $validAt,
                'transaction_from' => $transactionAt,
                'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
            ]);

            return $clone->save();
        });
    }

}
