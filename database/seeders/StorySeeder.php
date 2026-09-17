<?php

namespace Database\Seeders;

use App\Models\Story;
use Illuminate\Database\Seeder;

class StorySeeder extends Seeder
{
    public function run(): void
    {
        $chapters = [
            [
                'slug' => 'arrival',
                'title' => 'Bagong Mukha',
                'description' => 'Pagdating ni Leo sa baryo ng Luntian, nakilala niya si Mira at natutunang ipakilala ang sarili gamit ang mga titik.',
                'chapter_number' => 1,
                'order' => 1,
                'is_free' => true,
                'price' => 0.00,
                'currency' => 'PHP',
                'episodes' => 2,
                'icon' => 'MapPin',
                'color' => 'from-emerald-500 to-teal-400',
                'cover_url' => '/assets/hero-banner.png',
                'file_name' => null,
                'required_lesson_ids' => [],
            ],
            [
                'slug' => 'plaza',
                'title' => 'Unang Gabi sa Plaza',
                'description' => 'Nakilala ni Leo ang mga taga-baryo at natutong bumati, sumagot, at humingi ng paglilinaw sa pamamagitan ng mga senyas.',
                'chapter_number' => 2,
                'order' => 2,
                'is_free' => false,
                'price' => 49.00,
                'currency' => 'PHP',
                'episodes' => 4,
                'icon' => 'Users',
                'color' => 'from-violet-500 to-purple-400',
                'cover_url' => '/assets/advertise.png',
                'file_name' => null,
                'required_lesson_ids' => ['alphabet'],
            ],
            [
                'slug' => 'market',
                'title' => 'Pamimili sa Palengke',
                'description' => 'Sa unang pagkakataon, ipinadala si Leo mag-isa upang bumili ng pagkain at inumin para sa pagtitipon ng baryo.',
                'chapter_number' => 3,
                'order' => 3,
                'is_free' => false,
                'price' => 49.00,
                'currency' => 'PHP',
                'episodes' => 5,
                'icon' => 'ShoppingCart',
                'color' => 'from-emerald-500 to-teal-400',
                'cover_url' => '/assets/gameSample.png',
                'file_name' => null,
                'required_lesson_ids' => ['numbers'],
            ],
            [
                'slug' => 'villagers',
                'title' => 'Mga Tao ng Luntian',
                'description' => 'Habang naghahatid ng mga gamit, mas nakilala ni Leo ang mga pamilya at kuwento ng mga taga-baryo.',
                'chapter_number' => 4,
                'order' => 4,
                'is_free' => false,
                'price' => 49.00,
                'currency' => 'PHP',
                'episodes' => 4,
                'icon' => 'HandHeart',
                'color' => 'from-amber-500 to-orange-400',
                'cover_url' => '/assets/login-img.png',
                'file_name' => null,
                'required_lesson_ids' => [],
            ],
            [
                'slug' => 'festival-prep',
                'title' => 'Paghahanda sa Pista',
                'description' => 'Tumulong si Leo sa pag-aayos ng mga kailangan para sa nalalapit na pista habang natututo ng bilang at mga araw.',
                'chapter_number' => 5,
                'order' => 5,
                'is_free' => false,
                'price' => 49.00,
                'currency' => 'PHP',
                'episodes' => 5,
                'icon' => 'Calendar',
                'color' => 'from-rose-500 to-pink-400',
                'cover_url' => '/assets/fsl-alphabet.png',
                'file_name' => null,
                'required_lesson_ids' => [],
            ],
            [
                'slug' => 'belonging',
                'title' => 'Bahagi ng Luntian',
                'description' => 'Sa gitna ng pagdiriwang, ginamit ni Leo ang lahat ng kaniyang natutunan upang makatulong sa mga taga-baryo.',
                'chapter_number' => 6,
                'order' => 6,
                'is_free' => false,
                'price' => 49.00,
                'currency' => 'PHP',
                'episodes' => 3,
                'icon' => 'Star',
                'color' => 'from-yellow-500 to-amber-400',
                'cover_url' => '/assets/bg.png',
                'file_name' => null,
                'required_lesson_ids' => [],
            ],
        ];

        foreach ($chapters as $chapter) {
            Story::updateOrCreate(
                ['slug' => $chapter['slug']],
                $chapter
            );
        }
    }
}
