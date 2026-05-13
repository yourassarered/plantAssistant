<?php

namespace App\DTO;

use App\Http\Requests\Api\PlantIndexRequest;

readonly class PlantFiltersData
{
    public function __construct(
        public ?int $roomId,
        public ?bool $isPublic,
        public ?string $search,
        public string $sortBy,
        public string $sortOrder,
        public int $perPage,
    ) {}

    public static function fromRequest(PlantIndexRequest $request): self
    {
        return new self(
            roomId: $request->integer('room_id') ?: null,
            isPublic: $request->has('is_public') ? $request->boolean('is_public') : null,
            search: $request->input('search'),
            sortBy: $request->input('sort_by', 'created_at'),
            sortOrder: $request->input('sort_order', 'desc'),
            perPage: $request->integer('per_page', 15),
        );
    }
}
