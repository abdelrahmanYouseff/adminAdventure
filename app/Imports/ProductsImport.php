<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class ProductsImport implements ToCollection
{
    protected int $imported = 0;

    protected array $errors = [];

    /**
     * Excel columns: A = اسم المنتج, B = الفئة, C = السعر
     * First row can be header (اسم المنتج، الفئة، السعر) — will be skipped if it looks like headers.
     */
    public function collection(Collection $rows): void
    {
        $rows = $rows->filter(fn ($row) => $row !== null && (is_array($row) || $row instanceof \Illuminate\Support\Collection));
        if ($rows->isEmpty()) {
            return;
        }

        $startIndex = 0;
        $firstRow = $rows->first();
        $firstRowCollection = $firstRow instanceof Collection ? $firstRow : collect($firstRow);
        if ($this->looksLikeHeaderRow($firstRowCollection)) {
            $startIndex = 1;
        }

        foreach ($rows->slice($startIndex) as $index => $row) {
            $rowNumber = $startIndex + $index + 1;
            $rowCollection = $row instanceof Collection ? $row : collect($row);

            $productName = $this->cell($rowCollection, 0);
            $categoryName = $this->cell($rowCollection, 1);
            $price = $this->cell($rowCollection, 2);

            if (blank($productName) && blank($categoryName) && blank($price)) {
                continue;
            }

            if (blank($productName)) {
                $this->errors[] = "الصف {$rowNumber}: اسم المنتج مطلوب.";
                continue;
            }

            $priceVal = $price !== null && $price !== '' ? trim((string) $price) : '';
            if ($priceVal === '' || ! is_numeric($priceVal)) {
                $this->errors[] = "الصف {$rowNumber}: السعر يجب أن يكون رقماً.";
                continue;
            }

            $priceFloat = (float) $priceVal;
            if ($priceFloat < 0) {
                $this->errors[] = "الصف {$rowNumber}: السعر لا يمكن أن يكون سالباً.";
                continue;
            }

            $category = null;
            if (! blank($categoryName)) {
                $name = trim((string) $categoryName);
                $category = Category::where('category_name', $name)->first();
                if (! $category) {
                    $category = Category::create(['category_name' => $name]);
                }
            }

            Product::create([
                'product_name' => trim((string) $productName),
                'price' => $priceFloat,
                'category_id' => $category?->id,
                'status' => 'active',
                'description' => null,
            ]);

            $this->imported++;
        }
    }

    protected function cell(Collection $row, int $index): mixed
    {
        $keys = [$index, ['A', 'B', 'C'][$index]];
        foreach ($keys as $key) {
            if ($row->has($key)) {
                $v = $row->get($key);
                if ($v !== null && $v !== '') {
                    return $v;
                }
            }
        }

        return null;
    }

    protected function looksLikeHeaderRow(Collection $row): bool
    {
        $a = $this->cell($row, 0);
        $c = $this->cell($row, 2);
        $strA = is_scalar($a) ? trim((string) $a) : '';
        $strC = is_scalar($c) ? trim((string) $c) : '';
        $nameHeaders = ['اسم المنتج', 'اسم_المنتج', 'product_name', 'product name'];
        if (! in_array($strA, $nameHeaders, true)) {
            return false;
        }

        return ! is_numeric($strC);
    }

    public function getImportedCount(): int
    {
        return $this->imported;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
