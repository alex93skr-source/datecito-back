<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_can_be_updated_with_catalog_values(): void
    {
        $user = $this->signIn();

        $this->patchJson('/api/v1/me', [
            'name' => 'Alex',
            'birthdate' => today()->subYears(28)->toDateString(),
            'interests' => ['Café', 'Cine'],
            'music_genres' => ['Indie'],
            'basics' => ['drink' => 'Socialmente', 'pets' => 'Tengo perro'],
            'prompts' => [['q' => 'Mi datecito ideal', 'a' => 'Tacos y caminar.']],
        ])->assertOk()->assertJsonPath('data.age', 28)->assertJsonPath('data.basics.pets', 'Tengo perro');

        $this->assertSame(['Café', 'Cine'], $user->fresh()->interests);
    }

    public function test_profile_rejects_values_outside_the_catalog(): void
    {
        $this->signIn();

        $this->patchJson('/api/v1/me', [
            'interests' => ['<script>alert(1)</script>'],
            'basics' => ['height' => '1.90'],
            'intent' => 'Otra cosa',
        ])->assertUnprocessable()->assertJsonValidationErrors(['interests.0', 'basics', 'intent']);
    }

    public function test_favorite_teams_are_saved_per_sport(): void
    {
        $user = $this->signIn();

        $this->patchJson('/api/v1/me', [
            'interests' => ['Fútbol', 'Fútbol americano', 'Micheladas'],
            'teams' => ['Fútbol' => ['Pumas', 'Barcelona'], 'Fútbol americano' => ['Steelers'], 'Béisbol' => []],
        ])->assertOk()->assertJsonPath('data.teams.Fútbol.0', 'Pumas');

        $this->assertSame(['Fútbol' => ['Pumas', 'Barcelona'], 'Fútbol americano' => ['Steelers']], $user->fresh()->teams);
    }

    public function test_teams_must_exist_and_are_limited_per_sport(): void
    {
        $this->signIn();

        $this->patchJson('/api/v1/me', [
            'teams' => ['Fútbol' => ['América', 'Chivas', 'Pumas'], 'Fútbol americano' => ['Mi equipo'], 'Hockey' => ['Oilers']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['teams', 'teams.Fútbol', 'teams.Fútbol americano.0']);
    }

    public function test_minors_cannot_set_their_birthdate(): void
    {
        $this->signIn();

        $this->patchJson('/api/v1/me', ['birthdate' => today()->subYears(17)->toDateString()])
            ->assertJsonValidationErrors('birthdate');
    }

    public function test_privileged_fields_cannot_be_changed_through_the_profile(): void
    {
        $user = $this->signIn();

        $this->patchJson('/api/v1/me', ['name' => 'Alex', 'role' => 'admin', 'verified_at' => now(), 'super_likes_balance' => 999])->assertOk();

        $user->refresh();
        $this->assertSame('user', $user->role->value);
        $this->assertNull($user->verified_at);
        $this->assertSame(0, $user->super_likes_balance);
    }

    public function test_location_is_stored_approximately_and_never_returned(): void
    {
        $user = $this->signIn();

        $this->putJson('/api/v1/me/location', ['lat' => 19.432608, 'lng' => -99.133209])->assertNoContent();

        $this->assertSame(19.43, $user->fresh()->approx_lat);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonMissingPath('data.approx_lat')->assertJsonPath('data.has_location', true);
    }

    public function test_photos_are_reencoded_to_webp_variants(): void
    {
        $user = $this->signIn();

        $response = $this->postJson('/api/v1/me/photos', ['photo' => UploadedFile::fake()->image('yo.jpg', 1200, 1500)]);

        $response->assertCreated()->assertJsonStructure(['data' => ['id', 'urls' => ['lg', 'md', 'sm']]]);
        $photo = Photo::query()->whereBelongsTo($user)->sole();
        foreach (['lg', 'md', 'sm'] as $variant) {
            Storage::disk('s3')->assertExists($photo->storage_key.'/'.$variant.'.webp');
        }
    }

    public function test_photo_upload_rejects_files_that_are_not_images(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/me/photos', ['photo' => UploadedFile::fake()->create('virus.php', 10, 'image/jpeg')])
            ->assertJsonValidationErrors('photo');
    }

    public function test_photo_count_is_limited(): void
    {
        $user = $this->signIn();
        foreach (range(0, 6) as $position) {
            $user->photos()->create(['position' => $position, 'storage_key' => 'photos/x/'.$position]);
        }

        $this->postJson('/api/v1/me/photos', ['photo' => UploadedFile::fake()->image('yo.jpg', 800, 800)])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'photos_limit');
    }

    public function test_another_persons_photo_cannot_be_deleted(): void
    {
        $photo = User::factory()->withPhoto()->create()->photos()->sole();
        $this->signIn();

        $this->deleteJson('/api/v1/me/photos/'.$photo->id)->assertNotFound();
        $this->assertModelExists($photo);
    }

    public function test_account_deletion_requires_the_password_and_removes_everything(): void
    {
        $user = $this->signIn();
        Storage::disk('s3')->put('photos/'.$user->id.'/a/lg.webp', 'x');

        $this->deleteJson('/api/v1/me', ['password' => 'incorrecta1'])->assertJsonValidationErrors('password');

        $this->deleteJson('/api/v1/me', ['password' => 'password1'])->assertNoContent();
        $this->assertModelMissing($user);
        Storage::disk('s3')->assertMissing('photos/'.$user->id.'/a/lg.webp');
    }
}
