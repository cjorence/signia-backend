<?php

namespace Database\Seeders;

use App\Models\Level;
use App\Models\Sign;
use Illuminate\Database\Seeder;

class SignSeeder extends Seeder
{
    public function run(): void
    {
        $alphabetLevel = Level::where('name', 'FSL Alphabet')->first()
            ?? Level::where('order', 1)->first();

        $numbersLevel = Level::where('name', 'FSL Numbers')->first()
            ?? Level::where('order', 2)->first();

        $greetingsLevel = Level::where('name', 'FSL Greetings')->first()
            ?? Level::where('order', 3)->first();

        if ($alphabetLevel) {
            $this->seedAlphabet($alphabetLevel->id);
        }

        if ($numbersLevel) {
            $this->seedNumbers($numbersLevel->id);
        }

        if ($greetingsLevel) {
            $this->seedGreetings($greetingsLevel->id);
        }
    }

    protected function seedAlphabet(int $levelId): void
    {
        $alphabetSigns = [
            [
                'name' => 'Letter A',
                'fsl_name' => 'A',
                'description' => 'Make a fist with your thumb resting straight along the side of your index finger.',
                'model_label' => 'letter_a',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 1,
            ],
            [
                'name' => 'Letter B',
                'fsl_name' => 'B',
                'description' => 'Hold all four fingers upright and touching together with your thumb folded across your palm.',
                'model_label' => 'letter_b',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 2,
            ],
            [
                'name' => 'Letter C',
                'fsl_name' => 'C',
                'description' => 'Curve your fingers and thumb to form the letter C shape facing sideways.',
                'model_label' => 'letter_c',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 3,
            ],
            [
                'name' => 'Letter D',
                'fsl_name' => 'D',
                'description' => 'Hold index finger straight up while fingertips of middle, ring, and pinky touch your thumb.',
                'model_label' => 'letter_d',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 4,
            ],
            [
                'name' => 'Letter E',
                'fsl_name' => 'E',
                'description' => 'Curl all four fingertips down to rest against the edge of your thumb tucked below them.',
                'model_label' => 'letter_e',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 5,
            ],
            [
                'name' => 'Letter F',
                'fsl_name' => 'F',
                'description' => 'Touch the tips of your thumb and index finger to form a circle while middle, ring, and pinky point up.',
                'model_label' => 'letter_f',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 6,
            ],
            [
                'name' => 'Letter G',
                'fsl_name' => 'G',
                'description' => 'Point your index finger horizontally to the side with your thumb parallel, other fingers curled in.',
                'model_label' => 'letter_g',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 7,
            ],
            [
                'name' => 'Letter H',
                'fsl_name' => 'H',
                'description' => 'Extend your index and middle fingers together horizontally with thumb tucked in.',
                'model_label' => 'letter_h',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 8,
            ],
            [
                'name' => 'Letter I',
                'fsl_name' => 'I',
                'description' => 'Make a fist and extend only your pinky finger straight up.',
                'model_label' => 'letter_i',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 9,
            ],
            [
                'name' => 'Letter J',
                'fsl_name' => 'J',
                'description' => 'With pinky extended, trace the letter J in the air curving inward.',
                'model_label' => 'letter_j',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 10,
            ],
            [
                'name' => 'Letter K',
                'fsl_name' => 'K',
                'description' => 'Point index finger up, middle finger angled slightly forward, and place thumb between them.',
                'model_label' => 'letter_k',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 11,
            ],
            [
                'name' => 'Letter L',
                'fsl_name' => 'L',
                'description' => 'Extend your thumb and index finger at a right angle to form an L shape.',
                'model_label' => 'letter_l',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 12,
            ],
            [
                'name' => 'Letter M',
                'fsl_name' => 'M',
                'description' => 'Tuck your thumb between your ring finger and pinky with fingers curled over it.',
                'model_label' => 'letter_m',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 13,
            ],
            [
                'name' => 'Letter N',
                'fsl_name' => 'N',
                'description' => 'Tuck your thumb between your middle and ring finger with fingers curled over it.',
                'model_label' => 'letter_n',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 14,
            ],
            [
                'name' => 'Letter O',
                'fsl_name' => 'O',
                'description' => 'Touch all four fingertips to your thumb to form an O shape.',
                'model_label' => 'letter_o',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 15,
            ],
            [
                'name' => 'Letter P',
                'fsl_name' => 'P',
                'description' => 'Form the K handshape and point it downward.',
                'model_label' => 'letter_p',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 16,
            ],
            [
                'name' => 'Letter Q',
                'fsl_name' => 'Q',
                'description' => 'Form the G handshape and point index finger and thumb downward.',
                'model_label' => 'letter_q',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 17,
            ],
            [
                'name' => 'Letter R',
                'fsl_name' => 'R',
                'description' => 'Cross your index and middle fingers straight up like a good luck gesture.',
                'model_label' => 'letter_r',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 18,
            ],
            [
                'name' => 'Letter S',
                'fsl_name' => 'S',
                'description' => 'Make a fist with your thumb folded across the front of your fingers.',
                'model_label' => 'letter_s',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 19,
            ],
            [
                'name' => 'Letter T',
                'fsl_name' => 'T',
                'description' => 'Tuck your thumb between your index and middle finger with fingers curled over.',
                'model_label' => 'letter_t',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 20,
            ],
            [
                'name' => 'Letter U',
                'fsl_name' => 'U',
                'description' => 'Extend index and middle fingers straight up and together with other fingers closed.',
                'model_label' => 'letter_u',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 21,
            ],
            [
                'name' => 'Letter V',
                'fsl_name' => 'V',
                'description' => 'Extend index and middle fingers in a V shape with other fingers closed.',
                'model_label' => 'letter_v',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 22,
            ],
            [
                'name' => 'Letter W',
                'fsl_name' => 'W',
                'description' => 'Extend index, middle, and ring fingers upward spread apart in a W shape.',
                'model_label' => 'letter_w',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 23,
            ],
            [
                'name' => 'Letter X',
                'fsl_name' => 'X',
                'description' => 'Make a fist and crook your index finger like a hook.',
                'model_label' => 'letter_x',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 24,
            ],
            [
                'name' => 'Letter Y',
                'fsl_name' => 'Y',
                'description' => 'Extend your thumb and pinky outwards while curling the middle three fingers.',
                'model_label' => 'letter_y',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 25,
            ],
            [
                'name' => 'Letter Z',
                'fsl_name' => 'Z',
                'description' => 'Extend index finger and trace a Z pattern in the air.',
                'model_label' => 'letter_z',
                'difficulty' => 'easy',
                'xp_reward' => 10,
                'sort_order' => 26,
            ],
        ];

        foreach ($alphabetSigns as $data) {
            Sign::updateOrCreate(
                [
                    'level_id' => $levelId,
                    'name' => $data['name'],
                ],
                $data
            );
        }
    }

