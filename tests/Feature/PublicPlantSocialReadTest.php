<?php

namespace Tests\Feature;

use App\Models\Like;
use App\Models\Plant;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPlantSocialReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_plant_social_reads_return_safe_empty_payloads_instead_of_forbidden(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $plant = Plant::create([
            'name' => 'Private fern',
            'planted_at' => now()->toDateString(),
            'is_public' => false,
            'user_id' => $owner->id,
        ]);

        Like::create(['user_id' => $owner->id, 'plant_id' => $plant->id]);
        Tip::create([
            'plant_id' => $plant->id,
            'author_id' => $owner->id,
            'content' => 'Private tip',
            'status' => 'accepted',
        ]);

        $this->actingAs($viewer)
            ->getJson("/api/plants/{$plant->id}/likes/count")
            ->assertOk()
            ->assertJsonPath('likes_count', 0);

        $this->actingAs($viewer)
            ->getJson("/api/plants/{$plant->id}/tips")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_public_plant_tips_hide_pending_tips_from_other_users(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $plant = Plant::create([
            'name' => 'Public fern',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $owner->id,
        ]);

        Tip::create([
            'plant_id' => $plant->id,
            'author_id' => $author->id,
            'content' => 'Accepted tip',
            'status' => 'accepted',
        ]);
        Tip::create([
            'plant_id' => $plant->id,
            'author_id' => $author->id,
            'content' => 'Pending tip',
            'status' => 'pending',
        ]);

        $this->actingAs($viewer)
            ->getJson("/api/plants/{$plant->id}/tips")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.content', 'Accepted tip');

        $this->actingAs($owner)
            ->getJson("/api/plants/{$plant->id}/tips")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
