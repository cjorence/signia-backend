<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CurriculumExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_excludes_users_and_never_overwrites_an_existing_review(): void
    {
        User::factory()->create(['email' => 'not-for-export@example.test']);
        $destination = sys_get_temp_dir().'/signia-curriculum-'.bin2hex(random_bytes(8));
        try {
            $this->artisan('signia:curriculum-export', ['directory' => $destination])->assertSuccessful();
            $text = File::get($destination.'/curriculum.json');
            $payload = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(\App\Console\Commands\ExportCurriculum::TABLES, array_keys($payload['tables']));
            $this->assertStringNotContainsString('not-for-export@example.test', $text);
            $this->assertTrue($payload['review_required']);
            $this->artisan('signia:curriculum-export', ['directory' => $destination])->assertFailed();
            $this->assertSame($text, File::get($destination.'/curriculum.json'));
            $this->assertSame(1, User::count());
        } finally {
            File::deleteDirectory($destination);
        }
    }
}
