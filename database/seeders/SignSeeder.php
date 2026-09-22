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
                'model_name'=> 'number'
            ],  
            'GREETING' => [
                'level' => 'FSL Greetings',
                'order' => 4,
                'difficulty' => 'medium',
                'xp_reward' => 15,
                'model_name'=>'greeting'
            ],

            'SURVIVAL' => [
                'level' => 'FSL Survival',
                'order' => 5,
                'difficulty' => 'medium',
                'xp_reward' => 15,
                'model_name'=>'survival'
            ],

            'CALENDAR' => [
                'level' => 'FSL Calendar',
                'order' => 6,
                'difficulty' => 'medium',
                'xp_reward' => 10,
                'model_name'=>'calendar'
            ],

            'DAYS' => [
                'level' => 'FSL Days',
                'order' => 7,
                'difficulty' => 'medium',
                'xp_reward' => 10,
                'model_name'=>'days'
            ],

            'FAMILY' => [
                'level' => 'FSL Family',
                'order' => 8,
                'difficulty' => 'medium',
                'xp_reward' => 15,
                'model_name'=>'family'
            ],

            'RELATIONSHIPS' => [
                'level' => 'FSL Relationships',
                'order' => 9,
                'difficulty' => 'medium',
                'xp_reward' => 15,
                'model_name'=>'relationships'
            ],

            'COLOR' => [
                'level' => 'FSL Colors',
                'order' => 10,
                'difficulty' => 'medium',
                'xp_reward' => 10,
                'model_name'=>'color'
            ],

            'FOOD' => [
                'level' => 'FSL Food',
                'order' => 11,
                'difficulty' => 'medium',
                'xp_reward' => 10,
                'model_name'=>'food'
            ],

            'DRINK' => [
                'level' => 'FSL Drinks',
                'order' => 12,
                'difficulty' => 'medium',
                'xp_reward' => 10,
                'model_name'=>'drink'
            ],

            'PRONOUN' => [
                'level' => 'FSL Pronouns',
                'order' => 13,
                'difficulty' => 'medium',
                'xp_reward' => 10,
                'model_name'=>'pronoun'
            ],

            'COMMUNICATION' => [
                'level' => 'FSL Communication',
                'order' => 14,
                'difficulty' => 'medium',
                'xp_reward' => 15,
                'model_name'=>'communication'
            ],

            'CONVERSATIONAL' => [
                'level' => 'FSL Conversational',
                'order' => 15,
                'difficulty' => 'hard',
                'xp_reward' => 20,
                'model_name'=>'conversational'
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
            'A' => 'Make a fist with your thumb resting against the side of your index finger. Keep your fingers together and point your thumb upward.',

            'B' => 'Hold your hand upright with all four fingers extended and held together. Fold your thumb across your palm.',

            'C' => 'Curve your fingers and thumb to form a C shape, as if holding a small object. Keep your palm facing sideways.',

            'D' => 'Extend your index finger upward. Touch the tips of your thumb, middle, ring, and little fingers together to form a rounded shape.',

            'E' => 'Curl your four fingers down toward your palm and place your thumb across the front of your fingers.',

            'F' => 'Touch the tips of your thumb and index finger together to form a circle. Keep your other three fingers extended upward.',

            'G' => 'Extend your index finger and thumb horizontally, with the two fingers pointing in the same direction. Keep your other fingers curled into your palm.',

            'H' => 'Extend your index and middle fingers together horizontally. Keep your other fingers and thumb curled into your palm.',

            'I' => 'Make a fist while keeping your little finger extended upward.',

            'J' => 'Make the handshape for I with your little finger extended. Move your little finger downward and curve it to trace the shape of a J.',

            'K' => 'Extend your index and middle fingers upward in a V shape. Place your thumb between them while keeping your ring and little fingers folded.',

            'L' => 'Extend your thumb and index finger to form an L shape. Keep your other three fingers curled into your palm.',

            'M' => 'Fold your thumb across your palm and place your index, middle, and ring fingers over it. Keep your little finger curled beside them.',

            'N' => 'Fold your thumb across your palm and place your index and middle fingers over it. Keep your ring and little fingers curled.',

            'O' => 'Curve all your fingers and thumb together to form a round O shape. Keep your fingertips touching or close together.',

            'P' => 'Form the K handshape, then angle your hand downward so your extended fingers point toward the ground.',

            'Q' => 'Extend your index finger and thumb downward, with both pointing in the same direction. Keep your other fingers curled into your palm.',

            'R' => 'Extend your index and middle fingers upward and cross them. Keep your thumb, ring, and little fingers folded into your palm.',

            'S' => 'Make a fist with all four fingers curled tightly into your palm. Place your thumb across the front of your fingers.',

            'T' => 'Make a fist and place your thumb between your index and middle fingers.',

            'U' => 'Extend your index and middle fingers upward and hold them together. Keep your other fingers and thumb curled into your palm.',

            'V' => 'Extend your index and middle fingers upward and separate them to form a V shape. Keep your other fingers and thumb folded.',

            'W' => 'Extend your index, middle, and ring fingers upward and spread them apart. Keep your thumb and little finger folded.',

            'X' => 'Make a fist while extending your index finger. Bend the index finger at the middle joint to form a hook shape.',

            'Y' => 'Extend your thumb and little finger outward while keeping your index, middle, and ring fingers curled into your palm.',

            'Z' => 'Extend your index finger and use it to trace the shape of the letter Z in the air.',
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
                    'model_label' => 'alphabet',
                    'description' => $description,
                    'difficulty' => 'easy',
                    'xp_reward' => 10,
                    'sort_order' => $alphabetOrder,
                    'video_type' => 'local',
                    'video_start' => null,
                    'video_end' => null,

                    // Alphabet uses image instead of video
                    'video_url' => null,
                    'image_url' => "/storage/signs/images/{$letter}.png",
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
                    'fsl_name'    => $label,
                    'model_label' => $config['model_name'] ?? null,
                    'description' => "FSL sign for {$displayName}.",
                    'difficulty'  => $config['difficulty'],
                    'xp_reward'   => $config['xp_reward'],
                    'sort_order'  => $sortOrders[$category],

                    // Non-alphabet signs use local videos by default
                    'video_url'   => $videoUrl,
                    'video_type'  => 'local',
                    'video_start' => null,
                    'video_end'   => null,

                    // No image URL for normal signs
                    'image_url'   => null,
                ]
            );

            $sortOrders[$category]++;
        }

        fclose($handle);

        $this->command->info('FSL signs seeded successfully.');
    }
}