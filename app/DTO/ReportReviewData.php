<?php

namespace App\DTO;

use App\Http\Requests\Api\ReviewReportRequest;

readonly class ReportReviewData
{
    public function __construct(
        public string $status,
        public ?string $adminComment,
        public ?string $resolutionAction,
    ) {}

    public static function fromRequest(ReviewReportRequest $request): self
    {
        return new self(
            status: $request->string('status')->value(),
            adminComment: $request->input('admin_comment'),
            resolutionAction: $request->input('resolution_action'),
        );
    }
}
