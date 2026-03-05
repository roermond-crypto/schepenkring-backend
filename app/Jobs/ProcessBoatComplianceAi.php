<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Yacht;
use App\Models\BoatDocument;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistItem;
use App\Models\BoatChecklistStatus;
use Illuminate\Support\Facades\Log;

class ProcessBoatComplianceAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $boatId;

    public function __construct($boatId)
    {
        $this->boatId = $boatId;
    }

    public function handle(): void
    {
        $yacht = Yacht::find($this->boatId);
        if (!$yacht) {
            Log::error("ProcessBoatComplianceAi: Yacht {$this->boatId} not found.");
            return;
        }

        Log::info("Starting AI Compliance Check for Yacht {$this->boatId}");

        $templates = ChecklistTemplate::with('items')->where(function($q) use ($yacht) {
            $q->where('boat_type_id', $yacht->boat_type_id)
              ->orWhereNull('boat_type_id');
        })->where('active', true)->get();

        $documents = BoatDocument::where('boat_id', $this->boatId)->get();

        if ($templates->isEmpty()) {
            Log::info("No checklist templates found for Yacht {$this->boatId}");
            return;
        }

        // Placeholder for AI Logic integration (Gemini -> Pinecone -> ChatGPT)
        
        foreach ($templates as $template) {
            foreach ($template->items as $item) {
                BoatChecklistStatus::updateOrCreate(
                    [
                        'boat_id' => $yacht->id,
                        'checklist_item_id' => $item->id,
                    ],
                    [
                        'status' => 'pending_ai_review',
                    ]
                );
            }
        }

        Log::info("Successfully staged compliance check items for AI Review - Yacht {$this->boatId}");
    }
}
