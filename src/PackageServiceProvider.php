<?php
namespace HoangPhamDev\Bitemporal;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
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
            $this->dateTimeTz('valid_from')->useCurrent();
            $this->dateTimeTz('valid_to')->default(BitemporalDefaults::INFINITY_DATETIME);
            $this->dateTimeTz('transaction_from')->useCurrent();
            $this->dateTimeTz('transaction_to')->default(BitemporalDefaults::INFINITY_DATETIME);
        });

        Blueprint::macro('dropBitemporal', function (): void {
            /** @var \Illuminate\Database\Schema\Blueprint $this */
            $this->dropColumn([
                'valid_from',
                'valid_to',
                'transaction_from',
                'transaction_to',
            ]);
        });
    }

    protected function consoleConfiguration(): void
    {
        if ($this->app->runningInConsole()) {
        }
    }
}