    protected function seedNumbers(int $levelId): void
    {
        $numberSigns = [
            [
                'name' => 'Number 1',
                'fsl_name' => '1 / Isa',
                'description' => 'Extend only your index finger straight up with palm facing inward or forward.',
                'model_label' => '1',
                'difficulty' => 'easy',
                'xp_reward' => 15,
                'sort_order' => 1,
            ],
            [
                'name' => 'Number 2',
                'fsl_name' => '2 / Dalawa',
                'description' => 'Extend index and middle fingers upward in a V shape.',
                'model_label' => '2',
                'difficulty' => 'easy',
                'xp_reward' => 15,
                'sort_order' => 2,
            ],
            [
                'name' => 'Number 3',
                'fsl_name' => '3 / Tatlo',
                'description' => 'Extend thumb, index, and middle fingers with ring and pinky tucked.',
                'model_label' => '3',
                'difficulty' => 'easy',
                'xp_reward' => 15,
                'sort_order' => 3,
            ],
            [
                'name' => 'Number 4',
                'fsl_name' => '4 / Apat',
                'description' => 'Extend all four fingers upward spread slightly with thumb folded in.',
                'model_label' => '4',
                'difficulty' => 'easy',
                'xp_reward' => 15,
                'sort_order' => 4,
            ],
            [
                'name' => 'Number 5',
                'fsl_name' => '5 / Lima',
                'description' => 'Extend all five fingers open and spread wide.',
                'model_label' => '5',
                'difficulty' => 'easy',
                'xp_reward' => 15,
                'sort_order' => 5,
            ],
            [
                'name' => 'Number 6',
                'fsl_name' => '6 / Anim',
                'description' => 'Touch your pinky fingertip to your thumb while keeping index, middle, and ring fingers up.',
                'model_label' => '6',
                'difficulty' => 'easy',
                'xp_reward' => 15,
                'sort_order' => 6,
            ],
            [
                'name' => 'Number 7',
                'fsl_name' => '7 / Pito',
                'description' => 'Touch your ring fingertip to your thumb while keeping index, middle, and pinky up.',
                'model_label' => '7',
                'difficulty' => 'easy',
                'xp_reward' => 15,
                'sort_order' => 7,
            ],
            [
                'name' => 'Number 8',
                'fsl_name' => '8 / Walo',
                'description' => 'Touch your middle fingertip to your thumb while keeping index, ring, and pinky up.',
                'model_label' => '8',
                'difficulty' => 'easy',
                'xp_reward' => 15,
                'sort_order' => 8,
            ],
            [
                'name' => 'Number 9',
                'fsl_name' => '9 / Siyam',
                'description' => 'Touch your index fingertip to your thumb with remaining fingers up.',
                'model_label' => '9',
                'difficulty' => 'easy',
                'xp_reward' => 15,
                'sort_order' => 9,
            ],
            [
                'name' => 'Number 10',
                'fsl_name' => '10 / Sampu',
                'description' => 'Make a fist with thumb pointing up (A handshape) and shake or twist slightly.',
                'model_label' => '10',
                'difficulty' => 'easy',
                'xp_reward' => 15,
                'sort_order' => 10,
            ],
        ];

        foreach ($numberSigns as $data) {
            Sign::updateOrCreate(
                [
                    'level_id' => $levelId,
                    'name' => $data['name'],
                ],
                $data
            );
        }
    }

