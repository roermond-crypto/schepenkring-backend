<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\BlogTranslation;
use App\Services\ContentTranslationService;
use App\Services\LocaleService;
use App\Support\TranslationStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    /**
     * Display a listing of blogs.
     */
    public function index(Request $request)
    {
        $localeService = app(LocaleService::class);
        $localeInfo = $localeService->resolve($request, $request->user());
        $requestedLocale = $localeInfo['locale'];
        $fallbacks = $localeInfo['fallbacks'];

        $query = Blog::with('user');
        
        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by search term
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%");
            });
        }
        
        // Sort options
        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');
        $query->orderBy($sort, $order);
        
        // Pagination
        $perPage = $request->get('per_page', 10);
        $blogs = $query->paginate($perPage);

        $blogIds = collect($blogs->items())->pluck('id')->values();
        $translations = $blogIds->isEmpty()
            ? collect()
            : BlogTranslation::whereIn('blog_id', $blogIds)
                ->whereIn('locale', $fallbacks)
                ->get()
                ->groupBy('blog_id');

        $data = collect($blogs->items())->map(function (Blog $blog) use ($requestedLocale, $fallbacks, $translations, $request) {
            $translationGroup = $translations->get($blog->id, collect());
            return $this->resolveBlogLocale($blog, $requestedLocale, $fallbacks, $translationGroup, $this->isAdmin($request));
        })->values();

        return response()->json([
            'requested_locale' => $requestedLocale,
            'data' => $data,
            'meta' => [
                'current_page' => $blogs->currentPage(),
                'last_page' => $blogs->lastPage(),
                'per_page' => $blogs->perPage(),
                'total' => $blogs->total(),
            ]
        ]);
    }

// Update the store method to use proper form data handling
public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'title' => 'required|string|max:255',
        'content' => 'required|string',
        'author' => 'nullable|string|max:255',
        'excerpt' => 'nullable|string|max:500',
        'status' => 'required|in:draft,published',
        'source_locale' => 'nullable|string|max:5',
        'meta_title' => 'nullable|string|max:255',
        'meta_description' => 'nullable|string|max:255',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'errors' => $validator->errors()
        ], 422);
    }

    $blogData = $request->only(['title', 'content', 'author', 'excerpt', 'status', 'meta_title', 'meta_description']);
    $blogData['slug'] = Str::slug($request->title);
    $blogData['user_id'] = auth()->id();
    $blogData['source_locale'] = strtolower((string) $request->input('source_locale', $request->input('locale', config('locales.default'))));
    
    // Handle file upload separately
    if ($request->hasFile('featured_image')) {
        $validator = Validator::make($request->all(), [
            'featured_image' => 'image|mimes:jpeg,png,jpg,gif|max:20480',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }
        
        $path = $request->file('featured_image')->store('blog_images', 'public');
        $blogData['featured_image'] = $path;
    }

    $blog = Blog::create($blogData);
    $blog->source_hash = app(ContentTranslationService::class)->blogSourceHash($blog);
    $blog->save();

    return response()->json([
        'message' => 'Blog created successfully',
        'data' => $blog->load('user')
    ], 201);
}

