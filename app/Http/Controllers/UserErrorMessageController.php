<?php

namespace App\Http\Controllers;

use App\Models\PlatformError;
use Illuminate\Http\Request;

class UserErrorMessageController extends Controller
{
    public function show(Request $request, string $reference)
    {
        $lang = substr((string) ($request->get('lang') ?: $request->header('Accept-Language') ?: 'en'), 0, 2);

        $error = PlatformError::where('reference_code', $reference)->first();
        if (!$error) {
            return response()->json([
                'reference' => $reference,
                'message' => $this->fallbackMessage($lang),
                'steps' => ['refresh', 'try again', 'contact support with the reference code'],
            ]);
        }

        $message = match ($lang) {
            'nl' => $error->ai_user_message_nl,
            'de' => $error->ai_user_message_de,
            default => $error->ai_user_message_en,
        } ?: $this->fallbackMessage($lang);

        return response()->json([
            'reference' => $reference,
            'message' => $message,
            'steps' => $error->ai_user_steps ?: ['refresh', 'try again', 'contact support with the reference code'],
        ]);
    }

    private function fallbackMessage(string $lang): string
    {
        return match ($lang) {
            'nl' => 'Er ging iets mis. Probeer het opnieuw. Als dit blijft gebeuren, neem contact op met support.',
            'de' => 'Es ist etwas schiefgelaufen. Bitte erneut versuchen. Falls das Problem bleibt, kontaktieren Sie den Support.',
            default => 'Something went wrong. Please try again. If it persists, contact support.',
        };
    }
}
