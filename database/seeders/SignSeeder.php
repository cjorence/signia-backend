<?php

namespace Database\Seeders;

use App\Models\Level;
use App\Models\Sign;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SignSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Category Configuration
        |--------------------------------------------------------------------------
        */

        $categoryConfig = [
            'NUMBER' => [
                'level' => 'FSL Numbers',
                'order' => 3,
                'difficulty' => 'easy',
                'xp_reward' => 10,
            ],
            'GREETING' => [
                'level' => 'FSL Greetings',
                'order' => 4,
                'difficulty' => 'medium',
                'xp_reward' => 15,
            ],

            'SURVIVAL' => [
                'level' => 'FSL Survival',
                'order' => 5,
                'difficulty' => 'medium',
                'xp_reward' => 15,
            ],

            'CALENDAR' => [
                'level' => 'FSL Calendar',
                'order' => 6,
                'difficulty' => 'medium',
                'xp_reward' => 10,
            ],

            'DAYS' => [
                'level' => 'FSL Days',
                'order' => 7,
                'difficulty' => 'medium',
                'xp_reward' => 10,
            ],

            'FAMILY' => [
                'level' => 'FSL Family',
                'order' => 8,
                'difficulty' => 'medium',
                'xp_reward' => 15,
            ],

            'RELATIONSHIPS' => [
                'level' => 'FSL Relationships',
                'order' => 9,
                'difficulty' => 'medium',
                'xp_reward' => 15,
            ],

            'COLOR' => [
                'level' => 'FSL Colors',
                'order' => 10,
                'difficulty' => 'medium',
                'xp_reward' => 10,
            ],

            'FOOD' => [
                'level' => 'FSL Food',
                'order' => 11,
                'difficulty' => 'medium',
                'xp_reward' => 10,
            ],

            'DRINK' => [
                'level' => 'FSL Drinks',
                'order' => 12,
                'difficulty' => 'medium',
                'xp_reward' => 10,
            ],

            'PRONOUN' => [
                'level' => 'FSL Pronouns',
                'order' => 13,
                'difficulty' => 'medium',
                'xp_reward' => 10,
            ],

            'COMMUNICATION' => [
                'level' => 'FSL Communication',
                'order' => 14,
                'difficulty' => 'medium',
                'xp_reward' => 15,
            ],

            'CONVERSATIONAL' => [
                'level' => 'FSL Conversational',
                'order' => 15,
                'difficulty' => 'hard',
                'xp_reward' => 20,
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Create Levels
        |--------------------------------------------------------------------------
        */

        $levels = [];

        foreach ($categoryConfig as $category => $config) {
            $levels[$category] = Level::updateOrCreate(
                [
                    'name' => $config['level'],
                ],
                [
                    'order' => $config['order'],
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FSL Alphabet
        |--------------------------------------------------------------------------
        |
        | Alphabet is handled separately because it uses images instead
        | of video files.
        |
        */

        $alphabetLevel = Level::updateOrCreate(
            [
                'name' => 'FSL Alphabet',
            ],
            [
                'order' => 1,
            ]
        );

        $alphabetDescriptions = [
            'A' => 'FSL sign for the letter A.',
            'B' => 'FSL sign for the letter B.',
            'C' => 'FSL sign for the letter C.',
            'D' => 'FSL sign for the letter D.',
            'E' => 'FSL sign for the letter E.',
            'F' => 'FSL sign for the letter F.',
            'G' => 'FSL sign for the letter G.',
            'H' => 'FSL sign for the letter H.',
            'I' => 'FSL sign for the letter I.',
            'J' => 'FSL sign for the letter J.',
            'K' => 'FSL sign for the letter K.',
            'L' => 'FSL sign for the letter L.',
            'M' => 'FSL sign for the letter M.',
            'N' => 'FSL sign for the letter N.',
            'O' => 'FSL sign for the letter O.',
            'P' => 'FSL sign for the letter P.',
            'Q' => 'FSL sign for the letter Q.',
            'R' => 'FSL sign for the letter R.',
            'S' => 'FSL sign for the letter S.',
            'T' => 'FSL sign for the letter T.',
            'U' => 'FSL sign for the letter U.',
            'V' => 'FSL sign for the letter V.',
            'W' => 'FSL sign for the letter W.',
            'X' => 'FSL sign for the letter X.',
            'Y' => 'FSL sign for the letter Y.',
            'Z' => 'FSL sign for the letter Z.',
        ];

        $alphabetOrder = 1;

        foreach ($alphabetDescriptions as $letter => $description) {
            Sign::updateOrCreate(
                [
                    'level_id' => $alphabetLevel->id,
                    'name' => "Letter {$letter}",
                ],
                [
                    'fsl_name' => $letter,
                    'description' => $description,

                    // User requested model_label to be null
                    'model_label' => null,

                    'difficulty' => 'easy',
                    'xp_reward' => 10,
                    'sort_order' => $alphabetOrder,

                    // Alphabet uses image instead of video
                    'video_url' => null,
                    'image_url' => "/storage/videos/signs/images/{$letter}.png",
                ]
            );

            $alphabetOrder++;
        }

        /*
        |--------------------------------------------------------------------------
        | Read CSV
        |--------------------------------------------------------------------------
        |
        | Expected CSV structure:
        |
        | label,category
        | HELLO,GREETING
        | THANK YOU,GREETING
        | ...
        |
        */

        $csvPath = database_path('seeders/data/labels.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found: {$csvPath}");
            return;
        }

        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            $this->command->error("Unable to open CSV file.");
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Read Header
        |--------------------------------------------------------------------------
        */

        $header = fgetcsv($handle);

        if (!$header) {
            fclose($handle);
            $this->command->error("CSV file is empty.");
            return;
        }

        // Remove UTF-8 BOM if present
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        $header = array_map(
            fn ($value) => strtolower(trim($value)),
            $header
        );

        /*
        |--------------------------------------------------------------------------
        | Find Label + Category Columns
        |--------------------------------------------------------------------------
        */

        $labelIndex = null;
        $categoryIndex = null;

        foreach ($header as $index => $column) {
            if (in_array($column, [
                'label',
                'name',
                'sign',
                'model_label',
            ])) {
                $labelIndex = $index;
            }

            if (in_array($column, [
                'category',
                'type',
                'group',
            ])) {
                $categoryIndex = $index;
            }
        }

        if ($labelIndex === null) {
            fclose($handle);
            $this->command->error(
                'CSV must contain a label/name/sign column.'
            );
            return;
        }

        if ($categoryIndex === null) {
            fclose($handle);
            $this->command->error(
                'CSV must contain a category/type/group column.'
            );
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Seed CSV Signs
        |--------------------------------------------------------------------------
        */

        $sortOrders = [];

        foreach ($categoryConfig as $category => $config) {
            $sortOrders[$category] = 1;
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row, fn ($value) => trim($value) !== ''))) {
                continue;
            }

            $label = trim($row[$labelIndex] ?? '');
            $category = strtoupper(trim($row[$categoryIndex] ?? ''));

            if ($label === '') {
                continue;
            }

            /*
            | Skip alphabet if it exists in the CSV
            */
            if ($category === 'ALPHABET') {
                continue;
            }

            /*
            | Skip categories that are not configured
            */
            if (!isset($categoryConfig[$category])) {
                $this->command->warn(
                    "Skipping '{$label}' - unknown category '{$category}'."
                );

                continue;
            }

            $config = $categoryConfig[$category];
            $level = $levels[$category];

            /*
            |--------------------------------------------------------------------------
            | Display Name
            |--------------------------------------------------------------------------
            */

            $displayName = Str::title(
                Str::lower($label)
            );

            /*
            |--------------------------------------------------------------------------
            | Video Filename
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            |
            | GOOD MORNING
            |     ↓
            | GOODMORNING.mp4
            |
            | THANK YOU
            |     ↓
            | THANKYOU.mp4
            |
            | No underscores.
            |
            */

            $videoFilename = strtoupper(
                preg_replace('/[^A-Za-z0-9]/', '_', $label)
            );

            $videoUrl = "signs/videos/{$videoFilename}.MOV";

            /*
            |--------------------------------------------------------------------------
            | Create Sign
            |--------------------------------------------------------------------------
            */

            Sign::updateOrCreate(
                [
                    'level_id' => $level->id,
                    'name' => $displayName,
                ],
                [
                    'fsl_name' => $label,

                    'description' => "FSL sign for {$displayName}.",

                    // Intentionally NULL
                    'model_label' => null,

                    'difficulty' => $config['difficulty'],
                    'xp_reward' => $config['xp_reward'],
                    'sort_order' => $sortOrders[$category],

                    // Non-alphabet signs use videos
                    'video_url' => $videoUrl,

                    // No image URL for normal signs
                    'image_url' => null,
                ]
            );

            $sortOrders[$category]++;
        }

        fclose($handle);

        $this->command->info('FSL signs seeded successfully.');
    }
}