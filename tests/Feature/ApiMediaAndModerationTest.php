<?php

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\Report;
use App\Models\Role;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiMediaAndModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_and_delete_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/users/profile/avatar', [
            'avatar' => $this->pngUpload('avatar.png'),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonStructure(['data' => ['avatar_url']]);

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);

        $this->deleteJson('/api/users/profile/avatar')->assertOk();

        $oldPath = $user->avatar_path;
        $user->refresh();
        $this->assertNull($user->avatar_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_plant_images_are_owned_and_latest_image_is_returned_with_plant(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $plant = Plant::create([
            'name' => 'Monstera',
            'planted_at' => now()->subMonth(),
            'height' => 42,
            'is_public' => true,
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($owner);

        $first = $this->post("/api/plants/{$plant->id}/images", [
            'image' => $this->pngUpload('first.png'),
        ])->assertCreated()->json('data');

        $second = $this->post("/api/plants/{$plant->id}/images", [
            'image' => $this->pngUpload('second.png'),
        ])->assertCreated()->json('data');

        $this->getJson("/api/plants/{$plant->id}")
            ->assertOk()
            ->assertJsonPath('data.latest_image.id', $second['id']);

        $this->getJson("/api/plants/{$plant->id}/images")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);

        $this->deleteJson("/api/plant-images/{$first['id']}")->assertForbidden();
    }

    public function test_admin_can_review_tip_report_and_penalize_tip_author(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $owner = User::factory()->create();
        $tipAuthor = User::factory()->create(['rank' => 5]);
        $reporter = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $plant = Plant::create([
            'name' => 'Ficus',
            'planted_at' => now()->subYear(),
            'is_public' => true,
            'user_id' => $owner->id,
        ]);

        $tip = Tip::create([
            'plant_id' => $plant->id,
            'author_id' => $tipAuthor->id,
            'content' => 'Bad advice',
            'status' => 'accepted',
        ]);

        Sanctum::actingAs($reporter);

        $reportId = $this->postJson("/api/tips/{$tip->id}/reports", [
            'reason' => 'misinformation',
            'details' => 'This advice can damage the plant.',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('reports', [
            'id' => $reportId,
            'target_type' => Report::TARGET_TIP,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/reports/{$reportId}/review", [
            'status' => 'accepted',
            'admin_comment' => 'Confirmed.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertSame(4, $tipAuthor->refresh()->rank);
    }

    public function test_non_admin_cannot_access_admin_reports(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/reports')->assertForbidden();
    }

    private function pngUpload(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'plant-test-image-');
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='
        ));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
