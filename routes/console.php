<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('signs:batch-convert {dir : Directory path containing media files}', function (string $dir) {
    if (!is_dir($dir)) {
        $this->error("Directory does not exist: {$dir}");
        return 1;
    }

    $this->info("Scanning {$dir} for media files...");
    $files = scandir($dir);
    $convertedVideos = 0;
    $convertedImages = 0;

    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || str_contains($file, '.tmp.') || str_contains($file, '_backup.')) {
            continue;
        }

        $fullPath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
        if (!is_file($fullPath)) continue;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $baseName = pathinfo($file, PATHINFO_FILENAME);

        // Videos -> Web-standard H.264 MP4
        if (in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'webm', 'wmv', 'flv'])) {
            $outputPath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "{$baseName}.mp4";
            $tempOutput = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "{$baseName}.temp_h264.mp4";

            $this->line("Processing video: {$file} -> {$baseName}.mp4 (H.264)...");
            $cmd = sprintf(
                'ffmpeg -y -i %s -c:v libx264 -pix_fmt yuv420p -movflags +faststart -c:a aac %s 2>&1',
                escapeshellarg($fullPath),
                escapeshellarg($tempOutput)
            );
            exec($cmd, $out, $ret);

            if ($ret === 0 && file_exists($tempOutput) && filesize($tempOutput) > 0) {
                if ($fullPath !== $outputPath && file_exists($fullPath)) {
                    @unlink($fullPath);
                } elseif (file_exists($outputPath)) {
                    @unlink($outputPath);
                }
                rename($tempOutput, $outputPath);
                $this->info("✓ Converted video: {$baseName}.mp4");
                $convertedVideos++;
            } else {
                if (file_exists($tempOutput)) @unlink($tempOutput);
                $this->warn("! Skipped / failed video: {$file}");
            }
        }

        // Images -> WebP
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'bmp', 'tiff'])) {
            $outputPath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "{$baseName}.webp";
            $this->line("Processing image: {$file} -> {$baseName}.webp...");

            $cmd = sprintf(
                'ffmpeg -y -i %s -c:v libwebp -quality 90 %s 2>&1',
                escapeshellarg($fullPath),
                escapeshellarg($outputPath)
            );
            exec($cmd, $out, $ret);

            if ($ret === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
                $this->info("✓ Converted image: {$baseName}.webp");
                $convertedImages++;
            } else {
                $this->warn("! Skipped / failed image: {$file}");
            }
        }
    }

    $this->info("Completed! Converted {$convertedVideos} video(s) and {$convertedImages} image(s).");
    return 0;
})->purpose('Batch convert videos to web-standard H.264 MP4 and images to WebP');
