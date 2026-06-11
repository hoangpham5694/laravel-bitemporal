# Laravel Bitemporal

Laravel package for working with bitemporal records in Eloquent models.

It provides:
- a migration macro to add bitemporal columns
- a reusable `HasBitemporal` trait for models
- a custom Eloquent builder for bitemporal queries
- a `delete($validAt = null)` method that versions records instead of hard-deleting them

## Requirements

- PHP 8.0+
- Laravel 9+

## Installation

Install the package in your Laravel app:

```bash
composer require hoangphamdev/laravel-bitemporal
```

If you are developing locally with a path repository, make sure the package is registered in your app's `composer.json`.

### Package discovery

The package ships with Laravel auto-discovery, so the service provider is loaded automatically.

## Migration usage

Use the `bitemporal()` macro in your migrations:

```php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->timestamps();
    $table->bitemporal();
});
```

This creates these columns:
- `valid_from`
- `valid_to`
- `transaction_from`
- `transaction_to`

Default values:
- `valid_from` = current datetime
- `transaction_from` = current datetime
- `valid_to` = `9999-12-31 23:59:59`
- `transaction_to` = `9999-12-31 23:59:59`

Rollback helper:

```php
Schema::table('posts', function (Blueprint $table) {
    $table->dropBitemporal();
});
```

## Model usage

Add the trait to any Eloquent model:

```php
use Illuminate\Database\Eloquent\Model;
use HoangPhamDev\Bitemporal\Traits\HasBitemporal;

class Post extends Model
{
    use HasBitemporal;
}
```

### What the trait adds

- automatic global scope for the current bitemporal snapshot
- a custom Eloquent builder
- helper scopes
- a bitemporal-aware `delete()` implementation

## Querying

### Current snapshot

By default, models using `HasBitemporal` are automatically scoped to the current snapshot.

```php
$posts = Post::all();
```

You can also call the explicit scope:

```php
$posts = Post::current()->get();
```

### Query as of a specific time

```php
$posts = Post::asOf('2025-01-01 00:00:00')->get();
```

You can also provide both valid time and transaction time:

```php
$posts = Post::asOf('2025-01-01 00:00:00', '2025-01-10 09:00:00')->get();
```

### Disable bitemporal filtering

To query all rows without the automatic bitemporal scope:

```php
$posts = Post::withoutBitemporal()->get();
```

Or directly:

```php
$posts = Post::withoutGlobalScope('bitemporal_current')->get();
```

### Find helpers

The custom builder keeps `find`-style methods bitemporal-aware:

```php
Post::find(1);
Post::findMany([1, 2]);
Post::findOrFail(1);
Post::findOrNew(1);
Post::findOr(1, fn () => null);
```

## Bitemporal delete

The trait overrides `delete()` to version the record instead of physically removing it.

```php
$post = Post::find(1);

$post->delete();
```

You can also pass a valid time:

```php
$post->delete('2025-01-08 00:00:00');
```

Behavior:
- the current record is closed by setting `transaction_to`
- a cloned record is created
- the clone gets:
  - `valid_to = validAt`
  - `transaction_from = now()`
  - `transaction_to = infinity datetime`

## Hard delete

If you want to physically remove the row without bitemporal versioning, use `hardDelete()`:

```php
$post = Post::find(1);

$post->hardDelete();
```

This bypasses the bitemporal versioning logic and deletes the row from the database.

## Infinity datetime constant

Use the shared constant when you need the package-wide infinity value:

```php
use HoangPhamDev\Bitemporal\Support\BitemporalDefaults;

$value = BitemporalDefaults::INFINITY_DATETIME;
```

## Testing

Run the package test suite:

```bash
vendor/bin/phpunit -c phpunit.xml.dist
```

## License

MIT
