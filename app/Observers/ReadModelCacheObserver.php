<?php

namespace App\Observers;

use App\Services\TaggedCacheService;
use Illuminate\Database\Eloquent\Model;

class ReadModelCacheObserver
{
    public function __construct(private readonly TaggedCacheService $cache) {}

    public function created(Model $model): void
    {
        $this->flush();
    }

    public function updated(Model $model): void
    {
        $this->flush();
    }

    public function deleted(Model $model): void
    {
        $this->flush();
    }

    public function restored(Model $model): void
    {
        $this->flush();
    }

    private function flush(): void
    {
        $this->cache->flushTags(['feed', 'dashboard']);
    }
}
