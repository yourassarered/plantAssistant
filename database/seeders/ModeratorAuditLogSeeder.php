<?php

namespace Database\Seeders;

use App\Models\ModeratorAuditLog;
use App\Models\Plant;
use App\Models\Report;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Database\Seeder;

class ModeratorAuditLogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::whereHas('role', fn ($query) => $query->where('name', 'admin'))->first();
        $reports = Report::with(['reporter', 'reviewer'])->whereNotNull('reviewed_at')->orderBy('id')->get();

        if (! $admin || $reports->isEmpty()) {
            return;
        }

        foreach ($reports as $report) {
            ModeratorAuditLog::firstOrCreate(
                [
                    'actor_id' => $admin->id,
                    'action' => 'report.review',
                    'target_type' => Report::class,
                    'target_id' => $report->id,
                ],
                [
                    'payload' => [
                        'status' => $report->status,
                        'reason' => $report->reason,
                        'resolution_action' => $report->resolution_action,
                        'report_target_type' => $report->target_type,
                        'report_target_id' => $report->target_id,
                        'reporter_email' => $report->reporter?->email,
                    ],
                    'ip' => '127.0.0.1',
                    'user_agent' => 'DatabaseSeeder/1.0',
                ]
            );

            if ($report->status !== 'accepted' || ! $report->resolution_action) {
                continue;
            }

            $targetClass = $report->target_type === Report::TARGET_PLANT ? Plant::class : Tip::class;
            ModeratorAuditLog::firstOrCreate(
                [
                    'actor_id' => $admin->id,
                    'action' => 'moderation.apply_resolution',
                    'target_type' => $targetClass,
                    'target_id' => $report->target_id,
                ],
                [
                    'payload' => [
                        'report_id' => $report->id,
                        'resolution_action' => $report->resolution_action,
                        'resolution_summary' => $report->resolution_summary,
                    ],
                    'ip' => '127.0.0.1',
                    'user_agent' => 'DatabaseSeeder/1.0',
                ]
            );
        }
    }
}
