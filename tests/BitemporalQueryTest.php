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
        Carbon::setTestNow(Carbon::parse('2025-01-10 09:00:00'));

        Post::query()->withoutBitemporal()->create([
            'title' => 'version-1',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => Carbon::parse('2025-01-05 00:00:00'),
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        Post::query()->withoutBitemporal()->create([
            'title' => 'version-2',
            'valid_from' => Carbon::parse('2025-01-05 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-05 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $result = Post::withoutBitemporal()
            ->asOf('2025-01-06 00:00:00')
            ->pluck('title')
            ->all();

        $this->assertSame(['version-2'], $result);
    }

    public function test_as_of_normalizes_timezone_before_querying(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-10 09:00:00', 'UTC'));

        Post::query()->withoutBitemporal()->create([
            'title' => 'timezone-sensitive',
            'valid_from' => Carbon::parse('2025-01-05 04:00:00', 'UTC'),
            'valid_to' => Carbon::parse('2025-01-05 06:00:00', 'UTC'),
            'transaction_from' => Carbon::parse('2025-01-05 04:00:00', 'UTC'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $titles = Post::withoutBitemporal()
            ->asOf(Carbon::parse('2025-01-05 12:00:00', 'Asia/Ho_Chi_Minh'))
            ->pluck('title')
            ->all();

        $this->assertSame(['timezone-sensitive'], $titles);
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
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        Post::query()->withoutBitemporal()->create([
            'title' => 'version-2',
            'valid_from' => Carbon::parse('2025-01-05 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-05 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $titles = Post::withoutBitemporal()
            ->asOf('2025-01-02 00:00:00')
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

    public function test_record_uuid_is_generated_when_missing_on_create(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-10 12:00:00'));

        $post = Post::query()->withoutBitemporal()->create([
            'title' => 'current',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $this->assertNotNull($post->record_uuid);
        $this->assertSame(36, strlen($post->record_uuid));
    }

    public function test_model_update_uses_default_eloquent_behavior(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-10 12:00:00'));

        $post = Post::query()->withoutBitemporal()->create([
            'title' => 'draft',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        Carbon::setTestNow(Carbon::parse('2025-01-11 09:30:00'));

        $this->assertTrue($post->update([
            'title' => 'published',
        ]));

        $rows = Post::withoutBitemporal()->orderBy('id')->get();

        $this->assertCount(1, $rows);
        $this->assertSame('published', $rows->first()->title);
        $this->assertSame(['published'], Post::pluck('title')->all());
    }

    public function test_builder_update_uses_default_eloquent_behavior(): void
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

        Carbon::setTestNow(Carbon::parse('2025-01-11 09:30:00'));

        $updated = Post::withoutBitemporal()
            ->whereIn('id', [$first->id, $second->id])
            ->update([
                'title' => 'published',
            ]);

        $this->assertSame(2, $updated);
        $this->assertCount(2, Post::withoutBitemporal()->get());
        $this->assertSame(['published', 'published'], Post::pluck('title')->sort()->values()->all());
    }

    public function test_builder_update_returns_zero_when_no_records_match(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-10 12:00:00'));

        Post::query()->withoutBitemporal()->create([
            'title' => 'existing',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
        ]);

        $updated = Post::withoutBitemporal()
            ->whereKey(999999)
            ->update([
                'title' => 'published',
            ]);

        $this->assertSame(0, $updated);
        $this->assertSame(['existing'], Post::pluck('title')->all());
    }

    public function test_delete_closes_current_record_and_creates_new_version(): void
    {
        $now = Carbon::parse('2025-01-10 12:00:00');
        Carbon::setTestNow($now);

        $recordUuid = '22222222-2222-2222-2222-222222222222';

        $post = Post::query()->withoutBitemporal()->forceCreate([
            'record_uuid' => $recordUuid,
            'title' => 'post',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
            'operated_at' => Carbon::parse('2025-01-01 00:00:00'),
        ]);

        $post->bitemporalDelete('2025-01-08 00:00:00');

        $rows = Post::withoutBitemporal()->orderBy('id')->get();

        $this->assertCount(2, $rows);
        $this->assertSame('2025-01-10 12:00:00', $rows->first()->transaction_to->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-10 12:00:00', $rows->last()->transaction_from->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-08 00:00:00', $rows->last()->valid_to->format('Y-m-d H:i:s'));
        $this->assertSame(BitemporalDefaults::INFINITY_DATETIME, $rows->last()->transaction_to->format('Y-m-d H:i:s'));

        $this->assertCount(0, Post::query()->get());
    }

    public function test_bitemporal_delete_since_closes_matching_versions_and_truncates_current_snapshot(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-10 12:00:00'));

        $recordUuid = '11111111-1111-1111-1111-111111111111';

        Post::query()->withoutBitemporal()->forceCreate([
            'record_uuid' => $recordUuid,
            'title' => 'version-1',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => Carbon::parse('2025-01-10 00:00:00'),
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
            'operated_at' => Carbon::parse('2025-01-01 00:00:00'),
        ]);

        Post::query()->withoutBitemporal()->forceCreate([
            'record_uuid' => $recordUuid,
            'title' => 'version-2',
            'valid_from' => Carbon::parse('2025-01-10 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-05 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
            'operated_at' => Carbon::parse('2025-01-05 00:00:00'),
        ]);

        $deleted = $this->callProtectedMethod(new Post, 'bitemporalDeleteSince', [
            $recordUuid,
            '2025-01-05 12:00:00',
        ]);

        $this->assertTrue($deleted);

        $rows = Post::withoutBitemporal()
            ->where('record_uuid', $recordUuid)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $rows);
        $this->assertSame('version-1', $rows[0]->title);
        $this->assertSame('2025-01-10 12:00:00', $rows[0]->transaction_to->format('Y-m-d H:i:s'));
        $this->assertSame('version-2', $rows[1]->title);
        $this->assertSame('2025-01-10 12:00:00', $rows[1]->transaction_to->format('Y-m-d H:i:s'));
        $this->assertSame('version-1', $rows[2]->title);
        $this->assertSame('2025-01-05 12:00:00', $rows[2]->valid_to->format('Y-m-d H:i:s'));
        $this->assertSame(BitemporalDefaults::INFINITY_DATETIME, $rows[2]->transaction_to->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-01 00:00:00', $rows[2]->operated_at->format('Y-m-d H:i:s'));

        $this->assertNull(Post::withoutBitemporal()
            ->where('record_uuid', $recordUuid)
            ->current()
            ->first());

        $this->assertSame(['version-1'], Post::withoutBitemporal()
            ->where('record_uuid', $recordUuid)
            ->asOf('2025-01-04 12:00:00')
            ->pluck('title')
            ->all());
    }

    public function test_bitemporal_update_replaces_current_version_with_updated_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-10 12:00:00'));

        $recordUuid = '33333333-3333-3333-3333-333333333333';

        $post = Post::query()->withoutBitemporal()->forceCreate([
            'record_uuid' => $recordUuid,
            'title' => 'draft',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
            'operated_at' => Carbon::parse('2025-01-01 00:00:00'),
        ]);

        $this->assertTrue($post->bitemporalUpdate([
            'title' => 'published',
        ], '2025-01-08 00:00:00'));

        $rows = Post::withoutBitemporal()
            ->where('record_uuid', $recordUuid)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $rows);
        $this->assertSame('draft', $rows[0]->title);
        $this->assertSame('2025-01-10 12:00:00', $rows[0]->transaction_to->format('Y-m-d H:i:s'));
        $this->assertSame('draft', $rows[1]->title);
        $this->assertSame('2025-01-08 00:00:00', $rows[1]->valid_to->format('Y-m-d H:i:s'));
        $this->assertSame('published', $rows[2]->title);
        $this->assertSame('2025-01-08 00:00:00', $rows[2]->valid_from->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-10 12:00:00', $rows[2]->transaction_from->format('Y-m-d H:i:s'));
        $this->assertSame(BitemporalDefaults::INFINITY_DATETIME, $rows[2]->transaction_to->format('Y-m-d H:i:s'));
        $this->assertSame(['published'], Post::withoutBitemporal()
            ->where('record_uuid', $recordUuid)
            ->current()
            ->pluck('title')
            ->all());
    }

    public function test_bitemporal_update_defaults_valid_at_to_now(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-10 12:00:00'));

        $recordUuid = '44444444-4444-4444-4444-444444444444';

        $post = Post::query()->withoutBitemporal()->forceCreate([
            'record_uuid' => $recordUuid,
            'title' => 'draft',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
            'operated_at' => Carbon::parse('2025-01-01 00:00:00'),
        ]);

        Carbon::setTestNow(Carbon::parse('2025-01-11 09:30:00'));

        $this->assertTrue($post->bitemporalUpdate([
            'title' => 'published',
        ]));

        $rows = Post::withoutBitemporal()
            ->where('record_uuid', $recordUuid)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $rows);
        $this->assertSame('2025-01-11 09:30:00', $rows[2]->valid_from->format('Y-m-d H:i:s'));
        $this->assertSame('published', $rows[2]->title);
    }

    public function test_as_of_returns_historical_version_after_record_was_updated(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-10 12:00:00'));

        $recordUuid = '55555555-5555-5555-5555-555555555555';

        $post = Post::query()->withoutBitemporal()->forceCreate([
            'record_uuid' => $recordUuid,
            'title' => 'draft',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => BitemporalDefaults::INFINITY_DATETIME,
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
            'operated_at' => Carbon::parse('2025-01-01 00:00:00'),
        ]);

        $this->assertTrue($post->bitemporalUpdate([
            'title' => 'published',
        ], '2025-01-08 00:00:00'));

        $historical = Post::query()
            ->asOf('2025-01-05 12:00:00')
            ->where('record_uuid', $recordUuid)
            ->first();

        $current = Post::query()
            ->asOf('2025-01-09 12:00:00')
            ->where('record_uuid', $recordUuid)
            ->first();

        $this->assertNotNull($historical);
        $this->assertSame('draft', $historical->title);
        $this->assertNotNull($current);
        $this->assertSame('published', $current->title);
    }

    public function test_bitemporal_delete_defaults_to_as_of_valid_at_when_model_is_loaded_from_as_of_query(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-10 12:00:00'));

        $recordUuid = '77777777-7777-7777-7777-777777777777';

        Post::query()->withoutBitemporal()->forceCreate([
            'record_uuid' => $recordUuid,
            'title' => 'draft',
            'valid_from' => Carbon::parse('2025-01-01 00:00:00'),
            'valid_to' => Carbon::parse('2025-01-20 00:00:00'),
            'transaction_from' => Carbon::parse('2025-01-01 00:00:00'),
            'transaction_to' => BitemporalDefaults::INFINITY_DATETIME,
            'operated_at' => Carbon::parse('2025-01-01 00:00:00'),
        ]);

        $post = Post::query()
            ->asOf('2025-01-05 12:00:00')
            ->where('record_uuid', $recordUuid)
            ->first();

        $this->assertNotNull($post);
        $this->assertTrue($post->bitemporalDelete());

        $rows = Post::withoutBitemporal()
            ->where('record_uuid', $recordUuid)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame('2025-01-05 12:00:00', $rows->last()->valid_to->format('Y-m-d H:i:s'));
        $this->assertSame('2025-01-10 12:00:00', $rows->last()->transaction_from->format('Y-m-d H:i:s'));
    }

    public function test_query_delete_removes_multiple_records_physically(): void
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
        $this->assertCount(0, Post::withoutBitemporal()->get());
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

        $this->assertTrue($post->delete());
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

    public function test_base_query_delete_removes_multiple_records_physically(): void
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
            ->toBase()
            ->delete();

        $this->assertSame(2, $deleted);
        $this->assertCount(0, Post::withoutBitemporal()->get());
    }

    private function callProtectedMethod(object $object, string $method, array $arguments = [])
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
