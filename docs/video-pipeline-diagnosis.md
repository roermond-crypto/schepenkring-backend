# Video Pipeline — Diagnosis & Fix Plan

> **Problem:** Boats are created but no video is ever generated. The social dashboard at `/admin/social` shows records with no video link. Nothing appears on any social media platform.

---

## Table of Contents

1. [Full Pipeline Overview](#1-full-pipeline-overview)
2. [Root Cause Analysis — Every Failure Point](#2-root-cause-analysis--every-failure-point)
3. [How to Diagnose Your Specific Situation](#3-how-to-diagnose-your-specific-situation)
4. [Fix Checklist — In Order](#4-fix-checklist--in-order)
5. [Testing the Fixed Pipeline](#5-testing-the-fixed-pipeline)
6. [Social Publishing (Yext) Diagnosis](#6-social-publishing-yext-diagnosis)

---

## 1. Full Pipeline Overview

This is the complete chain from boat creation to social post. Every step must work for a video to appear.

```
Boat created / published
        ↓
VideoAutomationService::handleYachtCreated()
  → checks: VIDEO_AUTOMATION_ENABLED=true
  → checks: yacht has images (renderable image count > 0)
  → checks: yacht status is in publish_statuses list
  → creates Video record (status='queued')
  → dispatches RenderMarketingVideo job → queue: 'video-rendering'
        ↓
Queue worker processes 'video-rendering' queue
  → FFmpegService::isAvailable() — checks /usr/bin/ffmpeg exists
  → collectRenderableImagePaths() — finds LOCAL file paths (not URLs)
  → FFmpegService::renderVerticalSlideshow() — runs ffmpeg command
  → saves .mp4 to storage/app/public/videos/marketing/
  → Video::update(status='ready', video_url=..., thumbnail_url=...)
        ↓
VideoSchedulerService::scheduleNextAvailable()
  → creates VideoPost record (status='scheduled', scheduled_at=tomorrow 10:30)
        ↓
Scheduled command / queue worker publishes at scheduled_at time
  → PublishVideoPost job → queue: 'social-publishing'
  → YextSocialService::createPost()
  → calls Yext API with video_url + caption + publishers
  → VideoPost::update(status='published', yext_post_id=...)
        ↓
Video appears on Facebook / Instagram / etc.
```

---

## 2. Root Cause Analysis — Every Failure Point

There are **6 independent failure points**. Any one of them stops the entire pipeline.

---

### ❌ Failure Point 1 — `VIDEO_AUTOMATION_ENABLED` is false or not set

**Location:** `config/video_automation.php` → `VideoAutomationService::handleYachtCreated()`

**Code:**
```php
if (!config('video_automation.enabled') || !config('video_automation.auto_on_create')) {
    return null;  // ← silently returns, no video queued
}
```

**Check:**
```bash
php artisan tinker --execute="echo config('video_automation.enabled') ? 'ENABLED' : 'DISABLED';"
php artisan tinker --execute="echo config('video_automation.auto_on_create') ? 'ON' : 'OFF';"
```

**Fix:** Add to `.env`:
```dotenv
VIDEO_AUTOMATION_ENABLED=true
VIDEO_AUTOMATION_ON_CREATE=true
VIDEO_AUTOMATION_ON_PUBLISH=true
```

---

### ❌ Failure Point 2 — No renderable images (most likely cause)

**Location:** `VideoAutomationService::collectRenderableImagePaths()`

**The critical bug:** The method only accepts **local file paths**. It explicitly rejects HTTP/HTTPS URLs:

```php
private function resolveRenderablePath(?string $candidate): ?string
{
    // ...
    if (preg_match('/^https?:\/\//i', $candidate) === 1) {
        return null;  // ← ALL Cloudinary/CDN URLs are rejected here
    }
    // ...
}
```

**This means:** If your boat images are stored on Cloudinary, S3, or any external URL (which is the default for the image pipeline), **zero images will be found** and the video is silently skipped.

**Check:**
```bash
php artisan tinker
```
```php
$yacht = \App\Models\Yacht::with('images')->latest()->first();
echo "main_image: " . $yacht->main_image . "\n";
echo "image count: " . $yacht->images->count() . "\n";
$yacht->images->each(fn($img) => print($img->url . "\n"));

// Check what the automation service finds:
$svc = app(\App\Services\VideoAutomationService::class);
$count = $svc->renderableImageCount($yacht);
echo "Renderable image count: {$count}\n";
// If this is 0 → this is your problem
```

**Fix options (choose one):**

**Option A — Download images before rendering (recommended):**
Update `collectRenderableImagePaths()` to download remote images to a temp directory:

```php
private function resolveRenderablePath(?string $candidate): ?string
{
    if (!is_string($candidate) || trim($candidate) === '') {
        return null;
    }

    $candidate = trim($candidate);

    // ── NEW: Download remote URLs to temp file ──────────────────
    if (preg_match('/^https?:\/\//i', $candidate)) {
        return $this->downloadToTemp($candidate);
    }

    // Local file check (existing logic)
    if (file_exists($candidate)) {
        return $candidate;
    }

    $storagePath = Storage::disk('public')->path(ltrim($candidate, '/'));
    return file_exists($storagePath) ? $storagePath : null;
}

private function downloadToTemp(string $url): ?string
{
    try {
        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $tmpPath = sys_get_temp_dir() . '/yacht_img_' . md5($url) . '.' . $ext;

        // Use cached version if downloaded recently (within 1 hour)
        if (file_exists($tmpPath) && filemtime($tmpPath) > time() - 3600) {
            return $tmpPath;
        }

        $contents = @file_get_contents($url);
        if ($contents === false || strlen($contents) < 1000) {
            return null; // Skip broken/empty images
        }

        file_put_contents($tmpPath, $contents);
        return $tmpPath;
    } catch (\Throwable $e) {
        Log::warning('Failed to download image for video', ['url' => $url, 'error' => $e->getMessage()]);
        return null;
    }
}
```

**Option B — Store images locally during upload:**
Ensure the image pipeline saves a local copy in `storage/app/public/` in addition to uploading to Cloudinary.

---

### ❌ Failure Point 3 — FFmpeg is not installed on the server

**Location:** `RenderMarketingVideo::handle()` → `FFmpegService::isAvailable()`

**Code:**
```php
$ffmpeg = new FFmpegService();
if (!$ffmpeg->isAvailable()) {
    $this->failJob($video, 'FFmpeg is not installed or not accessible');
    return;
}
```

**Check:**
```bash
# On the server:
which ffmpeg
ffmpeg -version

# Or via artisan:
php artisan tinker --execute="
\$ffmpeg = new \App\Services\FFmpegService();
echo \$ffmpeg->isAvailable() ? 'FFmpeg OK' : 'FFmpeg MISSING';
"
```

**Fix:**
```bash
# Ubuntu/Debian:
sudo apt-get install -y ffmpeg

# Check the binary path matches FFMPEG_BINARY in .env:
which ffmpeg  # → /usr/bin/ffmpeg (default) or /usr/local/bin/ffmpeg
```

Add to `.env`:
```dotenv
FFMPEG_BINARY=/usr/bin/ffmpeg
FFMPEG_FONT_PATH=/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf
```

**Note:** The font path is also checked in `buildOverlayFilter()`. If the font file doesn't exist, overlay text is silently skipped (not a blocker, but text won't appear on video).

---

### ❌ Failure Point 4 — The `video-rendering` queue is not being processed

**Location:** `RenderMarketingVideo` is dispatched to the `video-rendering` queue specifically.

**The problem:** If your queue worker is started with just `php artisan queue:work` (no queue specified), it processes the `default` queue only. The `video-rendering` queue jobs sit forever.

**Check:**
```bash
# Check pending jobs per queue
php artisan tinker --execute="
\$jobs = \DB::table('jobs')->select('queue', \DB::raw('count(*) as count'))->groupBy('queue')->get();
foreach (\$jobs as \$j) echo \$j->queue . ': ' . \$j->count . ' jobs\n';
"

# Check failed jobs
php artisan queue:failed
```

**Fix:** Start workers for ALL queues:
```bash
# Option A — process all queues
php artisan queue:work --queue=video-rendering,social-publishing,whatsapp,default --tries=3 --timeout=300

# Option B — separate workers per queue (recommended for production)
php artisan queue:work --queue=video-rendering --tries=3 --timeout=300 &
php artisan queue:work --queue=social-publishing --tries=3 --timeout=60 &
php artisan queue:work --queue=whatsapp --tries=3 --timeout=60 &
php artisan queue:work --queue=default --tries=3 --timeout=60 &
```

**For Supervisor (production):**
```ini
[program:worker-video]
command=php /var/www/artisan queue:work --queue=video-rendering --tries=3 --timeout=300 --sleep=3
autostart=true
autorestart=true
numprocs=1

[program:worker-social]
command=php /var/www/artisan queue:work --queue=social-publishing,whatsapp,default --tries=3 --timeout=60 --sleep=3
autostart=true
autorestart=true
numprocs=2
```

---

### ❌ Failure Point 5 — Yacht status not in `publish_statuses` list

**Location:** `VideoAutomationService::isPublishable()` → `isPublishedStatus()`

**Code:**
```php
$publishStatuses = array_map('strtolower', config('video_automation.publish_statuses', []));
// Default: ['active', 'for sale', 'for bid', 'published']

return in_array($status, $publishStatuses, true);
```

**Check:**
```bash
php artisan tinker --execute="
\$yacht = \App\Models\Yacht::latest()->first();
echo 'Yacht status: ' . \$yacht->status . PHP_EOL;
\$svc = app(\App\Services\VideoAutomationService::class);
echo 'Is publishable: ' . (\$svc->isPublishedStatus(\$yacht->status) ? 'YES' : 'NO') . PHP_EOL;
echo 'Publish statuses: ' . implode(', ', config('video_automation.publish_statuses')) . PHP_EOL;
"
```

**Fix:** Either update the yacht status to match, or add your status to `.env`:
```dotenv
VIDEO_AUTOMATION_PUBLISH_STATUSES=active,for sale,for bid,published,te koop,beschikbaar
```

---

### ❌ Failure Point 6 — `VideoPost` records exist but `video_url` is null (dashboard shows records, no link)

**This is exactly what you see:** Records in the social dashboard but no video link.

**Cause:** The `Video` record exists with `status='queued'` or `status='failed'`, meaning rendering never completed. The `VideoPost` was never created (it's only created after rendering succeeds), OR the `Video.video_url` is null because the file was never saved to public storage.

**Check:**
```bash
php artisan tinker --execute="
\App\Models\Video::latest()->limit(5)->get(['id','yacht_id','status','error_message','video_url','video_path','generated_at'])
    ->each(fn(\$v) => print_r(\$v->toArray()));
"
```

**Expected statuses and what they mean:**

| `status` | Meaning | Fix |
|---|---|---|
| `queued` | Job dispatched but never processed | Start the `video-rendering` queue worker |
| `processing` | Job started but crashed (worker died) | Restart worker, check failed jobs |
| `failed` | Job ran but FFmpeg or images failed | Check `error_message` column |
| `ready` | Video rendered OK | Check `video_url` — should be set |

**Check `video_url` accessibility:**
```bash
php artisan tinker --execute="
\$video = \App\Models\Video::where('status','ready')->latest()->first();
if (\$video) {
    echo 'video_url: ' . \$video->video_url . PHP_EOL;
    echo 'video_path: ' . \$video->video_path . PHP_EOL;
    \$path = storage_path('app/public/' . \$video->video_path);
    echo 'File exists: ' . (file_exists(\$path) ? 'YES' : 'NO') . PHP_EOL;
}
"
```

**If `video_url` is a local storage URL** (e.g. `http://localhost/storage/videos/...`), it will not be accessible by Yext for social publishing. The URL must be a **publicly reachable HTTPS URL**.

Fix: Ensure `APP_URL` in `.env` is the real public domain:
```dotenv
APP_URL=https://www.schepen-kring.nl
```

---

## 3. How to Diagnose Your Specific Situation

Run these commands in order. Stop at the first failure.

### Step 1 — Check config
```bash
php artisan tinker --execute="
echo 'enabled: '         . (config('video_automation.enabled') ? 'YES' : 'NO') . PHP_EOL;
echo 'auto_on_create: '  . (config('video_automation.auto_on_create') ? 'YES' : 'NO') . PHP_EOL;
echo 'publish_statuses: '. implode(', ', config('video_automation.publish_statuses')) . PHP_EOL;
echo 'APP_URL: '         . config('app.url') . PHP_EOL;
echo 'FFMPEG_BINARY: '   . env('FFMPEG_BINARY', '/usr/bin/ffmpeg') . PHP_EOL;
"
```

### Step 2 — Check FFmpeg
```bash
php artisan tinker --execute="
\$ffmpeg = new \App\Services\FFmpegService();
echo \$ffmpeg->isAvailable() ? 'FFmpeg: OK' : 'FFmpeg: MISSING - install it!';
echo PHP_EOL;
"
```

### Step 3 — Check images on a real boat
```bash
php artisan tinker --execute="
\$yacht = \App\Models\Yacht::with('images')->latest()->first();
echo 'Yacht: ' . \$yacht->boat_name . ' (status: ' . \$yacht->status . ')' . PHP_EOL;
echo 'main_image: ' . (\$yacht->main_image ?: '(none)') . PHP_EOL;
echo 'images count: ' . \$yacht->images->count() . PHP_EOL;
\$svc = app(\App\Services\VideoAutomationService::class);
echo 'renderable images: ' . \$svc->renderableImageCount(\$yacht) . PHP_EOL;
// If 0 → images are remote URLs, fix resolveRenderablePath()
"
```

### Step 4 — Check queue
```bash
php artisan tinker --execute="
\$queues = \DB::table('jobs')->select('queue', \DB::raw('count(*) as cnt'))->groupBy('queue')->get();
foreach (\$queues as \$q) echo \$q->queue . ': ' . \$q->cnt . ' pending' . PHP_EOL;
echo 'Failed jobs: ' . \DB::table('failed_jobs')->count() . PHP_EOL;
"
```

### Step 5 — Check existing Video records
```bash
php artisan tinker --execute="
\App\Models\Video::latest()->limit(10)
    ->get(['id','yacht_id','status','error_message','video_url','generated_at'])
    ->each(function(\$v) {
        echo \"Video #{$v->id} yacht={$v->yacht_id} status={$v->status}\";
        if (\$v->error_message) echo \" ERROR: {\$v->error_message}\";
        if (\$v->video_url) echo \" URL: {\$v->video_url}\";
        echo PHP_EOL;
    });
"
```

### Step 6 — Check VideoPost records (what you see in dashboard)
```bash
php artisan tinker --execute="
\App\Models\VideoPost::with('video')->latest()->limit(10)->get()
    ->each(function(\$p) {
        echo \"Post #{$p->id} status={$p->status} scheduled={$p->scheduled_at}\";
        echo \" video_status=\" . (\$p->video?->status ?? 'NO VIDEO');
        echo \" video_url=\" . (\$p->video?->video_url ? 'SET' : 'NULL');
        if (\$p->error_message) echo \" ERROR: {\$p->error_message}\";
        echo PHP_EOL;
    });
"
```

---

## 4. Fix Checklist — In Order

Work through these in order. Each one must be ✅ before moving to the next.

### Fix 1 — Environment variables
```dotenv
# Add/verify in .env:
VIDEO_AUTOMATION_ENABLED=true
VIDEO_AUTOMATION_ON_CREATE=true
VIDEO_AUTOMATION_ON_PUBLISH=true
VIDEO_AUTOMATION_PUBLISH_STATUSES=active,for sale,for bid,published
VIDEO_AUTOMATION_MIN_IMAGES=1          # Lower this during testing (default 8 is too strict)
VIDEO_AUTOMATION_AUTO_SCHEDULE=true
FFMPEG_BINARY=/usr/bin/ffmpeg
FFMPEG_FONT_PATH=/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf
APP_URL=https://www.schepen-kring.nl   # Must be public HTTPS URL
```

After editing `.env`:
```bash
php artisan config:clear
php artisan cache:clear
```

### Fix 2 — Install FFmpeg (if missing)
```bash
sudo apt-get update && sudo apt-get install -y ffmpeg
ffmpeg -version  # verify
```

### Fix 3 — Fix image URL resolution (critical if using Cloudinary)

Edit `app/Services/VideoAutomationService.php` — replace `resolveRenderablePath()`:

```php
private function resolveRenderablePath(?string $candidate): ?string
{
    if (! is_string($candidate)) {
        return null;
    }

    $candidate = trim($candidate);
    if ($candidate === '') {
        return null;
    }

    // Download remote URLs (Cloudinary, S3, CDN) to a local temp file
    if (preg_match('/^https?:\/\//i', $candidate) === 1) {
        return $this->downloadToTemp($candidate);
    }

    if (file_exists($candidate)) {
        return $candidate;
    }

    $storagePath = Storage::disk('public')->path(ltrim($candidate, '/'));

    return file_exists($storagePath) ? $storagePath : null;
}

private function downloadToTemp(string $url): ?string
{
    try {
        $ext = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';
        $tmpPath = sys_get_temp_dir() . '/yacht_img_' . md5($url) . '.' . $ext;

        // Reuse cached download if fresh (within 1 hour)
        if (file_exists($tmpPath) && filemtime($tmpPath) > time() - 3600) {
            return $tmpPath;
        }

        $ctx = stream_context_create(['http' => ['timeout' => 15]]);
        $contents = @file_get_contents($url, false, $ctx);

        if ($contents === false || strlen($contents) < 500) {
            return null;
        }

        file_put_contents($tmpPath, $contents);

        return $tmpPath;
    } catch (\Throwable $e) {
        Log::warning('Failed to download image for video rendering', [
            'url'   => $url,
            'error' => $e->getMessage(),
        ]);

        return null;
    }
}
```

### Fix 4 — Start queue workers for ALL queues
```bash
# Kill any existing workers first
php artisan queue:restart

# Start workers (keep running in background or use Supervisor)
php artisan queue:work --queue=video-rendering,social-publishing,whatsapp,default \
    --tries=3 --timeout=300 --sleep=3
```

### Fix 5 — Retry failed/stuck Video records
```bash
# Reset stuck 'queued' or 'processing' videos and re-dispatch
php artisan tinker --execute="
\App\Models\Video::whereIn('status', ['queued', 'processing', 'failed'])
    ->each(function(\$video) {
        \$video->update(['status' => 'queued', 'error_message' => null]);
        \App\Jobs\RenderMarketingVideo::dispatch(\$video->id)->onQueue('video-rendering');
        echo 'Re-queued video #' . \$video->id . PHP_EOL;
    });
"
```

### Fix 6 — Manually trigger video for a specific boat (for testing)
```bash
php artisan tinker --execute="
\$yacht = \App\Models\Yacht::find(YOUR_YACHT_ID);
\$svc = app(\App\Services\VideoAutomationService::class);
\$result = \$svc->queueManualVideo(\$yacht, null, true); // force=true
echo 'Video #' . \$result['video']->id . ' status: ' . \$result['video']->status . PHP_EOL;
"

# Then process it synchronously to see errors immediately:
php artisan queue:work --queue=video-rendering --once --tries=1
```

---

## 5. Testing the Fixed Pipeline

### Test A — Synchronous end-to-end test (fastest)

```bash
# 1. Pick a boat with images
php artisan tinker --execute="
\$yacht = \App\Models\Yacht::with('images')->whereHas('images')->latest()->first();
echo 'Testing with yacht #' . \$yacht->id . ': ' . \$yacht->boat_name . PHP_EOL;
echo 'Images: ' . \$yacht->images->count() . PHP_EOL;
\$svc = app(\App\Services\VideoAutomationService::class);
echo 'Renderable: ' . \$svc->renderableImageCount(\$yacht) . PHP_EOL;
"

# 2. Queue a video (force=true to bypass reuse check)
php artisan tinker --execute="
\$yacht = \App\Models\Yacht::with('images')->whereHas('images')->latest()->first();
\$svc = app(\App\Services\VideoAutomationService::class);
\$result = \$svc->queueManualVideo(\$yacht, null, true);
echo 'Video #' . \$result['video']->id . ' queued' . PHP_EOL;
"

# 3. Process the video-rendering queue (watch for errors)
php artisan queue:work --queue=video-rendering --once --tries=1

# 4. Check result
php artisan tinker --execute="
\$video = \App\Models\Video::latest()->first();
echo 'Status: ' . \$video->status . PHP_EOL;
echo 'video_url: ' . (\$video->video_url ?: 'NULL') . PHP_EOL;
echo 'error: ' . (\$video->error_message ?: 'none') . PHP_EOL;
"
```

### Test B — Check the video file is accessible

```bash
# Get the video URL from DB
php artisan tinker --execute="
\$video = \App\Models\Video::where('status','ready')->latest()->first();
echo \$video?->video_url ?? 'No ready video found';
"

# Test the URL is publicly reachable
curl -s -o /dev/null -w "%{http_code}" "https://www.schepen-kring.nl/storage/videos/marketing/yacht-X-TIMESTAMP.mp4"
# Expected: 200
```

### Test C — Check social post scheduling

```bash
php artisan tinker --execute="
\$video = \App\Models\Video::where('status','ready')->latest()->first();
if (\$video) {
    echo 'Posts: ' . \$video->posts->count() . PHP_EOL;
    \$video->posts->each(fn(\$p) => print('Post #' . \$p->id . ' status=' . \$p->status . ' scheduled=' . \$p->scheduled_at . PHP_EOL));
}
"
```

### Test D — Manually trigger social publish

```bash
php artisan tinker --execute="
\$post = \App\Models\VideoPost::where('status','scheduled')->latest()->first();
if (\$post) {
    \App\Jobs\PublishVideoPost::dispatch(\$post->id)->onQueue('social-publishing');
    echo 'Dispatched post #' . \$post->id . PHP_EOL;
}
"

php artisan queue:work --queue=social-publishing --once --tries=1

php artisan tinker --execute="
\$post = \App\Models\VideoPost::latest()->first();
echo 'Status: ' . \$post->status . PHP_EOL;
echo 'yext_post_id: ' . (\$post->yext_post_id ?: 'null') . PHP_EOL;
echo 'error: ' . (\$post->error_message ?: 'none') . PHP_EOL;
"
```

---

## 6. Social Publishing (Yext) Diagnosis

The social dashboard shows records but no video link because:

1. **`video_url` is null** — rendering failed (see §2 Failure Points 1–4)
2. **`video_url` is a localhost URL** — Yext cannot fetch it
3. **Yext API credentials missing** — post fails silently

### Check Yext config
```bash
php artisan tinker --execute="
echo 'YEXT_API_KEY: '    . (config('services.yext.api_key') ? 'SET (' . strlen(config('services.yext.api_key')) . ' chars)' : 'MISSING') . PHP_EOL;
echo 'YEXT_ACCOUNT_ID: ' . (config('services.yext.account_id') ?: 'MISSING') . PHP_EOL;
echo 'YEXT_ENTITY_ID: '  . (config('services.yext.entity_id') ?: 'MISSING') . PHP_EOL;
echo 'publishers: '      . implode(', ', config('video_automation.default_publishers')) . PHP_EOL;
"
```

### Check Yext API logs
```bash
php artisan tinker --execute="
\App\Models\SocialLog::latest()->limit(5)->get()
    ->each(function(\$log) {
        echo 'Event: ' . \$log->event . ' Status: ' . \$log->status_code . PHP_EOL;
        echo 'Response: ' . json_encode(\$log->response_payload) . PHP_EOL;
        echo '---' . PHP_EOL;
    });
"
```

### Required `.env` for Yext social publishing
```dotenv
YEXT_API_KEY=your_yext_api_key
YEXT_ACCOUNT_ID=your_account_id
YEXT_ENTITY_ID=your_entity_id
YEXT_API_BASE=https://api.yextapis.com
YEXT_API_VERSION=20240101
YEXT_USE_PUBLISHER_TARGETS=true
YEXT_VIDEO_PUBLISHERS=facebook,instagram
VIDEO_AUTOMATION_PUBLISHERS=facebook,instagram,google
```

---

## Summary — Most Likely Root Causes (in order of probability)

| # | Root Cause | How to confirm | Fix |
|---|---|---|---|
| 1 | **Images are Cloudinary/CDN URLs** — `resolveRenderablePath()` rejects all HTTP URLs | `renderableImageCount()` returns 0 | Fix `resolveRenderablePath()` to download remote images |
| 2 | **`video-rendering` queue not processed** — worker only runs `default` queue | `jobs` table has rows with `queue='video-rendering'` | Start worker with `--queue=video-rendering,...` |
| 3 | **FFmpeg not installed** | `ffmpeg -version` fails | `sudo apt-get install ffmpeg` |
| 4 | **`VIDEO_AUTOMATION_ENABLED` not set** | `config('video_automation.enabled')` = false | Add to `.env` |
| 5 | **Yacht status not in publish list** | `isPublishedStatus()` returns false | Add status to `VIDEO_AUTOMATION_PUBLISH_STATUSES` |
| 6 | **`APP_URL` is localhost** — video URL not reachable by Yext | `video_url` starts with `http://localhost` | Set `APP_URL=https://www.schepen-kring.nl` |
