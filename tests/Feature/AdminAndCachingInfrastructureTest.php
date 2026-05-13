<?php

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\Report;
use App\Models\Role;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAndCachingInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_cache_is_invalidated_after_plant_changes(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();

        $oldPlant = Plant::create([
            'name' => 'Old plant',
            'planted_at' => now()->subDays(20),
            'is_public' => true,
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($viewer);

        $first = $this->getJson('/api/feed')->assertOk()->json('data');
        $firstIds = collect($first)->pluck('id')->all();
        $this->assertContains($oldPlant->id, $firstIds);

        $newPlant = Plant::create([
            'name' => 'New plant',
            'planted_at' => now()->subDays(2),
            'is_public' => true,
            'user_id' => $owner->id,
        ]);

        $second = $this->getJson('/api/feed')->assertOk()->json('data');
        $secondIds = collect($second)->pluck('id')->all();

        $this->assertContains($newPlant->id, $secondIds);
    }

    public function test_admin_review_creates_audit_log_entry(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $owner = User::factory()->create();
        $tipAuthor = User::factory()->create();
        $reporter = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $plant = Plant::create([
            'name' => 'Calathea',
            'planted_at' => now()->subMonth(),
            'is_public' => true,
            'user_id' => $owner->id,
        ]);

        $tip = Tip::create([
            'plant_id' => $plant->id,
            'author_id' => $tipAuthor->id,
            'content' => 'Some content',
            'status' => 'accepted',
        ]);

        Sanctum::actingAs($reporter);
        $reportId = $this->postJson("/api/tips/{$tip->id}/reports", [
            'reason' => 'misinformation',
            'details' => 'Not safe advice',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($admin);
        $this->putJson("/api/admin/reports/{$reportId}/review", [
            'status' => 'accepted',
            'admin_comment' => 'Confirmed',
        ])->assertOk();

        $this->assertDatabaseHas('moderator_audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'report.review',
            'target_type' => Report::class,
            'target_id' => $reportId,
        ]);
    }

    public function test_admin_routes_are_rate_limited(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/admin/reports')->assertOk();
        }

        $this->getJson('/api/admin/reports')->assertStatus(429);
    }
}