// Also update the update method similarly
public function update(Request $request, $id)
{
    $blog = Blog::find($id);
    
    if (!$blog) {
        return response()->json([
            'message' => 'Blog not found'
        ], 404);
    }

    $validator = Validator::make($request->all(), [
        'title' => 'sometimes|string|max:255',
        'content' => 'sometimes|string',
        'author' => 'nullable|string|max:255',
        'excerpt' => 'nullable|string|max:500',
        'status' => 'sometimes|in:draft,published',
        'source_locale' => 'nullable|string|max:5',
        'meta_title' => 'nullable|string|max:255',
        'meta_description' => 'nullable|string|max:255',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'errors' => $validator->errors()
        ], 422);
    }

    $blogData = $request->only(['title', 'content', 'author', 'excerpt', 'status', 'source_locale', 'meta_title', 'meta_description']);
    if (!empty($blogData['source_locale'])) {
        $blogData['source_locale'] = strtolower((string) $blogData['source_locale']);
    }
    
    if ($request->has('title')) {
        $blogData['slug'] = Str::slug($request->title);
    }
    
    // Handle file upload separately
    if ($request->hasFile('featured_image')) {
        $validator = Validator::make($request->all(), [
            'featured_image' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Delete old image if exists
        if ($blog->featured_image) {
            Storage::disk('public')->delete($blog->featured_image);
        }
        
        $path = $request->file('featured_image')->store('blog_images', 'public');
        $blogData['featured_image'] = $path;
    }

    $blog->update($blogData);
    $sourceHash = app(ContentTranslationService::class)->blogSourceHash($blog);
    if ($sourceHash !== $blog->source_hash) {
        $blog->source_hash = $sourceHash;
        $blog->save();
        BlogTranslation::where('blog_id', $blog->id)
            ->where(function ($query) use ($sourceHash) {
                $query->whereNull('translated_from_hash')
                    ->orWhere('translated_from_hash', '!=', $sourceHash);
            })
            ->update(['status' => TranslationStatus::OUTDATED]);
    }

    return response()->json([
        'message' => 'Blog updated successfully',
        'data' => $blog->load('user')
    ]);
}

/**
 * Increment blog views.
 */
public function incrementViews($id)
{
    $blog = Blog::find($id);
    
    if (!$blog) {
        return response()->json([
            'message' => 'Blog not found'
        ], 404);
    }
    
    $blog->increment('views');
    
    return response()->json([
        'message' => 'View count incremented',
        'views' => $blog->views
    ]);
}

    /**
     * Display the specified blog.
     */
    public function show(Request $request, $id)
    {
        $blog = Blog::with('user')->find($id);
        
        if (!$blog) {
            return response()->json([
                'message' => 'Blog not found'
            ], 404);
        }
        
        // Increment view count
        $blog->incrementViews();

        $localeService = app(LocaleService::class);
        $localeInfo = $localeService->resolve($request, $request->user());
        $requestedLocale = $localeInfo['locale'];
        $fallbacks = $localeInfo['fallbacks'];
        $translations = BlogTranslation::where('blog_id', $blog->id)
            ->whereIn('locale', $fallbacks)
            ->get();
        $resolved = $this->resolveBlogLocale($blog, $requestedLocale, $fallbacks, $translations, $this->isAdmin($request));
        
        return response()->json([
            'requested_locale' => $requestedLocale,
            'data' => $resolved
        ]);
    }


    /**
     * Remove the specified blog.
     */
    public function destroy($id)
    {
        $blog = Blog::find($id);
        
        if (!$blog) {
            return response()->json([
                'message' => 'Blog not found'
            ], 404);
        }
        
        // Delete featured image if exists
        if ($blog->featured_image) {
            Storage::disk('public')->delete($blog->featured_image);
        }
        
        $blog->delete();
        
        return response()->json([
            'message' => 'Blog deleted successfully'
        ]);
    }

    /**
     * Get blog by slug.
     */
    public function showBySlug(Request $request, $slug)
    {
        $blog = Blog::with('user')->where('slug', $slug)->first();
        
        if (!$blog) {
            return response()->json([
                'message' => 'Blog not found'
            ], 404);
        }
        
        // Increment view count
        $blog->incrementViews();

        $localeService = app(LocaleService::class);
        $localeInfo = $localeService->resolve($request, $request->user());
        $requestedLocale = $localeInfo['locale'];
        $fallbacks = $localeInfo['fallbacks'];
        $translations = BlogTranslation::where('blog_id', $blog->id)
            ->whereIn('locale', $fallbacks)
            ->get();
        $resolved = $this->resolveBlogLocale($blog, $requestedLocale, $fallbacks, $translations, $this->isAdmin($request));
        
        return response()->json([
            'requested_locale' => $requestedLocale,
            'data' => $resolved
        ]);
    }

    /**
     * Get featured/popular blogs.
     */
    public function featured(Request $request)
    {
        $blogs = Blog::published()
            ->orderBy('views', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $localeService = app(LocaleService::class);
        $localeInfo = $localeService->resolve($request, $request->user());
        $requestedLocale = $localeInfo['locale'];
        $fallbacks = $localeInfo['fallbacks'];

        $translations = $blogs->isEmpty()
            ? collect()
            : BlogTranslation::whereIn('blog_id', $blogs->pluck('id'))
                ->whereIn('locale', $fallbacks)
                ->get()
                ->groupBy('blog_id');

        $data = $blogs->map(function (Blog $blog) use ($requestedLocale, $fallbacks, $translations, $request) {
            return $this->resolveBlogLocale($blog, $requestedLocale, $fallbacks, $translations->get($blog->id, collect()), $this->isAdmin($request));
        })->values();

        return response()->json([
            'requested_locale' => $requestedLocale,
            'data' => $data,
        ]);
    }

    private function resolveBlogLocale(Blog $blog, string $requestedLocale, array $fallbacks, $translations, bool $includeStatus): array
    {
        $sourceLocale = $blog->source_locale ?: config('locales.default');
        $chain = array_values(array_unique(array_merge([$requestedLocale], $fallbacks, [$sourceLocale])));

        $translation = null;
        $usedLocale = $sourceLocale;
        foreach ($chain as $candidate) {
            if ($candidate === $sourceLocale) {
                $usedLocale = $sourceLocale;
                break;
            }
            $candidateTranslation = $translations instanceof \Illuminate\Support\Collection
                ? $translations->firstWhere('locale', $candidate)
                : null;
            if ($candidateTranslation) {
                $translation = $candidateTranslation;
                $usedLocale = $candidate;
                break;
            }
        }

        $data = [
            'id' => $blog->id,
            'slug' => $blog->slug,
            'title' => $translation?->title ?? $blog->title,
            'excerpt' => $translation?->excerpt ?? $blog->excerpt,
            'content' => $translation?->content ?? $blog->content,
            'meta_title' => $translation?->meta_title ?? $blog->meta_title,
            'meta_description' => $translation?->meta_description ?? $blog->meta_description,
            'author' => $blog->author,
            'featured_image' => $blog->featured_image,
            'status' => $blog->status,
            'views' => $blog->views,
            'user' => $blog->relationLoaded('user') ? $blog->user : null,
            'source_locale' => $blog->source_locale,
            'created_at' => $blog->created_at,
            'updated_at' => $blog->updated_at,
            'locale' => $usedLocale,
            'fallback_locale_used' => $usedLocale !== $requestedLocale ? $usedLocale : null,
        ];

        if ($includeStatus) {
            $data['translation_status'] = $translation?->status ?? TranslationStatus::REVIEWED;
        }

        return $data;
    }

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('super-admin'))) {
            return true;
        }

        return in_array($user->role, ['admin', 'super-admin', 'super_admin'], true);
    }
}
