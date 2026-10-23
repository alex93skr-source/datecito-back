<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('s3_public');
        Storage::disk('s3_public')->buildTemporaryUrlsUsing(
            fn (string $path, \DateTimeInterface $expiration) => 'https://photos.test/'.$path.'?expires='.$expiration->getTimestamp(),
        );
    }

    protected function signIn(?User $user = null): User
    {
        $user ??= User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }
}
