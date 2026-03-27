<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\IssueReport;
use App\Models\PlatformError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PlatformErrorController extends Controller
{
    public function index(Request $request)
    {
        $query = PlatformError::query();
        $search = trim((string) ($request->string('subject')->value() ?: $request->string('search')->value()));

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('level')) {
            $query->where('level', $request->string('level'));
        }
        if ($request->filled('source')) {
            $source = (string) $request->string('source');

            $query->where(function ($builder) use ($source) {
                $builder
                    ->where('source', $source)
                    ->orWhere('project', $source);
            });
        }
        if ($request->filled('project')) {
            $query->where('project', $request->string('project'));
        }
        if ($request->filled('environment')) {
            $query->where('environment', $request->string('environment'));
        }
        if ($request->filled('release')) {
            $query->where('release', $request->string('release'));
        }
        if ($request->filled('route')) {
            $query->where('route', 'like', '%'.$request->string('route').'%');
        }
        if ($request->filled('user_id')) {
            $query->where('tags->user_id', (string) $request->string('user_id'));
        }
        if ($request->filled('category')) {
            $query->where('ai_category', $request->string('category'));
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('message', 'like', '%'.$search.'%');
            });
        }
        if ($request->filled('from')) {
            $query->where('last_seen_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('last_seen_at', '<=', $request->date('to'));
        }

        $sortBy = $request->string('sort_by', 'last_seen_at');
        $sortDir = $request->string('sort_dir', 'desc');
        $allowedSorts = ['last_seen_at', 'first_seen_at', 'occurrences_count', 'users_affected', 'level'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'last_seen_at';
        }
        $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');

        $perPage = (int) $request->input('per_page', 25);
        $errors = $query->paginate($perPage);

        return response()->json($errors);
    }

    public function show(Request $request, PlatformError $error)
    {
        $includeReports = filter_var($request->query('include_reports', false), FILTER_VALIDATE_BOOL);
        if (! $includeReports) {
            return response()->json($error);
        }

        $reports = IssueReport::with('files')
            ->where('platform_error_id', $error->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (IssueReport $report) {
                $files = $report->files->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'original_name' => $file->original_name,
                        'mime_type' => $file->mime_type,
                        'size' => $file->size,
                        'url' => $file->storage_path ? Storage::disk($file->storage_disk)->url($file->storage_path) : null,
                    ];
                })->values();

                return array_merge($report->toArray(), [
                    'files' => $files,
                ]);
            });

        return response()->json([
            'error' => $error,
            'reports' => $reports,
        ]);
    }

    public function stats()
    {
        $last24h = PlatformError::where('last_seen_at', '>=', now()->subDay())->count();
        $critical = PlatformError::where('ai_severity', 'critical')->count();
        $regressions = PlatformError::where('status', 'unresolved')->whereNotNull('release')->count();
        $usersAffected = PlatformError::sum('users_affected');

        return response()->json([
            'errors_last_24h' => $last24h,
            'critical' => $critical,
            'regressions' => $regressions,
            'users_affected' => $usersAffected,
        ]);
    }

    public function resolve(PlatformError $error)
    {
        $error->status = 'resolved';
        $error->resolved_at = now();
        $error->save();

        return response()->json(['status' => 'resolved']);
    }

    public function ignore(Request $request, PlatformError $error)
    {
        $error->status = 'ignored';
        if ($request->filled('days')) {
            $error->ignore_until = now()->addDays((int) $request->input('days'));
        }
        if ($request->filled('until_release')) {
            $error->ignore_release = (string) $request->input('until_release');
        }
        $error->save();

        return response()->json(['status' => 'ignored']);
    }

    public function note(Request $request, PlatformError $error)
    {
        $error->internal_note = (string) $request->input('note');
        $error->save();

        return response()->json(['status' => 'noted']);
    }

    public function assign(Request $request, PlatformError $error)
    {
        $error->assigned_to_user_id = $request->input('user_id');
        $error->save();

        return response()->json(['status' => 'assigned']);
    }
}
