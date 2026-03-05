<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessChatAttachment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $attachmentId)
    {
    }

    public function handle(): void
    {
        $attachment = Attachment::find($this->attachmentId);
        if (!$attachment) {
            return;
        }

        if (!filter_var(env('CHAT_OCR_ENABLED', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $path = $attachment->storage_key;
        if (!Storage::disk('public')->exists($path)) {
            return;
        }

        // Placeholder for OCR/Vision pipeline. Implement provider integration later.
        Log::info('OCR placeholder executed', ['attachment_id' => $attachment->id]);

        $attachment->extracted_text = $attachment->extracted_text ?? null;
        $attachment->ai_summary = $attachment->ai_summary ?? null;
        $attachment->save();

        if ($attachment->message_id) {
            $message = Message::find($attachment->message_id);
            if ($message) {
                Message::create([
                    'conversation_id' => $message->conversation_id,
                    'sender_type' => 'system',
                    'text' => 'Attachment received and queued for analysis.',
                    'channel' => $message->channel,
                    'message_type' => 'system',
                ]);
            }
        }
    }
}
