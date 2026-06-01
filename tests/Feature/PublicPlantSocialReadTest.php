<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\Like;
use App\Models\Plant;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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
            ->assertForbidden();
    }

    public function test_public_plant_tips_are_only_listed_for_owner(): void
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
            ->assertForbidden();

        $this->actingAs($owner)
            ->getJson("/api/plants/{$plant->id}/tips")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_public_feed_marks_liked_plants_for_authenticated_viewer(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $likedPlant = Plant::create([
            'name' => 'Liked fern',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $owner->id,
        ]);
        $otherPlant = Plant::create([
            'name' => 'Other fern',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $owner->id,
        ]);

        Like::create(['user_id' => $viewer->id, 'plant_id' => $likedPlant->id]);
        $token = $viewer->createToken('test-token')->plainTextToken;

        $plants = collect($this
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/feed')
            ->assertOk()
            ->json('data'));

        $this->assertTrue($plants->firstWhere('id', $likedPlant->id)['user_liked']);
        $this->assertFalse($plants->firstWhere('id', $otherPlant->id)['user_liked']);
    }

    public function test_public_feed_excludes_current_user_plants_for_authenticated_viewer(): void
    {
        $viewer = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownPlant = Plant::create([
            'name' => 'My fern',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $viewer->id,
        ]);
        $otherPlant = Plant::create([
            'name' => 'Other fern',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $otherUser->id,
        ]);
        $token = $viewer->createToken('test-token')->plainTextToken;

        $plants = collect($this
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/feed')
            ->assertOk()
            ->json('data'));

        $this->assertFalse($plants->contains('id', $ownPlant->id));
        $this->assertTrue($plants->contains('id', $otherPlant->id));
    }

    public function test_liked_feed_returns_viewer_liked_plants(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $likedPlant = Plant::create([
            'name' => 'Liked fern',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $owner->id,
        ]);
        $otherPlant = Plant::create([
            'name' => 'Other fern',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $owner->id,
        ]);

        Like::create(['user_id' => $viewer->id, 'plant_id' => $likedPlant->id]);
        $token = $viewer->createToken('test-token')->plainTextToken;

        $plants = collect($this
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/feed/liked')
            ->assertOk()
            ->json('data'));

        $this->assertTrue($plants->contains('id', $likedPlant->id));
        $this->assertFalse($plants->contains('id', $otherPlant->id));
        $this->assertTrue($plants->firstWhere('id', $likedPlant->id)['user_liked']);
    }

    public function test_liked_feed_excludes_current_user_own_liked_plants(): void
    {
        $viewer = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownLikedPlant = Plant::create([
            'name' => 'My liked fern',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $viewer->id,
        ]);
        $otherLikedPlant = Plant::create([
            'name' => 'Other liked fern',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $otherUser->id,
        ]);

        Like::create(['user_id' => $viewer->id, 'plant_id' => $ownLikedPlant->id]);
        Like::create(['user_id' => $viewer->id, 'plant_id' => $otherLikedPlant->id]);
        $token = $viewer->createToken('test-token')->plainTextToken;

        $plants = collect($this
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/feed/liked')
            ->assertOk()
            ->json('data'));

        $this->assertFalse($plants->contains('id', $ownLikedPlant->id));
        $this->assertTrue($plants->contains('id', $otherLikedPlant->id));
    }

    public function test_personal_feed_returns_public_plants_from_followed_users(): void
    {
        $viewer = User::factory()->create();
        $followedUser = User::factory()->create();
        $unfollowedUser = User::factory()->create();
        $followedPlant = Plant::create([
            'name' => 'Followed fern',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $followedUser->id,
        ]);
        $privateFollowedPlant = Plant::create([
            'name' => 'Private followed fern',
            'planted_at' => now()->toDateString(),
            'is_public' => false,
            'user_id' => $followedUser->id,
        ]);
        $unfollowedPlant = Plant::create([
            'name' => 'Unfollowed fern',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $unfollowedUser->id,
        ]);

        Follow::create([
            'follower_id' => $viewer->id,
            'following_id' => $followedUser->id,
        ]);
        $token = $viewer->createToken('test-token')->plainTextToken;

        $plants = collect($this
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/feed/personal')
            ->assertOk()
            ->json('data'));

        $this->assertTrue($plants->contains('id', $followedPlant->id));
        $this->assertFalse($plants->contains('id', $privateFollowedPlant->id));
        $this->assertFalse($plants->contains('id', $unfollowedPlant->id));
    }

    public function test_personal_feed_excludes_current_user_plants_even_if_self_follow_exists(): void
    {
        $viewer = User::factory()->create();
        $followedUser = User::factory()->create();
        $ownPlant = Plant::create([
            'name' => 'My followed fern',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $viewer->id,
        ]);
        $followedPlant = Plant::create([
            'name' => 'Followed fern',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $followedUser->id,
        ]);

        Follow::create([
            'follower_id' => $viewer->id,
            'following_id' => $viewer->id,
        ]);
        Follow::create([
            'follower_id' => $viewer->id,
            'following_id' => $followedUser->id,
        ]);
        $token = $viewer->createToken('test-token')->plainTextToken;

        $plants = collect($this
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/feed/personal')
            ->assertOk()
            ->json('data'));

        $this->assertFalse($plants->contains('id', $ownPlant->id));
        $this->assertTrue($plants->contains('id', $followedPlant->id));
    }

    public function test_personal_feed_accepts_frontend_sort_options(): void
    {
        $viewer = User::factory()->create();
        $followedUser = User::factory()->create();
        $olderPlant = Plant::create([
            'name' => 'Zamioculcas',
            'planted_at' => now()->subDays(10)->toDateString(),
            'is_public' => true,
            'user_id' => $followedUser->id,
        ]);
        $newerPlant = Plant::create([
            'name' => 'Aloe',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $followedUser->id,
        ]);

        Follow::create([
            'follower_id' => $viewer->id,
            'following_id' => $followedUser->id,
        ]);
        $token = $viewer->createToken('test-token')->plainTextToken;

        $byName = collect($this
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/feed/personal?sort_by=name&sort_order=asc')
            ->assertOk()
            ->json('data'));

        $byPlantedAt = collect($this
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/feed/personal?sort_by=planted_at&sort_order=desc')
            ->assertOk()
            ->json('data'));

        $this->assertSame($newerPlant->id, $byName->first()['id']);
        $this->assertSame($newerPlant->id, $byPlantedAt->first()['id']);
        $this->assertTrue($byName->contains('id', $olderPlant->id));
    }

    public function test_toggle_like_returns_current_like_count(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $plant = Plant::create([
            'name' => 'Public fern',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($viewer);

        $this->postJson("/api/plants/{$plant->id}/like")
            ->assertOk()
            ->assertJsonPath('liked', true)
            ->assertJsonPath('likes_count', 1);

        $this->postJson("/api/plants/{$plant->id}/like")
            ->assertOk()
            ->assertJsonPath('liked', false)
            ->assertJsonPath('likes_count', 0);
    }

    public function test_tip_status_update_persists_status_changed_at(): void
    {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $plant = Plant::create([
            'name' => 'Public fern',
            'planted_at' => now()->toDateString(),
            'is_public' => true,
            'user_id' => $owner->id,
        ]);
        $tip = Tip::create([
            'plant_id' => $plant->id,
            'author_id' => $author->id,
            'content' => 'Move it away from direct sun',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($owner);

        $payload = $this
            ->putJson("/api/tips/{$tip->id}/status", [
                'status' => 'accepted',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->json('data');

        $this->assertNotNull($payload['status_changed_at']);
        $this->assertNotNull($tip->fresh()->status_changed_at);
    }
}
