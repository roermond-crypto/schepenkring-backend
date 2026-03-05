<?php

namespace App\Console\Commands;

use App\Models\Harbor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use ZipArchive;

class ExportHarborsForMagazineCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'harbors:export-magazine {--published-only : Export only published harbors} {--limit= : Limit the number of harbors}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export harbors to CSV and generate QR codes for magazine printing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Harbor Magazine Export...');

        $query = Harbor::query();
        if ($this->option('published-only')) {
            $query->where('is_published', true);
        }

        if ($limit = $this->option('limit')) {
            $query->limit($limit);
        }

        $harbors = $query->get();
        if ($harbors->isEmpty()) {
            $this->warn('No harbors found to export.');
            return self::SUCCESS;
        }

        $this->info("Found {$harbors->count()} harbors. Generating files...");

        // Setup directories
        $exportDir = 'magazine_export_' . now()->format('Y_m_d_His');
        $fullPath = storage_path('app/' . $exportDir);
        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        $csvFile = fopen($fullPath . '/harbors_index.csv', 'w');
        // CSV Headers
        fputcsv($csvFile, ['Hiswa ID', 'Name', 'Address', 'City', 'Postal Code', 'Claim URL', 'QR File Name']);

        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', config('app.url'))), '/');

        $bar = $this->output->createProgressBar(count($harbors));
        $bar->start();

        // Ensure public storage dir exists for QRs
        $publicQrDir = storage_path('app/public/harbors/qr');
        if (!file_exists($publicQrDir)) {
            mkdir($publicQrDir, 0755, true);
        }

        foreach ($harbors as $harbor) {
            $claimUrl = $frontendUrl . '/harbors/' . $harbor->public_slug . '/claim';
            
            // Clean filename
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($harbor->name));
            $fileName = "qr_{$harbor->id}_{$safeName}.svg";
            
            // Generate QR Code as SVG in both ZIP and Public Storage
            QrCode::format('svg')
                ->size(300)
                ->margin(1)
                ->generate($claimUrl, $fullPath . '/' . $fileName);
                
            QrCode::format('svg')
                ->size(300)
                ->margin(1)
                ->generate($claimUrl, $publicQrDir . '/' . $fileName);
                
            // Update Harbor Model with public URL
            $harbor->update([
                'qr_code_url' => asset('storage/harbors/qr/' . $fileName)
            ]);

            // Write to CSV
            fputcsv($csvFile, [
                $harbor->hiswa_company_id ?? '',
                $harbor->name,
                $harbor->street_address ?? '',
                $harbor->city ?? '',
                $harbor->postal_code ?? '',
                $claimUrl,
                $fileName
            ]);

            $bar->advance();
        }

        fclose($csvFile);
        $bar->finish();
        $this->newLine();

        $this->info('Creating ZIP archive...');
        $zipFile = storage_path('app/magazine_export_latest.zip');
        
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $files = glob($fullPath . '/*');
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
            $this->info("ZIP archive created successfully at: {$zipFile}");
        } else {
            $this->error("Failed to create ZIP archive!");
            return self::FAILURE;
        }

        // Cleanup temporary directory
        $this->deleteDirectory($fullPath);

        $this->info('Export completed successfully!');
        return self::SUCCESS;
    }

    private function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }
}
