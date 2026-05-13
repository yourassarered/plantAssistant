<?php

namespace App\Services;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

class TaggedCacheService
{
    private const VERSION_PREFIX = 'cache_tag_version:';

    public function remember(array $tags, string $key, int $ttlSeconds, callable $callback): mixed
    {
        if ($this->supportsTags()) {
            return Cache::tags($tags)->remember($key, $ttlSeconds, $callback);
        }

        $versionedKey = $this->buildVersionedKey($tags, $key);

        return Cache::remember($versionedKey, $ttlSeconds, $callback);
    }

    public function flushTags(array $tags): void
    {
        if ($this->supportsTags()) {
            Cache::tags($tags)->flush();

            return;
        }

        foreach ($tags as $tag) {
            Cache::increment($this->versionKey($tag));
        }
    }

    private function supportsTags(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }

    private function buildVersionedKey(array $tags, string $key): string
    {
        $parts = [];
        foreach ($tags as $tag) {
            $parts[] = $tag.':'.$this->tagVersion($tag);
        }

        return implode('|', $parts).'|'.$key;
    }

    private function tagVersion(string $tag): int
    {
        return (int) Cache::get($this->versionKey($tag), 1);
    }

    private function versionKey(string $tag): string
    {
        return self::VERSION_PREFIX.$tag;
    }
}
