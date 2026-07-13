<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Mail\BoatIntakeConfirmationMail;
use App\Mail\BoatIntakeLocationNotificationMail;
use App\Models\AuditLog;
use App\Models\BoatIntake;
use App\Models\BoatIntakeFile;
use App\Models\Location;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use App\Models\Yacht;
use App\Services\BoatIntakeScoreService;
use App\Services\EmailVerificationCodeService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BoatIntakeController extends Controller
{
    public function __construct(
        private BoatIntakeScoreService $scorer,
        private EmailVerificationCodeService $verificationCodes,
    ) {}

    // ── POST /api/boat-intake ────────────────────────────────
    // Create a new intake (submit step 1 + basic boat info)

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'seller_first_name'  => 'required|string|max:100',
            'seller_last_name'   => 'required|string|max:100',
            'seller_email'       => 'required|email|max:255',
            'seller_phone'       => 'required|string|max:50',
            'seller_address'     => 'nullable|string|max:300',
            'seller_postal_code' => 'nullable|string|max:20',
            'seller_city'        => 'nullable|string|max:100',
            'seller_country'     => 'nullable|string|max:10',
            'seller_lat'         => 'nullable|numeric',
            'seller_lng'         => 'nullable|numeric',
            'location_id'        => 'required|integer|exists:locations,id',
            'boat_brand'         => 'required|string|max:150',
            'boat_model'         => 'required|string|max:150',
            'boat_name'          => 'nullable|string|max:150',
            'build_year'         => 'nullable|integer|min:1900|max:2030',
            'length_m'           => 'nullable|numeric|min:0',
            'width_m'            => 'nullable|numeric|min:0',
            'draft_m'            => 'nullable|numeric|min:0',
            'asking_price'       => 'required|numeric|min:1',
            'vat_status'         => 'nullable|string|in:included,excluded,marginal,unknown',
            'ce_category'        => 'nullable|string|in:A,B,C,D,none,unknown',
            'boat_type'          => 'nullable|string|max:100',
            'short_description'  => 'nullable|string|max:5000',
            'source_url'         => 'nullable|string|max:500',
        ]);

        [$intake, $score, $resumeToken] = DB::transaction(function () use ($data, $request) {
            $descLen = strlen($data['short_description'] ?? '');
            $resumeToken = Str::random(64);
            $seller = $this->findOrCreateSeller($data, $request);

            $intake = BoatIntake::create([
                ...$data,
                'seller_user_id'           => $seller->id,
                'status'                   => 'frontend_draft',
                'description_length'       => $descLen,
                'resume_token'             => $resumeToken,
                'resume_token_expires_at'  => now()->addDays(30),
                'ip_address'               => $request->ip(),
                'user_agent'               => substr($request->userAgent() ?? '', 0, 500),
            ]);

            // Calculate initial score
            $score = $this->scorer->calculate($intake);
            $status = $this->scorer->deriveStatus($intake, $score);
            $intake->update([
                'intake_score'     => $score['total'],
                'score_breakdown'  => $score['breakdown'],
                'missing_items'    => $score['missing'],
                'status'           => $status,
            ]);

            // Create draft yacht
            $yacht = $this->createDraftYacht($intake);
            $intake->update(['yacht_id' => $yacht->id]);

            // Audit
            $this->audit('boat_intake_submitted', $intake, $request);

            return [$intake, $score, $resumeToken];
        });

        // Send emails after the transaction commits so SMTP errors never roll back the record
        $this->sendSellerConfirmation($intake, $score, $resumeToken);
        $this->notifyLocation($intake);
        $intake->refresh(); // pick up confirmation_sent flag set by sendSellerConfirmation

        return response()->json([
            'intake'       => $this->serialize($intake, $score),
            'resume_token' => $resumeToken,
        ], 201);
    }

    // ── GET /api/boat-intake/{token} ──────────────────────────
    // Load intake by resume token (for seller returning via email link)

    public function showByToken(Request $request, string $token): JsonResponse
    {
        $intake = BoatIntake::where('resume_token', $token)
            ->where('resume_token_expires_at', '>', now())
            ->with(['location:id,name,code', 'files'])
            ->firstOrFail();

        $score = $this->scorer->calculate($intake);

        return response()->json(['intake' => $this->serialize($intake, $score)]);
    }

    // ── POST /api/boat-intake/{token}/photos ─────────────────
    // Upload photos; token-gated

    public function uploadPhotos(Request $request, string $token): JsonResponse
    {
        $intake = $this->resolveToken($token);
        $request->validate([
            'photos'   => 'required|array|min:1|max:30',
            'photos.*' => 'required|file|image|max:20480', // 20 MB each
        ]);

        $uploaded = [];
        foreach ($request->file('photos', []) as $file) {
            $path = $file->store("intakes/{$intake->id}/photos", 'public');
            $record = BoatIntakeFile::create([
                'boat_intake_id' => $intake->id,
                'type'           => 'photo',
                'original_name'  => $file->getClientOriginalName(),
                'path'           => $path,
                'mime_type'      => $file->getMimeType(),
                'size'           => $file->getSize(),
                'disk'           => 'public',
                'sort_order'     => $intake->photo_count + count($uploaded),
            ]);
            $uploaded[] = ['id' => $record->id, 'url' => Storage::disk('public')->url($path)];
        }

        $newCount = $intake->photos()->count();
        $intake->update(['photo_count' => $newCount]);

        // Recalculate score
        $score = $this->scorer->calculate($intake->fresh());
        $intake->fresh()->update([
            'intake_score'    => $score['total'],
            'score_breakdown' => $score['breakdown'],
            'missing_items'   => $score['missing'],
            'status'          => $this->scorer->deriveStatus($intake->fresh(), $score),
        ]);

        return response()->json([
            'uploaded' => $uploaded,
            'photo_count' => $newCount,
            'score' => $score,
        ]);
    }

    // ── DELETE /api/boat-intake/{token}/photos/{photoId} ─────
    // Remove a single uploaded photo; token-gated

    public function deletePhoto(string $token, int $photoId): JsonResponse
    {
        $intake = $this->resolveToken($token);

        $photo = BoatIntakeFile::where('id', $photoId)
            ->where('boat_intake_id', $intake->id)
            ->where('type', 'photo')
            ->firstOrFail();

        Storage::disk($photo->disk ?? 'public')->delete($photo->path);
        $photo->delete();

        $newCount = $intake->photos()->count();
        $intake->update(['photo_count' => $newCount]);

        $score = $this->scorer->calculate($intake->fresh());
        $intake->fresh()->update([
            'intake_score'    => $score['total'],
            'score_breakdown' => $score['breakdown'],
            'missing_items'   => $score['missing'],
            'status'          => $this->scorer->deriveStatus($intake->fresh(), $score),
        ]);

        return response()->json([
            'deleted' => true,
            'photo_count' => $newCount,
            'score' => $score,
        ]);
    }

    // ── POST /api/boat-intake/{token}/documents ───────────────
    // Upload documents; token-gated

    public function uploadDocuments(Request $request, string $token): JsonResponse
    {
        $intake = $this->resolveToken($token);
        $request->validate([
            'documents'        => 'required|array|min:1|max:20',
            'documents.*'      => 'required|file|max:30720', // 30 MB each
            'document_type'    => 'nullable|string|in:ce_certificate,vat_document,invoice,registration,maintenance,other',
        ]);

        $type = $request->input('document_type', 'other');
        $uploaded = [];

        foreach ($request->file('documents', []) as $file) {
            $path = $file->store("intakes/{$intake->id}/docs", 'public');
            $record = BoatIntakeFile::create([
                'boat_intake_id' => $intake->id,
                'type'           => $type,
                'original_name'  => $file->getClientOriginalName(),
                'path'           => $path,
                'mime_type'      => $file->getMimeType(),
                'size'           => $file->getSize(),
                'disk'           => 'public',
            ]);
            $uploaded[] = ['id' => $record->id, 'name' => $file->getClientOriginalName(), 'type' => $type];
        }

        $docCount = $intake->documents()->count();
        $intake->update(['document_count' => $docCount]);

        $score = $this->scorer->calculate($intake->fresh());
        $intake->fresh()->update([
            'intake_score'    => $score['total'],
            'score_breakdown' => $score['breakdown'],
            'missing_items'   => $score['missing'],
            'status'          => $this->scorer->deriveStatus($intake->fresh(), $score),
        ]);

        return response()->json([
            'uploaded' => $uploaded,
            'document_count' => $docCount,
            'score' => $score,
        ]);
    }

    // ── GET /api/admin/boat-intakes ───────────────────────────
    // Admin list of intakes

    public function adminIndex(Request $request): JsonResponse
    {
        $query = BoatIntake::with(['location:id,name', 'yacht:id,boat_name,status'])
            ->orderByDesc('created_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($locationId = $request->integer('location_id')) {
            $query->where('location_id', $locationId);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('seller_email', 'like', "%{$search}%")
                  ->orWhere('seller_first_name', 'like', "%{$search}%")
                  ->orWhere('seller_last_name', 'like', "%{$search}%")
                  ->orWhere('boat_brand', 'like', "%{$search}%")
                  ->orWhere('boat_model', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 30);
        $items   = $query->paginate($perPage);

        return response()->json([
            'data'      => $items->map(fn ($i) => $this->adminSerialize($i)),
            'total'     => $items->total(),
            'page'      => $items->currentPage(),
            'last_page' => $items->lastPage(),
        ]);
    }

    // ── GET /api/admin/boat-intakes/{id} ─────────────────────

    public function adminShow(Request $request, BoatIntake $boatIntake): JsonResponse
    {
        $boatIntake->load(['location:id,name,email,phone', 'yacht', 'files']);
        $score = $this->scorer->calculate($boatIntake);
        return response()->json(['intake' => $this->adminSerialize($boatIntake, $score)]);
    }

    // ── POST /api/admin/boat-intakes/{id}/promote ─────────────
    // Admin promotes intake to full yacht (removes draft status)

    public function promote(Request $request, BoatIntake $boatIntake): JsonResponse
    {
        if (! $boatIntake->yacht_id) {
            $yacht = $this->createDraftYacht($boatIntake);
            $boatIntake->update(['yacht_id' => $yacht->id]);
        } else {
            Yacht::where('id', $boatIntake->yacht_id)->update(['status' => 'for_sale']);
        }

        $boatIntake->update(['status' => 'accepted']);
        $this->audit('boat_intake_promoted', $boatIntake, $request);

        return response()->json([
            'message'  => 'Intake gepromoveerd naar actief aanbod.',
            'yacht_id' => $boatIntake->yacht_id,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     */
    private function findOrCreateSeller(array $data, Request $request): User
    {
        $existing = User::where('email', $data['seller_email'])->first();
        if ($existing) {
            return $existing;
        }

        $seller = User::create([
            'name'               => trim("{$data['seller_first_name']} {$data['seller_last_name']}"),
            'first_name'         => $data['seller_first_name'],
            'last_name'          => $data['seller_last_name'],
            'email'              => $data['seller_email'],
            'phone'              => $data['seller_phone'],
            // The seller can now log in via the auto-login-on-resume-token
            // path (see session()) without ever knowing this password. It
            // only guards the normal email+password login route in case
            // they later try that instead.
            'password'           => Hash::make(Str::random(32)),
            'type'               => UserType::SELLER,
            'role'               => 'seller',
            'status'             => UserStatus::EMAIL_PENDING,
            'client_location_id' => $data['location_id'] ?? null,
            'address_line1'      => $data['seller_address'] ?? null,
            'city'               => $data['seller_city'] ?? null,
            'postal_code'        => $data['seller_postal_code'] ?? null,
            'country'            => $data['seller_country'] ?? null,
        ]);

        // Real verification-code email, same mechanism the normal signup
        // flow uses (EmailVerificationCodeController) — previously this
        // account got no verification email of any kind.
        try {
            $this->verificationCodes->issue($seller, null, $request->header('Accept-Language'));
        } catch (\Throwable $e) {
            Log::error('[BoatIntake] Seller verification email FAILED', [
                'seller_id' => $seller->id,
                'email'     => $seller->email,
                'error'     => $e->getMessage(),
            ]);
        }

        return $seller;
    }

    private function resolveToken(string $token): BoatIntake
    {
        return BoatIntake::where('resume_token', $token)
            ->where('resume_token_expires_at', '>', now())
            ->firstOrFail();
    }

    private function createDraftYacht(BoatIntake $intake): Yacht
    {
        return Yacht::create([
            'location_id'         => $intake->location_id,
            // Employee-scoped visibility (visibleYachtsQuery()) filters on
            // ref_harbor_id, not location_id — without this the yacht is
            // created successfully but invisible to location staff.
            'ref_harbor_id'       => $intake->location_id,
            'user_id'             => $intake->seller_user_id,
            'seller_id'           => $intake->seller_user_id,
            'status'              => 'draft',
            'source'              => 'public_intake',
            'boat_name'           => $intake->boat_name ?: "{$intake->boat_brand} {$intake->boat_model}",
            'manufacturer'        => $intake->boat_brand,
            'model'               => $intake->boat_model,
            'year'                => $intake->build_year,
            'price'               => $intake->asking_price,
            'short_description_nl' => $intake->short_description,
            'completeness_score'   => $intake->intake_score,
            'completeness_breakdown' => $intake->score_breakdown,
        ]);
    }

    private function sendSellerConfirmation(BoatIntake $intake, array $score, string $token): void
    {
        try {
            $frontendBase = rtrim(config('app.frontend_url', 'https://schepenkring.nl'), '/');
            $resumeUrl = "{$frontendBase}/nl/boot-aanmelden/aanvullen?token={$token}";

            Mail::to($intake->seller_email)->send(
                new BoatIntakeConfirmationMail($intake, $score, $resumeUrl)
            );

            $intake->update([
                'confirmation_sent'    => true,
                'confirmation_sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[BoatIntake] Seller confirmation FAILED', [
                'intake_id'    => $intake->id,
                'seller_email' => $intake->seller_email,
                'error'        => $e->getMessage(),
                'class'        => get_class($e),
                'file'         => $e->getFile() . ':' . $e->getLine(),
                'trace'        => $e->getTraceAsString(),
            ]);
        }
    }

    private function notifyLocation(BoatIntake $intake): void
    {
        try {
            $location = Location::find($intake->location_id);
            if (! $location) return;

            // Prefer the location's own email; fall back to a global admin
            // address so a location with no email on file doesn't silently
            // drop the notification entirely.
            $recipient = $location->email ?: config('mail.admin_notification_address');
            if ($recipient) {
                Mail::to($recipient)->send(
                    new BoatIntakeLocationNotificationMail($intake, $location)
                );
            } else {
                Log::warning('[BoatIntake] No location email and no ADMIN_NOTIFICATION_EMAIL configured — notification not sent', [
                    'intake_id'   => $intake->id,
                    'location_id' => $intake->location_id,
                ]);
            }

            // Also notify location staff via in-app notification
            $staffIds = User::whereHas('locations', fn ($q) => $q->where('locations.id', $intake->location_id))
                ->pluck('id');

            foreach ($staffIds as $staffId) {
                \App\Models\AppNotification::notify(
                    $staffId,
                    'boat_intake',
                    'Nieuwe boot aanmelding',
                    "{$intake->boat_brand} {$intake->boat_model} — {$intake->sellerFullName()}",
                    ['intake_id' => $intake->id],
                    'info',
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[BoatIntake] Location notification failed: ' . $e->getMessage());
        }
    }

    // ── POST /api/boat-intake/{token}/resend-confirmation ────
    // Resend the seller confirmation email (for when SMTP fails on first attempt)

    public function resendConfirmation(Request $request, string $token): JsonResponse
    {
        $intake = $this->resolveToken($token);
        $score  = $this->scorer->calculate($intake);

        $this->sendSellerConfirmation($intake, $score, $token);
        $intake->refresh();

        return response()->json([
            'confirmation_sent' => (bool) $intake->confirmation_sent,
        ]);
    }

    // ── POST /api/boat-intake/{token}/session ─────────────────
    // Exchange the resume token for a real Sanctum session on the seller
    // account created by store() — closes the "user has to register again"
    // gap: arriving via this token (only ever delivered to the seller's own
    // email) is treated as equivalent proof of email ownership, the same
    // trust assumption every magic-link login relies on. Login itself is
    // never blocked on verification here (matching the requested flow:
    // login first, verify after) — the account's own verification-code
    // email was already sent by findOrCreateSeller() and can still be
    // completed from the dashboard.

    public function session(Request $request, string $token): JsonResponse
    {
        $intake = $this->resolveToken($token);

        $seller = User::find($intake->seller_user_id);
        if (! $seller) {
            abort(404, 'No account is associated with this intake.');
        }

        if (! $seller->hasVerifiedEmail()) {
            if ($seller->markEmailAsVerified()) {
                event(new Verified($seller));
            }
        }

        $seller->forceFill([
            'status'         => UserStatus::ACTIVE,
            'last_login_at'  => now(),
        ])->save();

        $tokenName = $request->input('device_name', 'web');
        $plainTextToken = $seller->createToken(is_string($tokenName) ? $tokenName : 'web')->plainTextToken;

        $this->audit('boat_intake_auto_login', $intake, $request);

        return response()->json([
            'token' => $plainTextToken,
            'user'  => new UserResource($seller->load(['locations', 'clientLocation'])),
        ]);
    }

    private function serialize(BoatIntake $intake, array $score): array
    {
        return [
            'id'                 => $intake->id,
            'status'             => $intake->status,
            'intake_score'       => $score['total'],
            'score'              => $score,
            'location'           => $intake->location ? ['id' => $intake->location->id, 'name' => $intake->location->name] : null,
            'boat_brand'         => $intake->boat_brand,
            'boat_model'         => $intake->boat_model,
            'asking_price'       => $intake->asking_price,
            'photo_count'        => $intake->photo_count,
            'document_count'     => $intake->document_count,
            'description_length' => $intake->description_length,
            'missing_items'      => $score['missing'],
            'confirmation_sent'  => (bool) $intake->confirmation_sent,
            'created_at'         => $intake->created_at?->toIso8601String(),
        ];
    }

    private function adminSerialize(BoatIntake $intake, ?array $score = null): array
    {
        return [
            'id'              => $intake->id,
            'status'          => $intake->status,
            'intake_score'    => $intake->intake_score,
            'score_breakdown' => $intake->score_breakdown,
            'missing_items'   => $intake->missing_items,
            'seller_name'     => $intake->sellerFullName(),
            'seller_email'    => $intake->seller_email,
            'seller_phone'    => $intake->seller_phone,
            'location'        => $intake->location ? ['id' => $intake->location->id, 'name' => $intake->location->name] : null,
            'boat_brand'      => $intake->boat_brand,
            'boat_model'      => $intake->boat_model,
            'boat_name'       => $intake->boat_name,
            'asking_price'    => $intake->asking_price,
            'photo_count'     => $intake->photo_count,
            'document_count'  => $intake->document_count,
            'yacht_id'        => $intake->yacht_id,
            'created_at'      => $intake->created_at?->toIso8601String(),
        ];
    }

    private function audit(string $action, BoatIntake $intake, Request $request): void
    {
        try {
            AuditLog::create([
                'action'      => $action,
                'risk_level'  => 'low',
                'result'      => 'success',
                'actor_id'    => $request->user()?->id,
                'entity_type' => 'boat_intake',
                'entity_id'   => $intake->id,
                'location_id' => $intake->location_id,
                'meta'        => [
                    'boat_brand'   => $intake->boat_brand,
                    'seller_email' => $intake->seller_email,
                    'status'       => $intake->status,
                    'score'        => $intake->intake_score,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[BoatIntake] Audit failed: ' . $e->getMessage());
        }
    }
}
