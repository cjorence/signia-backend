<?php

namespace Tests\Feature;

use App\Models\{Level, PlayerProfile, Sign, User};
use App\Services\GestureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberLessonRecognitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_lesson_accepts_one_without_aliases_and_rejects_ten(): void
    {
        $level = Level::create(['name' => 'FSL Numbers', 'order' => 2]);
        $sign = Sign::create(['level_id' => $level->id, 'name' => 'One', 'fsl_name' => 'ONE', 'model_label' => 'number']);
        $user = User::factory()->create();
        $profile = PlayerProfile::create(['user_id' => $user->id, 'hearts' => 5]);
        $service = app(GestureService::class);
        $data = ['sign_id' => $sign->id, 'level_id' => $level->id, 'confidence' => 97.8];
        $correct = $service->storeGesture($user->id, $data + ['predicted_sign' => 'ONE']);
        $this->assertTrue($correct['is_correct']);
        $this->assertEquals(5, $profile->fresh()->hearts);
        $wrong = $service->storeGesture($user->id, $data + ['predicted_sign' => 'TEN']);
        $this->assertFalse($wrong['is_correct']);
        $this->assertEquals(4, $profile->fresh()->hearts);
    }
}
