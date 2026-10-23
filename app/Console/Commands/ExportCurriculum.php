<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ExportCurriculum extends Command
{
    public const TABLES = ['levels', 'signs', 'quests', 'quizzes', 'questions', 'choices', 'achievements', 'stories'];
    protected $signature = 'signia:curriculum-export {directory : New private review directory}';
    protected $description = 'Read-only curriculum export with referenced media; excludes users, activity, credentials and payments';

    public function handle(): int
    {
        $directory = $this->argument('directory');
        if (file_exists($directory)) {
            $this->error('Destination already exists. Choose a new review directory.');
            return self::FAILURE;
        }
        $tables = DB::transaction(function () {
            $rows = [];
            foreach (self::TABLES as $table) $rows[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
            return $rows;
        });
        File::makeDirectory($directory, 0700, true);
        $references = [];
        foreach ($tables['signs'] as $sign) {
            foreach (['image_url', 'video_url'] as $field) if (! empty($sign[$field])) $references[] = $sign[$field];
        }
        foreach ($tables['stories'] as $story) {
            if (! empty($story['cover_url'])) $references[] = $story['cover_url'];
            if (! empty($story['file_name'])) $references[] = '/story-packages/'.$story['file_name'];
        }
        // StoryService falls back to the base package when a story has no custom package.
        if (count($tables['stories'])) $references[] = '/story-packages/base.pck';
        $media = []; $missing = []; $external = [];
        foreach (array_unique($references) as $reference) {
            $path = rawurldecode((string) parse_url($reference, PHP_URL_PATH));
            // SignService stores uploads relative to the public disk, e.g. signs/videos/x.mp4.
            if (str_starts_with(ltrim($path, '/'), 'signs/')) $path = '/storage/'.ltrim($path, '/');
            $path = '/'.ltrim($path, '/');
            if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, "\0")) {
                $missing[] = 'Unsafe media path rejected';
                continue;
            }
            $mapping = null;
            foreach (['/storage/' => storage_path('app/public'), '/stories/covers/' => public_path('stories/covers'), '/story-packages/' => storage_path('app/stories')] as $prefix => $root) {
                if (str_starts_with($path, $prefix)) { $mapping = [$root, substr($path, strlen($prefix))]; break; }
            }
            if ($mapping === null) { $external[] = $reference; continue; }
            [$root, $relative] = $mapping;
            $source = $root.'/'.$relative;
            $realRoot = realpath($root); $realSource = realpath($source);
            if (! $realRoot || ! $realSource || ! str_starts_with($realSource, $realRoot.DIRECTORY_SEPARATOR) || ! is_file($realSource)) {
                $missing[] = $path;
                continue;
            }
            $target = $directory.'/media'.$path;
            File::ensureDirectoryExists(dirname($target), 0700);
            if (! copy($realSource, $target)) throw new \RuntimeException('Media copy failed.');
            $media[$path] = ['bytes' => filesize($target), 'sha256' => hash_file('sha256', $target)];
            if (hash_file('sha256', $realSource) !== $media[$path]['sha256']) throw new \RuntimeException('Media changed during export. Pause editing and retry.');
        }
        $payload = ['version' => 1, 'review_required' => true, 'tables' => $tables, 'media' => $media, 'missing_media' => $missing, 'external_media' => $external];
        File::put($directory.'/curriculum.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->table(['Table', 'Rows'], collect($tables)->map(fn ($rows, $table) => [$table, count($rows)])->values()->all());
        $this->line('Copied media: '.count($media).'; missing: '.count($missing).'; external references: '.count($external));
        $this->line('Review SHA-256: '.hash_file('sha256', $directory.'/curriculum.json'));
        $this->info('No database changes made. Review content, media and URL rewrites before importing.');
        return count($missing) ? self::FAILURE : self::SUCCESS;
    }
}
