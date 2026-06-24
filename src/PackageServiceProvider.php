<?php
namespace HoangPhamDev\Bitemporal;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use HoangPhamDev\Bitemporal\Support\BitemporalDefaults;

class PackageServiceProvider extends ServiceProvider
{
    public function register()
    {
    }

    public function boot()
    {
        Blueprint::macro('bitemporal', function (): void {
            /** @var \Illuminate\Database\Schema\Blueprint $this */
            $this->uuid('record_uuid')->nullable();
            $this->dateTimeTz('operated_at')->useCurrent();
            $this->dateTimeTz('valid_from')->useCurrent();
            $this->dateTimeTz('valid_to')->default(BitemporalDefaults::INFINITY_DATETIME);
            $this->dateTimeTz('transaction_from')->useCurrent();
            $this->dateTimeTz('transaction_to')->default(BitemporalDefaults::INFINITY_DATETIME);
        });

        Blueprint::macro('dropBitemporal', function (): void {
            /** @var \Illuminate\Database\Schema\Blueprint $this */
            $this->dropColumn([
                'record_uuid',
                'operated_at',
                'valid_from',
                'valid_to',
                'transaction_from',
                'transaction_to',
            ]);
        });
    }
}
