<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faq;

class FaqSeeder extends Seeder
{
    public function run()
    {
        $faqs = [
            // General
            [
                'question' => 'What is Schepen Kring?',
                'answer' => 'Schepen Kring is a premium maritime platform connecting yacht owners, charter companies, and maritime enthusiasts. We specialize in yacht management, bookings, and maritime services.',
                'category' => 'General'
            ],
            [
                'question' => 'How do I create an account?',
                'answer' => 'Click the "Register" button on the top right corner, fill in your details, verify your email, and complete your profile setup.',
                'category' => 'General'
            ],
            
            // Booking
            [
                'question' => 'How do I book a yacht?',
                'answer' => 'Browse available yachts, select your preferred dates, fill in booking details, and proceed to payment. You\'ll receive confirmation within 24 hours.',
                'category' => 'Booking'
            ],
            [
                'question' => 'What is the cancellation policy?',
                'answer' => 'Cancellations made 30+ days before departure: 90% refund. 15-30 days: 50% refund. Less than 15 days: no refund. Please check specific yacht terms.',
                'category' => 'Booking'
            ],
            
            // Technical
            [
                'question' => 'What safety equipment is provided?',
                'answer' => 'All yachts include life jackets, fire extinguishers, first aid kits, navigation equipment, and emergency communication devices.',
                'category' => 'Technical'
            ],
            [
                'question' => 'Do I need a license to operate a yacht?',
                'answer' => 'For yachts under 15 meters, no license is required in Dutch waters. For larger vessels or international waters, proper certification is needed.',
                'category' => 'Technical'
            ],
            
            // Payment
            [
                'question' => 'What payment methods do you accept?',
                'answer' => 'We accept Visa, MasterCard, American Express, bank transfers, and cryptocurrency for certain bookings.',
                'category' => 'Payment'
            ],
            [
                'question' => 'Are there any hidden fees?',
                'answer' => 'No hidden fees. All charges are clearly displayed during booking: base rate, cleaning fee, security deposit, and optional extras.',
                'category' => 'Payment'
            ]
        ];
        
        foreach ($faqs as $faq) {
            Faq::create($faq);
        }
        
        // Initial Gemini training
        cache()->put('gemini_last_trained', now()->toDateTimeString());
        cache()->put('gemini_faq_count', count($faqs));
    }
}