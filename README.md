# Laravel Bitemporal

Laravel package for working with bitemporal records in Eloquent models.

The package provides:
- a `bitemporal()` migration macro
- a reusable `HasBitemporal` trait
- a custom Eloquent builder for current and historical queries
- helper methods for versioned updates and deletes

## Requirements

- PHP 8.0+
- Laravel 9+

## Installation

```bash
composer require hoangphamdev/laravel-bitemporal
```

If you are developing locally with a path repository, make sure the package is registered in your application's `composer.json`.

The package uses Laravel package discovery, so the service provider is loaded automatically.

## Database Columns

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

This macro adds these columns:
- `record_uuid`
- `operated_at`
- `valid_from`
- `valid_to`
- `transaction_from`
- `transaction_to`

Defaults created by the migration macro:
- `record_uuid` is nullable
- `operated_at` uses the current timestamp
- `valid_from` uses the current timestamp
- `valid_to` defaults to `9999-12-31 23:59:59`
- `transaction_from` uses the current timestamp
- `transaction_to` defaults to `9999-12-31 23:59:59`

### Column Purpose

- `record_uuid` groups all versions of the same logical record
- `operated_at` stores when the change was made
- `valid_from` and `valid_to` define business validity
- `transaction_from` and `transaction_to` define system/transaction validity

Rollback helper:

```php
Schema::table('posts', function (Blueprint $table) {
    $table->dropBitemporal();
});
```

## Model Usage

Add the trait to any Eloquent model:

```php
use Illuminate\Database\Eloquent\Model;
use HoangPhamDev\Bitemporal\Traits\HasBitemporal;

class Post extends Model
{
    use HasBitemporal;
}
```

### What the Trait Adds

- a global scope that returns only the current snapshot
- helper scopes for current, `asOf()`, and unscoped access
- a custom Eloquent builder with bitemporal-aware query helpers
- `bitemporalDelete($validAt = null)` for versioned deletes
- `bitemporalUpdate(array $data = [], $validAt = null)` for versioned updates
- automatic `record_uuid` generation on create when the field is empty
- automatic `operated_at` initialization on create when the field is empty

### Static Helpers

The trait also exposes:

```php
Post::getBitemporalColumns();
Post::getInfinityDatetime();
```

## Querying

### Current Snapshot

By default, models using `HasBitemporal` are automatically scoped to the current snapshot.

```php
$posts = Post::all();
```

You can also call the explicit scope:

```php
$posts = Post::current()->get();
```

The custom builder also exposes:

```php
$posts = Post::query()->current()->get();
```

### Query As Of a Specific Time

```php
$posts = Post::asOf('2025-01-01 00:00:00')->get();
```

`asOf()` filters by valid time and still requires `transaction_to` to be the infinity datetime.

If a model is retrieved with `asOf()`, that valid time is stored on the instance and later used by `bitemporalDelete()` when no explicit valid time is provided.

### Access Historical Rows

The custom builder exposes `history()` as a convenience clone of the current builder state, useful when you want to branch a query without mutating the original builder.

```php
$query = Post::withoutBitemporal()->history();
```

### Disable Bitemporal Filtering

To query all rows without the automatic current-snapshot scope:

```php
$posts = Post::withoutBitemporal()->get();
```

Or directly:

```php
$posts = Post::withoutGlobalScope('bitemporal_current')->get();
```

### Find Helpers

The custom builder keeps `find`-style methods bitemporal-aware:

```php
Post::find(1);
Post::findMany([1, 2]);
Post::findOrFail(1);
Post::findOrNew(1);
Post::findOr(1, fn () => null);
Post::findSole(1);
```

`touch()` is also wrapped to run on the matched models inside a transaction.

## Versioned Update

`bitemporalUpdate()` closes the current version and creates a new version with updated data.

```php
$post = Post::find(1);

$post->bitemporalUpdate([
    'title' => 'Published title',
]);
```

You can pass an explicit valid time:

```php
$post->bitemporalUpdate([
    'title' => 'Published title',
], '2025-01-08 00:00:00');
```

Behavior:
- the current version is closed by setting `transaction_to`
- matching rows for the same `record_uuid` and later valid windows are also closed
- a new cloned record is inserted
- the new record gets:
  - `record_uuid` copied from the original
  - `operated_at = now()`
  - `valid_from = validAt`
  - `valid_to = infinity datetime`
  - `transaction_from = now()`
  - `transaction_to = infinity datetime`

If the model does not exist or does not have a `record_uuid`, the method returns `false`.

## Versioned Delete

`bitemporalDelete($validAt = null)` versions the record instead of physically removing it.

```php
$post = Post::find(1);

$post->bitemporalDelete();
```

You can also pass a valid time:

```php
$post->bitemporalDelete('2025-01-08 00:00:00');
```

Behavior:
- the trait resolves the record's `record_uuid`
- the current version is closed by setting `transaction_to`
- a cloned record is created with a truncated `valid_to`
- the clone gets:
  - `valid_to = validAt`
  - `transaction_from = now()`
  - `transaction_to = infinity datetime`

If the model was loaded with `asOf()`, the stored valid time is used automatically when you call `bitemporalDelete()` without arguments.

If the model does not exist, `bitemporalDelete()` returns `false`.

## Physical Delete

If you want to physically remove rows without bitemporal versioning, use normal Eloquent delete methods:

```php
$post = Post::find(1);

$post->delete();
```

Bulk deletes also remain physical deletes:

```php
Post::withoutBitemporal()->whereIn('id', [1, 2])->delete();
Post::destroy([1, 2]);
Post::withoutBitemporal()->whereIn('id', [1, 2])->toBase()->delete();
```

These bypass the versioning logic entirely.

## Infinity Datetime Constant

Use the shared constant when you need the package-wide infinity value:

```php
use HoangPhamDev\Bitemporal\Support\BitemporalDefaults;

$value = BitemporalDefaults::INFINITY_DATETIME;
```

## Example Model

```php
use HoangPhamDev\Bitemporal\Traits\HasBitemporal;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasBitemporal;

    protected $fillable = [
        'title',
        'valid_from',
        'valid_to',
        'transaction_from',
        'transaction_to',
    ];

    protected $casts = [
        'operated_at' => 'datetime',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'transaction_from' => 'datetime',
        'transaction_to' => 'datetime',
    ];
}
```

## Testing

Run the package test suite:

```bash
vendor/bin/phpunit -c phpunit.xml.dist
```

## License

MIT
