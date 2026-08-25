<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CompanyClient;
use App\Models\OrderPaymentReceipt;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use App\Models\WorkerOrder;
use App\Support\MediaStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateLocalMediaToS3 extends Command
{
    protected $signature = 'storage:migrate-to-s3
                            {--dry-run : Show what would be migrated without uploading}
                            {--orphans : Also copy files under known upload dirs that are not referenced in the database}';

    protected $description = 'Copy existing local public-disk media files to S3, preserving object keys. Does not delete local files.';

    /** @var list<string> */
    private const KNOWN_DIRECTORIES = [
        'products',
        'categories',
        'brands',
        'packages',
        'customer-ibans',
        'payment-proofs',
        'worker-installations',
        'worker-pickups',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $includeOrphans = (bool) $this->option('orphans');

        $local = Storage::disk('public');
        $remote = Storage::disk(MediaStorage::DISK);

        $referenced = $this->collectReferencedPaths();
        $candidates = $includeOrphans
            ? $this->collectLocalFiles($local)
            : $referenced;

        $migrated = 0;
        $skipped = 0;
        $missingLocal = 0;
        $failed = 0;
        $orphanCount = 0;

        $this->info(($dryRun ? '[DRY-RUN] ' : '').'Migrating '.count($candidates).' path(s) to S3…');

        foreach (array_keys($candidates) as $path) {
            $key = ltrim($path, '/');
            $isReferenced = array_key_exists($key, $referenced);

            if (! $isReferenced) {
                $orphanCount++;
            }

            if (! $local->exists($key)) {
                $this->warn("MISSING LOCAL: {$key}".($isReferenced ? ' (DB-referenced)' : ' (orphan)'));
                $missingLocal++;

                continue;
            }

            if ($remote->exists($key)) {
                $this->line("SKIP (already on S3): {$key}");
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $label = $isReferenced ? 'referenced' : 'orphan';
                $this->line("WOULD MIGRATE [{$label}]: {$key}");
                $migrated++;

                continue;
            }

            try {
                $remote->put($key, $local->get($key), 'public');
                $this->info("MIGRATED: {$key}");
                $migrated++;
            } catch (Throwable $e) {
                $this->error("FAILED: {$key} — ".$e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Migrated / would migrate', $migrated],
                ['Skipped (already on S3)', $skipped],
                ['Missing on local disk', $missingLocal],
                ['Failed', $failed],
                ['Orphans in candidate set', $orphanCount],
                ['Dry run', $dryRun ? 'yes' : 'no'],
            ],
        );

        $this->comment('Local source files were NOT deleted.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string, true>
     */
    private function collectReferencedPaths(): array
    {
        $paths = [];

        foreach (Product::query()->whereNotNull('image')->pluck('image') as $path) {
            $this->addPath($paths, $path);
        }
        foreach (Category::query()->whereNotNull('image')->pluck('image') as $path) {
            $this->addPath($paths, $path);
        }
        foreach (Brand::query()->whereNotNull('logo')->pluck('logo') as $path) {
            $this->addPath($paths, $path);
        }
        foreach (Package::query()->whereNotNull('image')->pluck('image') as $path) {
            $this->addPath($paths, $path);
        }
        foreach (User::query()->whereNotNull('iban_image')->pluck('iban_image') as $path) {
            $this->addPath($paths, $path);
        }
        foreach (CompanyClient::query()->whereNotNull('iban_image')->pluck('iban_image') as $path) {
            $this->addPath($paths, $path);
        }
        foreach (WorkerOrder::query()->whereNotNull('installation_photo')->pluck('installation_photo') as $path) {
            $this->addPath($paths, $path);
        }
        foreach (WorkerOrder::query()->whereNotNull('pickup_photo')->pluck('pickup_photo') as $path) {
            $this->addPath($paths, $path);
        }
        foreach (WorkerOrder::query()->whereNotNull('product_image')->pluck('product_image') as $path) {
            $this->addPath($paths, $path);
        }

        foreach (OrderPaymentReceipt::query()->whereNotNull('proof_image')->get(['proof_image']) as $receipt) {
            $raw = $receipt->proof_image;
            if (is_array($raw)) {
                foreach ($raw as $path) {
                    $this->addPath($paths, $path);
                }
            } elseif (is_string($raw)) {
                $this->addPath($paths, $raw);
            }
        }

        ksort($paths);

        return $paths;
    }

    /**
     * @return array<string, true>
     */
    private function collectLocalFiles($localDisk): array
    {
        $paths = [];

        foreach (self::KNOWN_DIRECTORIES as $directory) {
            if (! $localDisk->exists($directory)) {
                continue;
            }

            foreach ($localDisk->allFiles($directory) as $file) {
                $this->addPath($paths, $file);
            }
        }

        ksort($paths);

        return $paths;
    }

    /**
     * @param  array<string, true>  $paths
     */
    private function addPath(array &$paths, mixed $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        $paths[ltrim($path, '/')] = true;
    }
}
