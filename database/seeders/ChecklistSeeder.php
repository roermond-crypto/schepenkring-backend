<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BoatType;
use App\Models\BoatCheck;

class ChecklistSeeder extends Seeder
{
    public function run()
    {
        // ========== 1. BOAT TYPES ==========
        $sailboat = BoatType::firstOrCreate(['name' => 'Sailboat']);
        $motorboat = BoatType::firstOrCreate(['name' => 'Motorboat']);
        $yacht = BoatType::firstOrCreate(['name' => 'Yacht']);
        $catamaran = BoatType::firstOrCreate(['name' => 'Catamaran']);

        // ========== 2. CHECKLIST QUESTIONS ==========
        // Generic questions (apply to all boat types) – no boat type attached
        $genericQuestions = [
            [
                'question_text' => 'Is the hull in good condition?',
                'type' => 'YES_NO',
                'required' => true,
                'ai_prompt' => 'Examine hull photos for cracks, blisters, or damage.',
                'evidence_sources' => ['photos', 'documents'],
                'weight' => 'high',
            ],
            [
                'question_text' => 'What is the engine hour reading?',
                'type' => 'TEXT',
                'required' => false,
                'ai_prompt' => 'Look at engine hour meter in photos or spec JSON.',
                'evidence_sources' => ['photos', 'spec_json'],
                'weight' => 'medium',
            ],
            [
                'question_text' => 'Are there any known defects?',
                'type' => 'TEXT',
                'required' => false,
                'ai_prompt' => 'Review documents and owner comments for reported defects.',
                'evidence_sources' => ['documents'],
                'weight' => 'high',
            ],
            [
                'question_text' => 'Last service date?',
                'type' => 'DATE',
                'required' => false,
                'ai_prompt' => 'Find service records in documents.',
                'evidence_sources' => ['documents'],
                'weight' => 'medium',
            ],
        ];

        // Sailboat-specific questions
        $sailboatQuestions = [
            [
                'question_text' => 'Is the mast in good condition?',
                'type' => 'YES_NO',
                'required' => true,
                'ai_prompt' => 'Inspect mast photos for corrosion or bends.',
                'evidence_sources' => ['photos'],
                'weight' => 'high',
            ],
            [
                'question_text' => 'Are the sails in good condition?',
                'type' => 'YES_NO',
                'required' => true,
                'ai_prompt' => 'Check sail photos for tears or wear.',
                'evidence_sources' => ['photos'],
                'weight' => 'high',
            ],
        ];

        // Motorboat-specific questions
        $motorboatQuestions = [
            [
                'question_text' => 'Are the engines running smoothly?',
                'type' => 'YES_NO',
                'required' => true,
                'ai_prompt' => 'Review engine photos and service documents.',
                'evidence_sources' => ['photos', 'documents'],
                'weight' => 'high',
            ],
            [
                'question_text' => 'What is the fuel capacity?',
                'type' => 'TEXT',
                'required' => false,
                'ai_prompt' => 'Extract fuel capacity from spec JSON.',
                'evidence_sources' => ['spec_json'],
                'weight' => 'medium',
            ],
        ];

        // Create generic questions (no boat type attached)
        foreach ($genericQuestions as $q) {
            BoatCheck::firstOrCreate(
                ['question_text' => $q['question_text']],
                $q
            );
        }

        // Create sailboat questions and attach to sailboat type
        foreach ($sailboatQuestions as $q) {
            $question = BoatCheck::firstOrCreate(
                ['question_text' => $q['question_text']],
                $q
            );
            $question->boatTypes()->syncWithoutDetaching([$sailboat->id]);
        }

        // Create motorboat questions and attach to motorboat type
        foreach ($motorboatQuestions as $q) {
            $question = BoatCheck::firstOrCreate(
                ['question_text' => $q['question_text']],
                $q
            );
            $question->boatTypes()->syncWithoutDetaching([$motorboat->id]);
        }

        // A question that applies to both sailboat and motorboat
        $navQuestion = BoatCheck::firstOrCreate(
            ['question_text' => 'Is the navigation equipment functional?'],
            [
                'type' => 'YES_NO',
                'required' => true,
                'ai_prompt' => 'Check photos of helm and navigation electronics.',
                'evidence_sources' => ['photos'],
                'weight' => 'medium',
            ]
        );
        $navQuestion->boatTypes()->syncWithoutDetaching([$sailboat->id, $motorboat->id]);

        $this->command->info('Checklist seeded successfully!');
    }
}