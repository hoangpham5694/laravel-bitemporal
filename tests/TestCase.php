<?php

namespace HoangPhamDev\Bitemporal\Tests;

use HoangPhamDev\Bitemporal\PackageServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PackageServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'mysql',
            'host' => 'mysql',
            'port' => '3306',
            'database' => 'bitemporal_test',
            'username' => 'root',
            'password' => 'password',
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('posts');
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
            $table->bitemporal();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('posts');
        \Carbon\Carbon::setTestNow();

        parent::tearDown();
    }
}
