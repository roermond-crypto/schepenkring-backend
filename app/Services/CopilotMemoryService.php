<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CopilotAction;
use App\Models\CopilotActionSuggestion;
use App\Models\CopilotAuditEvent;
use App\Models\Faq;
use Illuminate\Support\Str;

class CopilotMemoryService
{
    public function __construct(private KnowledgeVectorStoreService $vectors)
    {
    }

    public function rememberAuditLog(AuditLog $log): bool
    {
        $path = (string) (($log->meta ?? [])['path'] ?? '');
        $text = trim(implode(' ', array_filter([
            $log->action,
            class_basename((string) ($log->entity_type ?? $log->target_type ?? '')),
            $path,
            json_encode($log->meta ?? [], JSON_UNESCAPED_SLASHES),
        ])));

        if ($text === '') {
            return false;
        }

        return $this->remember(
            'audit-log-' . $log->id,
            $text,
            [
                'kind' => 'audit_log',
                'audit_log_id' => $log->id,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'target_type' => $log->target_type,
                'path' => $path,
            ]
        );
    }

    public function rememberCopilotEvent(CopilotAuditEvent $event): bool
    {
        $actionId = $event->selected_action_id ?: $event->user_correction_action_id;
        $text = trim(implode(' ', array_filter([
            $event->input_text,
            $actionId,
            $event->status,
            $event->failure_reason,
        ])));

        if ($text === '') {
            return false;
        }

        return $this->remember(
            'copilot-event-' . $event->id,
            $text,
            [
                'kind' => 'copilot_event',
                'copilot_event_id' => $event->id,
                'action_id' => $actionId,
                'status' => $event->status,
                'source' => $event->source,
                'stage' => $event->stage,
            ]
        );
    }

    public function rememberAction(CopilotAction $action): bool
    {
        $text = trim(implode(' ', array_filter([
            $action->action_id,
            $action->title,
            $action->short_description,
            $action->description,
            implode(' ', $action->example_prompts ?? []),
        ])));

        if ($text === '') {
            return false;
        }

        return $this->remember(
            'copilot-action-' . $action->id,
            $text,
            [
                'kind' => 'copilot_action',
                'copilot_action_id' => $action->id,
                'action_id' => $action->action_id,
                'route_template' => $action->route_template,
                'module' => $action->module,
            ]
        );
    }

    public function rememberSuggestion(CopilotActionSuggestion $suggestion): bool
    {
        $text = trim(implode(' ', array_filter([
            $suggestion->action_id,
            $suggestion->title,
            $suggestion->short_description,
            $suggestion->description,
            implode(' ', collect($suggestion->phrases ?? [])->pluck('phrase')->filter()->all()),
        ])));

        if ($text === '') {
            return false;
        }

        return $this->remember(
            'copilot-suggestion-' . $suggestion->id,
            $text,
            [
                'kind' => 'copilot_suggestion',
                'copilot_suggestion_id' => $suggestion->id,
                'action_id' => $suggestion->action_id,
                'route_template' => $suggestion->route_template,
                'module' => $suggestion->module,
                'status' => $suggestion->status,
            ]
        );
    }

    public function rememberFaq(Faq $faq): bool
    {
        $text = trim(implode(' ', array_filter([
            $faq->question,
            $faq->answer,
            $faq->category,
            $faq->department,
            $faq->brand,
            $faq->model,
            implode(' ', $faq->tags ?? []),
            $faq->language,
            $faq->visibility,
            $faq->source_type,
            $faq->location_id ? 'location ' . $faq->location_id : null,
        ])));

        if ($text === '') {
            return false;
        }

        return $this->remember(
            'faq-' . $faq->id,
            $text,
            [
                'kind' => 'faq',
                'faq_id' => $faq->id,
                'location_id' => $faq->location_id,
                'category' => $faq->category,
                'language' => $faq->language,
                'department' => $faq->department,
                'visibility' => $faq->visibility,
                'brand' => $faq->brand,
                'model' => $faq->model,
                'tags' => $faq->tags ?? [],
                'tags_text' => implode(' ', $faq->tags ?? []),
                'source_type' => $faq->source_type ?: 'faq',
                'last_updated_at' => optional($faq->updated_at)->toIso8601String(),
                'last_indexed_at' => optional($faq->last_indexed_at)->toIso8601String(),
                'helpful' => (int) $faq->helpful,
                'not_helpful' => (int) $faq->not_helpful,
                'question' => Str::limit($faq->question, 250, ''),
                'answer' => Str::limit($faq->answer, 1000, ''),
            ]
        );
    }

    public function forgetFaq(Faq $faq): bool
    {
        return $this->forget('faq-' . $faq->id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchSimilar(string $text, int $topK = 5, array $filter = []): array
    {
        return $this->vectors->search($text, $topK, $filter);
    }

    private function remember(string $id, string $text, array $metadata = []): bool
    {
        return $this->vectors->upsertText($id, $text, $metadata);
    }

    private function forget(string $id): bool
    {
        return $this->vectors->delete($id);
    }
}
