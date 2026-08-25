<?php

namespace Tests\Unit;

use App\Mail\DismantlingCompletedMail;
use App\Mail\InstallationCompletedMail;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CompanyClient;
use App\Models\OrderPaymentReceipt;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use App\Models\WorkerOrder;
use App\Support\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Storage-layer tests using Storage::fake('s3').
 * HTTP/controller coverage is intentionally avoided here because the project's
 * SQLite test migrations diverge from the production MySQL users schema.
 */
class MediaStorageS3Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(MediaStorage::DISK);
    }

    public function test_stores_product_category_brand_package_iban_payment_and_worker_paths_on_s3(): void
    {
        $cases = [
            'products' => UploadedFile::fake()->image('product.jpg'),
            'categories' => UploadedFile::fake()->image('category.jpg'),
            'brands' => UploadedFile::fake()->image('logo.png'),
            'packages' => UploadedFile::fake()->image('package.jpg'),
            'customer-ibans' => UploadedFile::fake()->image('iban.jpg'),
            'payment-proofs' => UploadedFile::fake()->create('proof.pdf', 120, 'application/pdf'),
            'worker-installations' => UploadedFile::fake()->image('install.jpg'),
            'worker-pickups' => UploadedFile::fake()->image('pickup.jpg'),
        ];

        foreach ($cases as $directory => $file) {
            $path = MediaStorage::store($file, $directory);
            $this->assertStringStartsWith($directory.'/', $path);
            Storage::disk(MediaStorage::DISK)->assertExists($path);
            $this->assertNotNull(MediaStorage::url($path));
        }
    }

    public function test_replacement_deletes_old_s3_object_after_new_upload(): void
    {
        $old = MediaStorage::store(UploadedFile::fake()->image('old.jpg'), 'products');
        $new = MediaStorage::store(UploadedFile::fake()->image('new.jpg'), 'products');

        Storage::disk(MediaStorage::DISK)->assertExists($old);
        Storage::disk(MediaStorage::DISK)->assertExists($new);

        MediaStorage::delete($old);

        Storage::disk(MediaStorage::DISK)->assertMissing($old);
        Storage::disk(MediaStorage::DISK)->assertExists($new);
    }

    public function test_model_url_accessors_delegate_to_media_storage(): void
    {
        $productPath = MediaStorage::store(UploadedFile::fake()->image('p.jpg'), 'products');
        $categoryPath = MediaStorage::store(UploadedFile::fake()->image('c.jpg'), 'categories');
        $logoPath = MediaStorage::store(UploadedFile::fake()->image('b.png'), 'brands');
        $packagePath = MediaStorage::store(UploadedFile::fake()->image('pkg.jpg'), 'packages');
        $ibanPath = MediaStorage::store(UploadedFile::fake()->image('iban.jpg'), 'customer-ibans');
        $installPath = MediaStorage::store(UploadedFile::fake()->image('i.jpg'), 'worker-installations');
        $pickupPath = MediaStorage::store(UploadedFile::fake()->image('k.jpg'), 'worker-pickups');
        $proofPath = MediaStorage::store(UploadedFile::fake()->image('proof.jpg'), 'payment-proofs');

        $this->assertSame(MediaStorage::url($productPath), (new Product(['image' => $productPath]))->image_url);
        $this->assertSame(MediaStorage::url($categoryPath), (new Category(['image' => $categoryPath]))->image_url);
        $this->assertSame(MediaStorage::url($logoPath), (new Brand(['logo' => $logoPath]))->logo_url);
        $this->assertSame(MediaStorage::url($packagePath), (new Package(['image' => $packagePath]))->image_url);
        $this->assertSame(MediaStorage::url($ibanPath), (new CompanyClient(['iban_image' => $ibanPath]))->iban_image_url);

        $user = new User;
        $user->forceFill(['iban_image' => $ibanPath]);
        $this->assertSame(MediaStorage::url($ibanPath), $user->iban_image_url);

        $worker = new WorkerOrder([
            'installation_photo' => $installPath,
            'pickup_photo' => $pickupPath,
            'product_image' => $productPath,
        ]);
        $this->assertSame(MediaStorage::url($installPath), $worker->installation_photo_url);
        $this->assertSame(MediaStorage::url($pickupPath), $worker->pickup_photo_url);
        $this->assertSame(MediaStorage::url($productPath), $worker->product_image_url);

        $receipt = new OrderPaymentReceipt;
        $receipt->forceFill(['proof_image' => [$proofPath]]);
        $this->assertSame([MediaStorage::url($proofPath)], $receipt->proof_image_urls);
    }

    public function test_temporary_local_path_for_pdf_embedding_is_cleaned_up(): void
    {
        $path = MediaStorage::store(UploadedFile::fake()->image('logo.png'), 'brands');
        $local = MediaStorage::temporaryLocalPath($path);

        $this->assertNotNull($local);
        $this->assertFileExists($local);

        MediaStorage::cleanupTempFiles();
        $this->assertFileDoesNotExist($local);
    }

    public function test_mailables_attach_s3_backed_photos(): void
    {
        $installPath = MediaStorage::store(UploadedFile::fake()->image('photo.jpg'), 'worker-installations');
        $pickupPath = MediaStorage::store(UploadedFile::fake()->image('pickup.jpg'), 'worker-pickups');

        $installationMail = new InstallationCompletedMail(
            orderNumber: 'ORD-1',
            customerName: 'عميل',
            workerName: 'عامل',
            workOrderUrl: 'https://example.com/wo',
            photos: [[
                'product_name' => 'منتج',
                'photo_path' => $installPath,
                'photo_url' => MediaStorage::url($installPath),
            ]],
        );

        $this->assertCount(1, $installationMail->attachments());

        $dismantlingMail = new DismantlingCompletedMail(
            orderNumber: 'ORD-1',
            customerName: 'عميل',
            workerName: 'عامل',
            returnsUrl: 'https://example.com/returns',
            photos: [[
                'product_name' => 'منتج',
                'photo_path' => $pickupPath,
                'photo_url' => MediaStorage::url($pickupPath),
            ]],
        );

        $this->assertCount(1, $dismantlingMail->attachments());
    }

    public function test_delete_is_safe_for_blank_and_missing_paths(): void
    {
        MediaStorage::delete(null);
        MediaStorage::delete('');
        MediaStorage::delete('products/does-not-exist.jpg');

        $this->assertTrue(true);
    }
}