    protected function seedGreetings(int $levelId): void
    {
        $greetingSigns = [
            [
                'name' => 'Hello',
                'fsl_name' => 'Kamusta',
                'description' => 'Touch your temple with the fingertips of your open flat hand, then move your hand outward and forward with a gentle salute motion.',
                'model_label' => 'hello',
                'difficulty' => 'medium',
                'xp_reward' => 15,
                'sort_order' => 1,
            ],
            [
                'name' => 'Thank You',
                'fsl_name' => 'Salamat',
                'description' => 'Touch your fingertips to your chin/lips and move your flat hand forward and slightly down towards the person.',
                'model_label' => 'thanks',
                'difficulty' => 'medium',
                'xp_reward' => 15,
                'sort_order' => 2,
            ],
            [
                'name' => 'Good Morning',
                'fsl_name' => 'Magandang Umaga',
                'description' => 'Sign good then bring your dominant hand upward under the opposite forearm like the rising sun.',
                'model_label' => 'good_morning',
                'difficulty' => 'medium',
                'xp_reward' => 15,
                'sort_order' => 3,
            ],
            [
                'name' => 'Good Afternoon',
                'fsl_name' => 'Magandang Hapon',
                'description' => 'Sign good then rest dominant forearm bent at a slight downward angle indicating afternoon sun.',
                'model_label' => 'good_afternoon',
                'difficulty' => 'medium',
                'xp_reward' => 15,
                'sort_order' => 4,
            ],
            [
                'name' => 'Good Evening',
                'fsl_name' => 'Magandang Gabi',
                'description' => 'Sign good then curve your dominant hand downward over the non-dominant wrist representing sunset/night.',
                'model_label' => 'good_evening',
                'difficulty' => 'medium',
                'xp_reward' => 15,
                'sort_order' => 5,
            ],
            [
                'name' => 'Goodbye',
                'fsl_name' => 'Paalam',
                'description' => 'Hold open hand at shoulder height with palm facing forward and gently bend your fingers up and down.',
                'model_label' => 'bye',
                'difficulty' => 'medium',
                'xp_reward' => 15,
                'sort_order' => 6,
            ],
            [
                'name' => 'Please',
                'fsl_name' => 'Pakiusap',
                'description' => 'Place your flat dominant palm over your chest and rub in a gentle clockwise circle.',
                'model_label' => 'please',
                'difficulty' => 'medium',
                'xp_reward' => 15,
                'sort_order' => 7,
            ],
            [
                'name' => "You're Welcome",
                'fsl_name' => 'Walang Anuman',
                'description' => 'Hold an open flat hand outward and bring it slightly inward towards your chest with an appreciative nod.',
                'model_label' => 'welcome',
                'difficulty' => 'medium',
                'xp_reward' => 15,
                'sort_order' => 8,
            ],
            [
                'name' => 'Yes',
                'fsl_name' => 'Oo',
                'description' => 'Make a fist and nod it up and down from the wrist like a nodding head.',
                'model_label' => 'yes',
                'difficulty' => 'medium',
                'xp_reward' => 15,
                'sort_order' => 9,
            ],
            [
                'name' => 'No',
                'fsl_name' => 'Hindi',
                'description' => 'Snap your index and middle fingers down quickly onto your thumb like a quick pinch.',
                'model_label' => 'no',
                'difficulty' => 'medium',
                'xp_reward' => 15,
                'sort_order' => 10,
            ],
        ];

        foreach ($greetingSigns as $data) {
            Sign::updateOrCreate(
                [
                    'level_id' => $levelId,
                    'name' => $data['name'],
                ],
                $data
            );
        }
    }
}
