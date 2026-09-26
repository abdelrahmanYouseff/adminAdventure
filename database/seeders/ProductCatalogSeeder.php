<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\MediaStorage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalogPath = database_path('seeders/catalog/products.json');
        $imagesDir = database_path('seeders/catalog/images');
        $catalog = json_decode((string) file_get_contents($catalogPath), true);

        if (! is_array($catalog['categories'] ?? null) || ! is_array($catalog['products'] ?? null)) {
            throw new RuntimeException('Product catalog file is invalid.');
        }

        $brandId = Brand::storefront()->id;
        $disk = Storage::disk(MediaStorage::DISK);
        $imageKeys = [];
        $existing = $disk->files('products/catalog');
        if ($existing !== []) {
            $disk->delete($existing);
        }

        foreach ($catalog['products'] as $product) {
            $filename = (string) ($product['image'] ?? '');
            $localPath = $imagesDir.DIRECTORY_SEPARATOR.$filename;
            if ($filename === '' || ! is_file($localPath)) {
                throw new RuntimeException("Missing catalog image: {$filename}");
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                default => 'image/jpeg',
            };
            $key = 'products/catalog/'.$filename;
            $stored = $disk->put($key, (string) file_get_contents($localPath), [
                'visibility' => 'public',
                'mimetype' => $mime,
                'ContentType' => $mime,
            ]);
            if ($stored !== true) {
                throw new RuntimeException("Could not store catalog image: {$filename}");
            }

            $imageKeys[$filename] = $key;
        }

        DB::transaction(function () use ($catalog, $brandId, $imageKeys) {
            Product::query()->where('brand_id', $brandId)->delete();
            Category::query()->where('brand_id', $brandId)->delete();

            $categories = [];
            foreach ($catalog['categories'] as $name) {
                $category = Category::query()->create([
                    'category_name' => $name,
                    'brand_id' => $brandId,
                ]);
                $categories[$name] = $category->id;
            }

            foreach ($catalog['products'] as $product) {
                $categoryName = (string) $product['category'];
                if (! isset($categories[$categoryName])) {
                    throw new RuntimeException("Unknown category: {$categoryName}");
                }

                Product::query()->create([
                    'product_name' => $product['name'],
                    'description' => $product['description'] ?? null,
                    'price' => $product['price'],
                    'insurance_amount' => 0,
                    'status' => 'active',
                    'show_on_storefront' => true,
                    'image' => $imageKeys[$product['image']],
                    'category_id' => $categories[$categoryName],
                    'brand_id' => $brandId,
                ]);
            }
        });
    }
}
