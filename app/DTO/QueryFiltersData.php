<?php

namespace App\DTO;

use App\Http\Requests\Api\DashboardQueryRequest;
use App\Http\Requests\Api\FeedQueryRequest;

readonly class QueryFiltersData
{
    public function __construct(
        public ?string $search,
        public string $sortBy,
        public string $sortOrder,
        public int $perPage,
        public int $days,
    ) {}

    public static function fromFeedRequest(FeedQueryRequest $request): self
    {
        return new self(
            search: $request->input('search'),
            sortBy: $request->input('sort_by', 'created_at'),
            sortOrder: $request->input('sort_order', 'desc'),
            perPage: $request->integer('per_page', 15),
            days: $request->integer('days', 7),
        );
    }

    public static function fromDashboardRequest(DashboardQueryRequest $request): self
    {
        return new self(
            search: null,
            sortBy: 'created_at',
            sortOrder: 'desc',
            perPage: 15,
            days: $request->integer('days', 30),
        );
    }
}
