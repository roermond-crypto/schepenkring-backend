<?php

namespace Database\Seeders;

use App\Models\KycQuestion;
use App\Models\KycRule;
use Illuminate\Database\Seeder;

class SellerOnboardingKycSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            [
                'key' => 'pep',
                'prompt' => 'Are you a politically exposed person (PEP)?',
                'input_type' => 'single_choice',
                'audience' => 'both',
                'seller_type_scope' => 'all',
                'required' => true,
                'sort_order' => 10,
                'options' => [
                    ['value' => 'yes', 'label' => 'Yes', 'score_delta' => 0, 'flag_code' => 'pep'],
                    ['value' => 'no', 'label' => 'No', 'score_delta' => 0],
                ],
            ],
            [
                'key' => 'pep_associate',
                'prompt' => 'Are you a family member or close associate of a PEP?',
                'input_type' => 'single_choice',
                'audience' => 'both',
                'seller_type_scope' => 'all',
                'required' => true,
                'sort_order' => 20,
                'options' => [
                    ['value' => 'yes', 'label' => 'Yes', 'score_delta' => 0, 'flag_code' => 'pep_associate'],
                    ['value' => 'no', 'label' => 'No', 'score_delta' => 0],
                ],
            ],
            [
                'key' => 'acting_for_third_party',
                'prompt' => 'Are you acting on behalf of someone else?',
                'input_type' => 'single_choice',
                'audience' => 'both',
                'seller_type_scope' => 'all',
                'required' => true,
                'sort_order' => 30,
                'options' => [
                    ['value' => 'yes', 'label' => 'Yes', 'score_delta' => 0, 'flag_code' => 'third_party_sale'],
                    ['value' => 'no', 'label' => 'No', 'score_delta' => 0],
                ],
            ],
            [
                'key' => 'beneficial_owner',
                'prompt' => 'Are you the beneficial owner of the boat sale proceeds?',
                'input_type' => 'single_choice',
                'audience' => 'seller',
                'seller_type_scope' => 'all',
                'required' => true,
                'sort_order' => 40,
                'options' => [
                    ['value' => 'yes', 'label' => 'Yes', 'score_delta' => 0],
                    ['value' => 'no', 'label' => 'No', 'score_delta' => 0, 'flag_code' => 'beneficial_owner_mismatch'],
                ],
            ],
            [
                'key' => 'boat_ownership_type',
                'prompt' => 'Is this boat owned privately or by a company?',
                'input_type' => 'single_choice',
                'audience' => 'seller',
                'seller_type_scope' => 'all',
                'required' => true,
                'sort_order' => 50,
                'options' => [
                    ['value' => 'private', 'label' => 'Privately', 'score_delta' => 0],
                    ['value' => 'company', 'label' => 'By a company', 'score_delta' => 0],
                ],
            ],
            [
                'key' => 'own_bank_account',
                'prompt' => 'Is the payout account your own bank account?',
                'input_type' => 'single_choice',
                'audience' => 'seller',
                'seller_type_scope' => 'all',
                'required' => true,
                'sort_order' => 60,
                'options' => [
                    ['value' => 'yes', 'label' => 'Yes', 'score_delta' => 0],
                    ['value' => 'no', 'label' => 'No', 'score_delta' => 0, 'flag_code' => 'third_party_bank_account'],
                ],
            ],
            [
                'key' => 'foreign_payout_account',
                'prompt' => 'Will funds come from or go to a foreign bank account?',
                'input_type' => 'single_choice',
                'audience' => 'seller',
                'seller_type_scope' => 'all',
                'required' => true,
                'sort_order' => 70,
                'options' => [
                    ['value' => 'yes', 'label' => 'Yes', 'score_delta' => 0, 'flag_code' => 'foreign_payout_account'],
                    ['value' => 'no', 'label' => 'No', 'score_delta' => 0],
                ],
            ],
            [
                'key' => 'intended_purchaser',
                'prompt' => 'Are you the intended purchaser?',
                'input_type' => 'single_choice',
                'audience' => 'buyer',
                'seller_type_scope' => 'all',
                'required' => true,
                'sort_order' => 80,
                'options' => [
                    ['value' => 'yes', 'label' => 'Yes', 'score_delta' => 0],
                    ['value' => 'no', 'label' => 'No', 'score_delta' => 20, 'flag_code' => 'intended_purchaser_mismatch'],
                ],
            ],
            [
                'key' => 'attend_test_drive_self',
                'prompt' => 'Will you attend the test drive yourself?',
                'input_type' => 'single_choice',
                'audience' => 'buyer',
                'seller_type_scope' => 'all',
                'required' => true,
                'sort_order' => 90,
                'options' => [
                    ['value' => 'yes', 'label' => 'Yes', 'score_delta' => 0],
                    ['value' => 'no', 'label' => 'No', 'score_delta' => 10, 'flag_code' => 'attending_third_party'],
                ],
            ],
            [
                'key' => 'buyer_purchase_type',
                'prompt' => 'Are you buying privately or through a company?',
                'input_type' => 'single_choice',
                'audience' => 'buyer',
                'seller_type_scope' => 'all',
                'required' => true,
                'sort_order' => 100,
                'options' => [
                    ['value' => 'private', 'label' => 'Privately', 'score_delta' => 0],
                    ['value' => 'company', 'label' => 'Through a company', 'score_delta' => 0],
                ],
            ],
            [
                'key' => 'requires_financing',
                'prompt' => 'Do you require financing?',
                'input_type' => 'single_choice',
                'audience' => 'buyer',
                'seller_type_scope' => 'all',
                'required' => true,
                'sort_order' => 110,
                'options' => [
                    ['value' => 'yes', 'label' => 'Yes', 'score_delta' => 10, 'flag_code' => 'requires_financing'],
                    ['value' => 'no', 'label' => 'No', 'score_delta' => 0],
                ],
            ],
            [
                'key' => 'buyer_own_bank_account',
                'prompt' => 'Will funds come from your own bank account?',
                'input_type' => 'single_choice',
                'audience' => 'buyer',
                'seller_type_scope' => 'all',
                'required' => true,
                'sort_order' => 120,
                'options' => [
                    ['value' => 'yes', 'label' => 'Yes', 'score_delta' => 0],
                    ['value' => 'no', 'label' => 'No', 'score_delta' => 20, 'flag_code' => 'third_party_bank_account'],
                ],
            ],
        ];

        foreach ($questions as $questionData) {
            $options = $questionData['options'];
            unset($questionData['options']);

            $question = KycQuestion::query()->updateOrCreate(
                ['key' => $questionData['key']],
                $questionData + ['is_active' => true]
            );

            $question->options()->delete();
            foreach ($options as $index => $option) {
                $question->options()->create([
                    'value' => $option['value'],
                    'label' => $option['label'],
                    'sort_order' => ($index + 1) * 10,
                    'score_delta' => $option['score_delta'] ?? 0,
                    'flag_code' => $option['flag_code'] ?? null,
                ]);
            }
        }

        $rules = [
            [
                'name' => 'PEP requires rejection review threshold',
                'audience' => 'both',
                'conditions_json' => ['question_key' => 'pep', 'operator' => 'equals', 'value' => 'yes'],
                'score_delta' => 50,
                'flag_code' => 'pep',
                'outcome_override' => 'manual_review',
                'priority' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'Third-party sale',
                'audience' => 'both',
                'conditions_json' => ['question_key' => 'acting_for_third_party', 'operator' => 'equals', 'value' => 'yes'],
                'score_delta' => 30,
                'flag_code' => 'third_party_sale',
                'outcome_override' => null,
                'priority' => 20,
                'is_active' => true,
            ],
            [
                'name' => 'Foreign payout account',
                'audience' => 'seller',
                'conditions_json' => ['question_key' => 'foreign_payout_account', 'operator' => 'equals', 'value' => 'yes'],
                'score_delta' => 20,
                'flag_code' => 'foreign_payout_account',
                'outcome_override' => null,
                'priority' => 30,
                'is_active' => true,
            ],
            [
                'name' => 'Buyer requires financing review',
                'audience' => 'buyer',
                'conditions_json' => ['question_key' => 'requires_financing', 'operator' => 'equals', 'value' => 'yes'],
                'score_delta' => 10,
                'flag_code' => 'requires_financing',
                'outcome_override' => null,
                'priority' => 40,
                'is_active' => true,
            ],
        ];

        foreach ($rules as $rule) {
            KycRule::query()->updateOrCreate(
                ['name' => $rule['name']],
                $rule
            );
        }
    }
}
