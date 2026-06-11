<?php

namespace HoangPhamDev\Bitemporal\Tests;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use HoangPhamDev\Bitemporal\Support\BitemporalDefaults;
use HoangPhamDev\Bitemporal\Tests\Models\Post;

class BitemporalQueryTest extends TestCase
{
    public function test_current_scope_filters_to_current_snapshot(): void
    {
        $now = Carbon::parse('2025-01-01 10:00:00');

        Carbon::setTestNow($now);

        Post::query()->withoutBitemporal()->create([
            'title' => 'current',
            'valid_from' => $now->copy()->subDay(),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => $now->copy()->subHour(),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        Post::query()->withoutBitemporal()->create([
            'title' => 'expired',
            'valid_from' => $now->copy()->subDays(10),
            'valid_to' => $now->copy()->subDay(),
            'transaction_from' => $now->copy()->subDays(10),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $titles = Post::query()->pluck('title')->all();

        $this->assertSame(['current'], $titles);
    }

    public function test_without_bitemporal_returns_all_records(): void
    {
        $now = Carbon::parse('2025-01-01 10:00:00');

        Carbon::setTestNow($now);

        Post::query()->withoutBitemporal()->create([
            'title' => 'current',
            'valid_from' => $now->copy()->subDay(),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => $now->copy()->subHour(),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        Post::query()->withoutBitemporal()->create([
            'title' => 'expired',
            'valid_from' => $now->copy()->subDays(10),
            'valid_to' => $now->copy()->subDay(),
            'transaction_from' => $now->copy()->subDays(10),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $titles = Post::withoutBitemporal()->orderBy('title')->pluck('title')->all();

        $this->assertSame(['current', 'expired'], $titles);
    }

    public function test_as_of_returns_expected_snapshot(): void
    {
        $transactionNow = Carbon::parse('2025-01-10 09:00:00');
        Carbon::setTestNow($transactionNow);

        Post::query()->withoutBitemporal()->create([
            'title' => 'version-1',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => Carbon::parse('2025-01-05 00:00:00'),
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => Carbon::parse('2025-01-03 00:00:00'),
        ]);

        Post::query()->withoutBitemporal()->create([
            'title' => 'version-2',
            'valid_from' => Carbon::parse('2025-01-05 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-03 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $result = Post::withoutBitemporal()
            ->asOf('2025-01-06 00:00:00', '2025-01-10 09:00:00')
            ->pluck('title')
            ->all();

        $this->assertSame(['version-2'], $result);
    }

    public function test_scope_current_applies_current_snapshot(): void
    {
        $now = Carbon::parse('2025-01-01 10:00:00');
        Carbon::setTestNow($now);

        Post::query()->withoutBitemporal()->create([
            'title' => 'current',
            'valid_from' => $now->copy()->subDay(),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => $now->copy()->subHour(),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        Post::query()->withoutBitemporal()->create([
            'title' => 'expired',
            'valid_from' => $now->copy()->subDays(10),
            'valid_to' => $now->copy()->subDay(),
            'transaction_from' => $now->copy()->subDays(10),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $titles = Post::withoutBitemporal()->current()->pluck('title')->all();

        $this->assertSame(['current'], $titles);
    }

    public function test_scope_as_of_returns_expected_snapshot(): void
    {
        Post::query()->withoutBitemporal()->create([
            'title' => 'version-1',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => Carbon::parse('2025-01-05 00:00:00'),
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => Carbon::parse('2025-01-03 00:00:00'),
        ]);

        Post::query()->withoutBitemporal()->create([
            'title' => 'version-2',
            'valid_from' => Carbon::parse('2025-01-05 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-03 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $titles = Post::withoutBitemporal()
            ->asOf('2025-01-02 00:00:00', '2025-01-02 00:00:00')
            ->pluck('title')
            ->all();

        $this->assertSame(['version-1'], $titles);
    }

    public function test_find_uses_current_snapshot(): void
    {
        $now = Carbon::parse('2025-01-01 10:00:00');
        Carbon::setTestNow($now);

        $post = Post::query()->withoutBitemporal()->create([
            'title' => 'current',
            'valid_from' => $now->copy()->subDay(),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => $now->copy()->subHour(),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $expired = Post::query()->withoutBitemporal()->create([
            'title' => 'expired',
            'valid_from' => $now->copy()->subDays(10),
            'valid_to' => $now->copy()->subDay(),
            'transaction_from' => $now->copy()->subDays(10),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $this->assertSame('current', Post::find($post->id)->title);
        $this->assertNull(Post::find($expired->id));
    }

    public function test_find_or_fail_returns_model_or_throws_when_missing(): void
    {
        $post = Post::query()->withoutBitemporal()->create([
            'title' => 'current',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $found = Post::findOrFail($post->id);

        $this->assertSame('current', $found->title);

        $this->expectException(ModelNotFoundException::class);

        Post::findOrFail(999999);
    }

    public function test_find_or_new_returns_existing_or_unsaved_model(): void
    {
        $post = Post::query()->withoutBitemporal()->create([
            'title' => 'current',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $found = Post::findOrNew($post->id);

        $this->assertTrue($found->exists);
        $this->assertSame('current', $found->title);

        $new = Post::findOrNew(999999);

        $this->assertFalse($new->exists);
        $this->assertNull($new->title);
    }

    public function test_find_or_uses_callback_when_missing(): void
    {
        $post = Post::query()->withoutBitemporal()->create([
            'title' => 'current',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $found = Post::findOr($post->id, function () {
            return 'fallback';
        });

        $this->assertSame('current', $found->title);

        $called = false;

        $fallback = Post::findOr(999999, function () use (&$called) {
            $called = true;

            return 'fallback';
        });

        $this->assertTrue($called);
        $this->assertSame('fallback', $fallback);
    }

    public function test_delete_closes_current_record_and_creates_new_version(): void
    {
        $now = Carbon::parse('2025-01-10 12:00:00');
        Carbon::setTestNow($now);

        $post = Post::query()->withoutBitemporal()->create([
            'title' => 'post',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $post->delete('2025-01-08 00:00:00');

        $rows = Post::withoutBitemporal()->orderBy('id')->get();

        $this->assertCount(2, $rows);
        $this->assertSame('2025-01-08 00:00:00', $rows->first()->transaction_to->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-10 12:00:00', $rows->last()->transaction_from->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-08 00:00:00', $rows->last()->valid_to->format('Y-m-d H:i:s'));
        $this->assertSame(BitemporalDefaults::INFINITY_DATETIME, $rows->last()->transaction_to->format('Y-m-d H:i:s'));

        $this->assertCount(0, Post::query()->get());
    }

    public function test_query_delete_versions_multiple_records_instead_of_physical_delete(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-10 12:00:00'));

        $first = Post::query()->withoutBitemporal()->create([
            'title' => 'first',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $second = Post::query()->withoutBitemporal()->create([
            'title' => 'second',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $deleted = Post::withoutBitemporal()
            ->whereIn('id', [$first->id, $second->id])
            ->delete();

        $this->assertSame(2, $deleted);
        $this->assertCount(4, Post::withoutBitemporal()->get());
        $this->assertCount(0, Post::query()->get());
    }

    public function test_hard_delete_removes_row_without_versioning(): void
    {
        $post = Post::query()->withoutBitemporal()->create([
            'title' => 'post',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $this->assertTrue($post->hardDelete());
        $this->assertCount(0, Post::withoutBitemporal()->get());
    }

    public function test_destroy_bulk_deletes_without_bitemporal_versioning(): void
    {
        $first = Post::query()->withoutBitemporal()->create([
            'title' => 'first',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $second = Post::query()->withoutBitemporal()->create([
            'title' => 'second',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $deleted = Post::destroy([$first->id, $second->id]);

        $this->assertSame(2, $deleted);
        $this->assertCount(0, Post::withoutBitemporal()->get());
    }

    public function test_query_hard_delete_removes_multiple_records_physically(): void
    {
        $first = Post::query()->withoutBitemporal()->create([
            'title' => 'first',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $second = Post::query()->withoutBitemporal()->create([
            'title' => 'second',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $deleted = Post::withoutBitemporal()
            ->whereIn('id', [$first->id, $second->id])
            ->hardDelete();

        $this->assertSame(2, $deleted);
        $this->assertCount(0, Post::withoutBitemporal()->get());
    }
}
