<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AdoptNumberLessons extends Command
{
    protected $signature = 'signia:adopt-number-lessons {--apply : Back up and apply the reviewed cleanup}';

    protected $description = 'Retire unused legacy number lessons when all ten canonical replacements exist (dry run by default)';

    public function handle(): int
    {
        try {
            DB::transaction(function () {
                $levels = DB::table('levels')->where('name', 'FSL Numbers')->whereNull('deleted_at')->lockForUpdate()->get();
                if ($levels->count() !== 1) {
                    throw new RuntimeException('Expected exactly one active FSL Numbers category. No changes made.');
                }
                $signs = DB::table('signs')->where('level_id', $levels->first()->id)->whereNull('deleted_at')->lockForUpdate()->get();
                $words = ['ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN'];
                $translations = ['Isa', 'Dalawa', 'Tatlo', 'Apat', 'Lima', 'Anim', 'Pito', 'Walo', 'Siyam', 'Sampu'];
                $pairs = [];
                $canonical = [];
                foreach ($words as $index => $word) {
                    $number = $index + 1;
                    $new = $signs->where('fsl_name', $word)->where('model_label', 'number')->values();
                    $old = $signs->where('name', "Number $number")->where('fsl_name', "$number / {$translations[$index]}")->values();
                    if ($new->count() !== 1 || $old->count() > 1) {
                        throw new RuntimeException("Missing or ambiguous replacement for $word. No changes made.");
                    }
                    $canonical[] = $new->first();
                    if ($old->isNotEmpty()) {
                        $pairs[] = [$old->first(), $new->first()];
                    }
                }
                if ($signs->count() !== 10 + count($pairs)) {
                    throw new RuntimeException('Unexpected extra number lessons need manual review. No changes made.');
                }
                if ($pairs === []) {
                    $this->info('Already using only the ten canonical number lessons. No changes made.');
                    return;
                }

                $oldIds = array_map(fn ($pair) => $pair[0]->id, $pairs);
                $questions = DB::table('questions')->whereIn('sign_id', $oldIds)->lockForUpdate()->get();
                $questionIds = $questions->pluck('id');
                if (DB::table('progress')->whereIn('sign_id', $oldIds)->exists()
                    || DB::table('gesture_logs')->whereIn('sign_id', $oldIds)->exists()
                    || DB::table('quiz_attempts')->whereIn('question_id', $questionIds)->exists()) {
                    throw new RuntimeException('Legacy lessons have learning history. Migrate that history before retiring them. No changes made.');
                }
                foreach ($pairs as [$old, $new]) {
                    foreach ($questions->where('sign_id', $old->id) as $question) {
                        $replacements = DB::table('questions')->where('sign_id', $new->id)
                            ->where('quiz_id', $question->quiz_id)
                            ->where('question_type', $question->question_type)
                            ->where('question_text', $question->question_text)
                            ->where('correct_answer', $new->fsl_name)->lockForUpdate()->get();
                        if ($replacements->count() !== 1 || $question->correct_answer !== $old->fsl_name) {
                            throw new RuntimeException("Question {$question->id} has no unambiguous equivalent. No changes made.");
                        }
                    }
                }
                $this->table(['Archive ID', 'Legacy lesson', 'Keep ID', 'Canonical lesson'],
                    array_map(fn ($pair) => [$pair[0]->id, $pair[0]->name, $pair[1]->id, $pair[1]->name], $pairs));
                $this->info("Retire ".count($pairs)." legacy lessons and {$questions->count()} unused duplicate questions; order ONE to TEN.");
                if (! $this->option('apply')) {
                    $this->info('Dry run only. Use --apply to save a backup and perform this cleanup.');
                    return;
                }

                $backup = 'curriculum-backups/number-lessons-'.now()->format('Ymd-His').'-'.Str::uuid().'.json';
                $snapshot = json_encode([
                    'created_at' => now()->toIso8601String(),
                    'level_id' => $levels->first()->id,
                    'signs' => $signs,
                    'questions' => $questions,
                    'choices' => DB::table('choices')->whereIn('question_id', $questionIds)->get(),
                ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                if (! Storage::disk('local')->put($backup, $snapshot)) {
                    throw new RuntimeException('Could not save backup. No changes made.');
                }
                // Only unused duplicate questions are removed. Their full rows and
                // choices are in the backup; existing canonical quiz IDs stay intact.
                DB::table('choices')->whereIn('question_id', $questionIds)->delete();
                DB::table('questions')->whereIn('id', $questionIds)->delete();
                DB::table('signs')->whereIn('id', $oldIds)->update(['deleted_at' => now(), 'updated_at' => now()]);
                foreach ($canonical as $index => $sign) {
                    DB::table('signs')->where('id', $sign->id)->update(['sort_order' => $index + 1, 'updated_at' => now()]);
                }
                $this->info('Backup: '.Storage::disk('local')->path($backup));
                $this->info('Cleanup applied. Refresh open lesson pages.');
            });
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
