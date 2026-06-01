<?php

namespace Tests\Feature;

use App\Models\CareLog;
use App\Models\CareSetting;
use App\Models\Plant;
use App\Models\Role;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlantAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_plant_detail_does_not_grant_management_to_other_users(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $plant = Plant::create([
            'name' => 'Owner plant',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $owner->id,
        ]);

        CareLog::create([
            'plant_id' => $plant->id,
            'type' => 'watering',
            'performed_at' => now(),
        ]);
        Tip::create([
            'plant_id' => $plant->id,
            'author_id' => $viewer->id,
            'content' => 'Viewer tip',
            'status' => 'accepted',
        ]);

        $this->actingAs($viewer)
            ->getJson("/api/plants/{$plant->id}")
            ->assertOk()
            ->assertJsonPath('data.can_manage', false)
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.can_complete_care', false)
            ->assertJsonMissingPath('data.care_logs')
            ->assertJsonMissingPath('data.tips');
    }

    public function test_owner_can_manage_and_see_private_plant_detail_data(): void
    {
        $owner = User::factory()->create();
        $plant = Plant::create([
            'name' => 'Owner plant',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $owner->id,
        ]);
        CareLog::create([
            'plant_id' => $plant->id,
            'type' => 'watering',
            'performed_at' => now(),
        ]);
        Tip::create([
            'plant_id' => $plant->id,
            'author_id' => User::factory()->create()->id,
            'content' => 'Owner-visible tip',
            'status' => 'pending',
        ]);

        $this->actingAs($owner)
            ->getJson("/api/plants/{$plant->id}")
            ->assertOk()
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_delete', true)
            ->assertJsonPath('data.can_complete_care', true)
            ->assertJsonCount(1, 'data.care_logs')
            ->assertJsonCount(1, 'data.tips');
    }

    public function test_other_users_cannot_mutate_or_complete_care_for_foreign_plants(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $plant = Plant::create([
            'name' => 'Owner plant',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $owner->id,
        ]);
        $setting = CareSetting::create([
            'plant_id' => $plant->id,
            'type' => 'watering',
            'interval_days' => 7,
            'is_enabled' => true,
        ]);

        $this->actingAs($viewer)
            ->putJson("/api/plants/{$plant->id}", ['name' => 'Hacked'])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->deleteJson("/api/plants/{$plant->id}")
            ->assertForbidden();

        $this->actingAs($viewer)
            ->postJson("/api/plants/{$plant->id}/care-logs", [
                'type' => 'watering',
                'performed_at' => now()->toISOString(),
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->putJson("/api/care-settings/{$setting->id}", [
                'interval_days' => 3,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('plants', [
            'id' => $plant->id,
            'name' => 'Owner plant',
        ]);
        $this->assertDatabaseCount('care_logs', 0);
    }

    public function test_other_users_cannot_read_tip_list_for_foreign_plants(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $plant = Plant::create([
            'name' => 'Owner plant',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $owner->id,
        ]);
        Tip::create([
            'plant_id' => $plant->id,
            'author_id' => User::factory()->create()->id,
            'content' => 'Hidden tip',
            'status' => 'accepted',
        ]);

        $this->actingAs($viewer)
            ->getJson("/api/plants/{$plant->id}/tips")
            ->assertForbidden();
    }

    public function test_admin_cannot_delete_foreign_plant_via_regular_endpoint(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $plant = Plant::create([
            'name' => 'Owner plant',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/plants/{$plant->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('plants', [
            'id' => $plant->id,
        ]);
    }
}
