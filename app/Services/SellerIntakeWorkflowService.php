<?php

namespace App\Services;

use App\Models\BoatIntake;
use App\Models\BoatIntakePayment;
use App\Models\ListingWorkflow;
use App\Models\ListingWorkflowReview;
use App\Models\ListingWorkflowVersion;
use App\Models\User;
use App\Models\Yacht;
use App\Models\YachtImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SellerIntakeWorkflowService
{
    public function __construct(
        private readonly MollieService $mollie,
        private readonly SellerPublishGateService $sellerPublishGate,
        private readonly BoatImagePublicationStatusService $imageStatusService,
    ) {
    }

    public function getOrCreateDraft(User $user): BoatIntake
    {
        $draft = BoatIntake::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['draft_intake', 'awaiting_payment', 'paid'])
            ->latest('id')
            ->first();

        if ($draft) {
            return $draft->fresh(['latestPayment', 'listingWorkflow']);
        }

        $intake = BoatIntake::create([
            'user_id' => $user->id,
            'status' => 'draft_intake',
        ]);

        return $intake->load(['latestPayment', 'listingWorkflow']);
    }

    public function saveIntake(User $user, array $payload, ?BoatIntake $intake = null): BoatIntake
    {
        $intake ??= $this->getOrCreateDraft($user);

        $intake->fill([
            'brand' => $payload['brand'] ?? $intake->brand,
            'model' => $payload['model'] ?? $intake->model,
            'year' => $payload['year'] ?? $intake->year,
            'length_m' => $payload['length_m'] ?? $intake->length_m,
            'width_m' => $payload['width_m'] ?? $intake->width_m,
            'height_m' => $payload['height_m'] ?? $intake->height_m,
            'fuel_type' => $payload['fuel_type'] ?? $intake->fuel_type,
            'price' => $payload['price'] ?? $intake->price,
            'description' => $payload['description'] ?? $intake->description,
            'boat_type' => $payload['boat_type'] ?? $intake->boat_type,
        ]);
        $intake->status = 'awaiting_payment';
        $intake->submitted_at = now();
        $intake->save();

        return $intake->fresh(['latestPayment', 'listingWorkflow']);
    }

    /**
     * @param array<int, UploadedFile> $files
     */
    public function addPhotos(BoatIntake $intake, array $files): BoatIntake
    {
        $manifest = $intake->photo_manifest_json ?? [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->storePublicly("boat-intakes/{$intake->id}", 'public');
            $manifest[] = [
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'uploaded_at' => now()->toIso8601String(),
            ];
        }

        $intake->photo_manifest_json = $manifest;
        $intake->save();

        return $intake->fresh(['latestPayment', 'listingWorkflow']);
    }

    public function createPaymentSession(BoatIntake $intake, array $validated): BoatIntakePayment
    {
        $existing = $intake->payments()
            ->whereIn('status', ['open', 'pending'])
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $amount = number_format((float) config('services.seller_listing_intake.payment_amount', 395.00), 2, '.', '');
        $idempotencyKey = (string) ($validated['idempotency_key'] ?? Str::uuid());
        $payload = [
            'amount' => [
                'currency' => 'EUR',
                'value' => $amount,
            ],
            'description' => 'Seller listing intake ' . $intake->id,
            'redirectUrl' => $validated['redirect_url'],
            'webhookUrl' => config('services.mollie.webhook_url') ?: url('/api/webhooks/mollie'),
            'metadata' => [
                'payment_type' => 'seller_listing_intake',
                'boat_intake_id' => $intake->id,
                'user_id' => $intake->user_id,
            ],
        ];

        $response = $this->mollie->createPayment($payload, $idempotencyKey);

        $payment = BoatIntakePayment::create([
            'boat_intake_id' => $intake->id,
            'user_id' => $intake->user_id,
            'mollie_payment_id' => $response['id'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'status' => $response['status'] ?? 'open',
            'amount_currency' => 'EUR',
            'amount_value' => $amount,
            'checkout_url' => data_get($response, '_links.checkout.href'),
            'redirect_url' => $validated['redirect_url'],
            'metadata_json' => $payload['metadata'],
        ]);

        $intake->latest_payment_id = $payment->id;
        $intake->status = 'awaiting_payment';
        $intake->save();

        return $payment;
    }

    public function handlePaymentStatus(BoatIntakePayment $payment, string $status): ?ListingWorkflow
    {
        return DB::transaction(function () use ($payment, $status) {
            $payment->status = $status;
            $payment->webhook_events_count = (int) $payment->webhook_events_count + 1;
            if ($status === 'paid') {
                $payment->paid_at = now();
            }
            $payment->save();

            $intake = $payment->intake()->lockForUpdate()->firstOrFail();
            $intake->latest_payment_id = $payment->id;

            if ($status === 'paid') {
                $intake->status = 'paid';
                $intake->paid_at = now();

                $workflow = $intake->listingWorkflow()->first();
                if (! $workflow) {
                    $yacht = $this->createDraftYachtFromIntake($intake);
                    $gate = $this->sellerPublishGate->assessForUser($intake->user);
                    $workflow = ListingWorkflow::create([
                        'boat_intake_id' => $intake->id,
                        'user_id' => $intake->user_id,
                        'yacht_id' => $yacht->id,
                        'status' => $gate['allowed'] ? 'paid' : 'awaiting_seller_verification',
                        'seller_verification_required' => ! $gate['allowed'],
                        'seller_verification_expires_at' => $gate['onboarding']?->expires_at,
                        'paid_at' => now(),
                    ]);

                    $this->createSnapshot($workflow, 'intake_original', $this->buildIntakeSnapshot($intake, $yacht));
                    $intake->listing_workflow_id = $workflow->id;
                }
            }

            $intake->save();

            return $intake->fresh('listingWorkflow')->listingWorkflow;
        });
    }

    public function startAiGeneration(ListingWorkflow $workflow, ?User $actor = null): ListingWorkflow
    {
        return DB::transaction(function () use ($workflow, $actor) {
            $workflow->loadMissing('intake', 'yacht', 'user');
            $workflow->status = 'ai_generating';
            $workflow->save();

            $yacht = $workflow->yacht ?: $this->createDraftYachtFromIntake($workflow->intake);
            if (! $workflow->yacht_id) {
                $workflow->yacht_id = $yacht->id;
            }

            $title = trim(implode(' ', array_filter([
                $workflow->intake->brand,
                $workflow->intake->model,
                $workflow->intake->year,
            ])));
            $description = trim((string) $workflow->intake->description);
            if ($description === '') {
                $description = sprintf(
                    '%s %s from %s prepared after seller intake and payment.',
                    $workflow->intake->brand ?: 'Boat',
                    $workflow->intake->model ?: '',
                    $workflow->intake->year ?: 'unknown year'
                );
            }

            $yacht->fill([
                'boat_name' => $title !== '' ? $title : ($yacht->boat_name ?: 'Seller Intake Draft'),
                'manufacturer' => $workflow->intake->brand,
                'model' => $workflow->intake->model,
                'year' => $workflow->intake->year,
                'price' => $workflow->intake->price,
                'boat_type' => $workflow->intake->boat_type,
                'status' => 'Draft',
                'owners_comment' => $description,
                'short_description_en' => $description,
                'short_description_nl' => $description,
                'location_city' => $workflow->user->city,
            ]);
            $yacht->save();
            $yacht->saveSubTables([
                'loa' => $workflow->intake->length_m,
                'beam' => $workflow->intake->width_m,
                'minimum_height' => $workflow->intake->height_m,
                'fuel' => $workflow->intake->fuel_type,
            ]);

            $workflow->status = 'ai_generated';
            $workflow->ai_generated_at = now();
            $workflow->save();

            $this->createSnapshot($workflow, 'ai_generated', $this->buildWorkflowPayload($workflow->fresh(['intake', 'yacht'])), $actor);

            return $workflow->fresh(['intake', 'yacht', 'versions', 'reviews']);
        });
    }

    public function markReviewed(ListingWorkflow $workflow, User $actor, ?string $message = null): ListingWorkflow
    {
        return DB::transaction(function () use ($workflow, $actor, $message) {
            $workflow->status = 'awaiting_client_approval';
            $workflow->admin_reviewed_at = now();
            $workflow->last_review_message = $message;
            $workflow->assigned_admin_id = $actor->id;
            $workflow->save();

            ListingWorkflowReview::create([
                'listing_workflow_id' => $workflow->id,
                'actor_id' => $actor->id,
                'actor_role' => strtolower((string) $actor->role),
                'action' => 'admin_reviewed',
                'message' => $message,
            ]);

            $this->createSnapshot($workflow, 'admin_reviewed', $this->buildWorkflowPayload($workflow->fresh(['intake', 'yacht'])), $actor);

            return $workflow->fresh(['intake', 'yacht', 'versions', 'reviews', 'user']);
        });
    }

    public function approveByClient(ListingWorkflow $workflow, User $actor, ?string $message = null): ListingWorkflow
    {
        return DB::transaction(function () use ($workflow, $actor, $message) {
            $workflow->status = 'ready_to_publish';
            $workflow->client_approved_at = now();
            $workflow->ready_to_publish_at = now();
            $workflow->last_review_message = $message;
            $workflow->save();

            ListingWorkflowReview::create([
                'listing_workflow_id' => $workflow->id,
                'actor_id' => $actor->id,
                'actor_role' => strtolower((string) $actor->role),
                'action' => 'client_approved',
                'message' => $message,
            ]);

            $this->createSnapshot($workflow, 'client_approved', $this->buildWorkflowPayload($workflow->fresh(['intake', 'yacht'])), $actor);

            return $workflow->fresh(['intake', 'yacht', 'versions', 'reviews', 'user']);
        });
    }

    public function requestChanges(ListingWorkflow $workflow, User $actor, ?string $message = null): ListingWorkflow
    {
        return DB::transaction(function () use ($workflow, $actor, $message) {
            $workflow->status = 'changes_requested';
            $workflow->last_review_message = $message;
            $workflow->save();

            ListingWorkflowReview::create([
                'listing_workflow_id' => $workflow->id,
                'actor_id' => $actor->id,
                'actor_role' => strtolower((string) $actor->role),
                'action' => 'changes_requested',
                'message' => $message,
            ]);

            return $workflow->fresh(['intake', 'yacht', 'versions', 'reviews', 'user']);
        });
    }

    public function publish(ListingWorkflow $workflow, User $actor): ListingWorkflow
    {
        return DB::transaction(function () use ($workflow, $actor) {
            $workflow->loadMissing('yacht', 'user');

            if (! in_array($workflow->status, ['ready_to_publish', 'published'], true)) {
                throw new \RuntimeException('Listing must be client approved before publication.');
            }

            $sellerGate = $this->sellerPublishGate->assessForUser($workflow->user);
            if (! $sellerGate['allowed']) {
                throw new \RuntimeException($sellerGate['message'] ?? 'Seller verification is not valid.');
            }

            if (! $workflow->yacht) {
                throw new \RuntimeException('Listing draft yacht is missing.');
            }

            $imageStatus = $this->imageStatusService->summary($workflow->yacht);
            if ($imageStatus['status'] !== 'ready') {
                throw new \RuntimeException('Boat images are not ready for publication yet.');
            }

            $workflow->status = 'published';
            $workflow->published_at = now();
            $workflow->save();

            $workflow->yacht->status = 'For Sale';
            $workflow->yacht->save();

            ListingWorkflowReview::create([
                'listing_workflow_id' => $workflow->id,
                'actor_id' => $actor->id,
                'actor_role' => strtolower((string) $actor->role),
                'action' => 'published',
            ]);

            $this->createSnapshot($workflow, 'published', $this->buildWorkflowPayload($workflow->fresh(['intake', 'yacht'])), $actor);

            return $workflow->fresh(['intake', 'yacht', 'versions', 'reviews', 'user']);
        });
    }

    public function reject(ListingWorkflow $workflow, User $actor, ?string $message = null): ListingWorkflow
    {
        $workflow->status = 'rejected';
        $workflow->rejected_at = now();
        $workflow->last_review_message = $message;
        $workflow->save();

        ListingWorkflowReview::create([
            'listing_workflow_id' => $workflow->id,
            'actor_id' => $actor->id,
            'actor_role' => strtolower((string) $actor->role),
            'action' => 'rejected',
            'message' => $message,
        ]);

        return $workflow->fresh(['intake', 'yacht', 'versions', 'reviews', 'user']);
    }

    public function archive(ListingWorkflow $workflow, User $actor, ?string $message = null): ListingWorkflow
    {
        $workflow->status = 'archived';
        $workflow->archived_at = now();
        $workflow->last_review_message = $message;
        $workflow->save();

        ListingWorkflowReview::create([
            'listing_workflow_id' => $workflow->id,
            'actor_id' => $actor->id,
            'actor_role' => strtolower((string) $actor->role),
            'action' => 'archived',
            'message' => $message,
        ]);

        return $workflow->fresh(['intake', 'yacht', 'versions', 'reviews', 'user']);
    }

    public function serializeIntake(BoatIntake $intake): array
    {
        $intake->loadMissing(['latestPayment', 'listingWorkflow']);

        return [
            'id' => $intake->id,
            'status' => $intake->status,
            'brand' => $intake->brand,
            'model' => $intake->model,
            'year' => $intake->year,
            'length_m' => $intake->length_m !== null ? (float) $intake->length_m : null,
            'width_m' => $intake->width_m !== null ? (float) $intake->width_m : null,
            'height_m' => $intake->height_m !== null ? (float) $intake->height_m : null,
            'fuel_type' => $intake->fuel_type,
            'price' => $intake->price !== null ? (float) $intake->price : null,
            'description' => $intake->description,
            'boat_type' => $intake->boat_type,
            'photos' => $intake->photo_manifest_json ?? [],
            'photo_count' => count($intake->photo_manifest_json ?? []),
            'payment' => $intake->latestPayment ? [
                'id' => $intake->latestPayment->id,
                'status' => $intake->latestPayment->status,
                'checkout_url' => $intake->latestPayment->checkout_url,
                'paid_at' => $intake->latestPayment->paid_at?->toIso8601String(),
            ] : null,
            'listing_workflow_id' => $intake->listing_workflow_id ?: $intake->listingWorkflow?->id,
            'submitted_at' => $intake->submitted_at?->toIso8601String(),
            'paid_at' => $intake->paid_at?->toIso8601String(),
        ];
    }

    public function serializeWorkflow(ListingWorkflow $workflow): array
    {
        $workflow->loadMissing(['intake', 'yacht.images', 'user', 'assignedAdmin', 'versions', 'reviews']);
        $sellerGate = $this->sellerPublishGate->assessForUser($workflow->user);

        return [
            'id' => $workflow->id,
            'boat_intake_id' => $workflow->boat_intake_id,
            'user_id' => $workflow->user_id,
            'yacht_id' => $workflow->yacht_id,
            'status' => $workflow->status,
            'seller_verification_required' => $workflow->seller_verification_required,
            'seller_verification_valid' => $sellerGate['allowed'],
            'seller_verification_expires_at' => $workflow->seller_verification_expires_at?->toIso8601String(),
            'intake' => $this->serializeIntake($workflow->intake),
            'user' => [
                'id' => $workflow->user->id,
                'name' => $workflow->user->name,
                'email' => $workflow->user->email,
            ],
            'yacht' => $workflow->yacht ? [
                'id' => $workflow->yacht->id,
                'boat_name' => $workflow->yacht->boat_name,
                'status' => $workflow->yacht->status,
                'price' => $workflow->yacht->price,
                'manufacturer' => $workflow->yacht->manufacturer,
                'model' => $workflow->yacht->model,
                'year' => $workflow->yacht->year,
            ] : null,
            'assigned_admin' => $workflow->assignedAdmin ? [
                'id' => $workflow->assignedAdmin->id,
                'name' => $workflow->assignedAdmin->name,
            ] : null,
            'last_review_message' => $workflow->last_review_message,
            'paid_at' => $workflow->paid_at?->toIso8601String(),
            'ai_generated_at' => $workflow->ai_generated_at?->toIso8601String(),
            'admin_reviewed_at' => $workflow->admin_reviewed_at?->toIso8601String(),
            'client_approved_at' => $workflow->client_approved_at?->toIso8601String(),
            'ready_to_publish_at' => $workflow->ready_to_publish_at?->toIso8601String(),
            'published_at' => $workflow->published_at?->toIso8601String(),
            'preview' => $this->buildPreview($workflow),
            'versions' => $workflow->versions->map(fn (ListingWorkflowVersion $version) => [
                'id' => $version->id,
                'version_type' => $version->version_type,
                'created_at' => $version->created_at?->toIso8601String(),
            ])->values(),
            'reviews' => $workflow->reviews->map(fn (ListingWorkflowReview $review) => [
                'id' => $review->id,
                'action' => $review->action,
                'actor_role' => $review->actor_role,
                'message' => $review->message,
                'created_at' => $review->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    public function buildPreview(ListingWorkflow $workflow): array
    {
        $workflow->loadMissing('intake', 'yacht');
        $yacht = $workflow->yacht;
        $intake = $workflow->intake;

        return [
            'title' => $yacht?->boat_name ?: trim(implode(' ', array_filter([$intake->brand, $intake->model]))),
            'description' => $yacht?->short_description_en ?: $yacht?->owners_comment ?: $intake->description,
            'specs' => [
                'brand' => $intake->brand,
                'model' => $intake->model,
                'year' => $intake->year,
                'length_m' => $intake->length_m !== null ? (float) $intake->length_m : null,
                'width_m' => $intake->width_m !== null ? (float) $intake->width_m : null,
                'height_m' => $intake->height_m !== null ? (float) $intake->height_m : null,
                'fuel_type' => $intake->fuel_type,
                'price' => $intake->price !== null ? (float) $intake->price : null,
                'boat_type' => $intake->boat_type,
            ],
            'photos' => $intake->photo_manifest_json ?? [],
            'status' => $workflow->status,
        ];
    }

    private function createDraftYachtFromIntake(BoatIntake $intake): Yacht
    {
        $existing = ListingWorkflow::query()
            ->where('boat_intake_id', $intake->id)
            ->whereNotNull('yacht_id')
            ->latest('id')
            ->first();

        if ($existing?->yacht) {
            return $existing->yacht;
        }

        $yacht = Yacht::create([
            'user_id' => $intake->user_id,
            'boat_name' => trim(implode(' ', array_filter([$intake->brand, $intake->model]))) ?: 'Seller Intake Draft',
            'manufacturer' => $intake->brand,
            'model' => $intake->model,
            'year' => $intake->year,
            'price' => $intake->price,
            'boat_type' => $intake->boat_type,
            'status' => 'Draft',
            'owners_comment' => $intake->description,
            'offline_uuid' => (string) Str::uuid(),
        ]);

        $yacht->saveSubTables([
            'loa' => $intake->length_m,
            'beam' => $intake->width_m,
            'minimum_height' => $intake->height_m,
            'fuel' => $intake->fuel_type,
        ]);

        foreach ($intake->photo_manifest_json ?? [] as $index => $photo) {
            $path = (string) ($photo['path'] ?? '');
            if ($path === '') {
                continue;
            }

            YachtImage::query()->firstOrCreate([
                'yacht_id' => $yacht->id,
                'url' => $path,
            ], [
                'status' => 'approved',
                'sort_order' => $index,
                'original_name' => $photo['original_name'] ?? null,
            ]);
        }

        return $yacht->fresh();
    }

    private function createSnapshot(ListingWorkflow $workflow, string $type, array $payload, ?User $actor = null): ListingWorkflowVersion
    {
        return ListingWorkflowVersion::create([
            'listing_workflow_id' => $workflow->id,
            'yacht_id' => $workflow->yacht_id,
            'version_type' => $type,
            'created_by' => $actor?->id,
            'created_by_role' => $actor ? strtolower((string) $actor->role) : 'system',
            'payload_json' => $payload,
        ]);
    }

    private function buildIntakeSnapshot(BoatIntake $intake, Yacht $yacht): array
    {
        return [
            'intake' => $this->serializeIntake($intake->fresh(['latestPayment', 'listingWorkflow'])),
            'yacht' => [
                'id' => $yacht->id,
                'boat_name' => $yacht->boat_name,
                'manufacturer' => $yacht->manufacturer,
                'model' => $yacht->model,
                'year' => $yacht->year,
                'price' => $yacht->price,
                'status' => $yacht->status,
            ],
        ];
    }

    private function buildWorkflowPayload(ListingWorkflow $workflow): array
    {
        return [
            'workflow' => [
                'id' => $workflow->id,
                'status' => $workflow->status,
                'boat_intake_id' => $workflow->boat_intake_id,
                'yacht_id' => $workflow->yacht_id,
            ],
            'preview' => $this->buildPreview($workflow),
        ];
    }
}
