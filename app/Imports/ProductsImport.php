<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Product;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductsImport
{
    protected int $imported = 0;

    protected array $errors = [];

    /**
     * Excel columns: A = اسم المنتج, B = الفئة, C = السعر
     * First row can be header — will be skipped if it looks like headers.
     */
    public function importFromPath(string $path): void
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        if ($highestRow < 1) {
            return;
        }

        $startRow = 1;
        $row1A = $this->getCellValue($sheet, 1, 'A');
        $row1B = $this->getCellValue($sheet, 1, 'B');
        $row1C = $this->getCellValue($sheet, 1, 'C');
        if ($this->looksLikeHeaderRow($row1A, $row1C)) {
            $startRow = 2;
        }

        for ($row = $startRow; $row <= $highestRow; $row++) {
            $productName = $this->getCellValue($sheet, $row, 'A');
            $categoryName = $this->getCellValue($sheet, $row, 'B');
            $price = $this->getCellValue($sheet, $row, 'C');

            if (trim((string) $productName) === '' && trim((string) $categoryName) === '' && trim((string) $price) === '') {
                continue;
            }

            if (trim((string) $productName) === '') {
                $this->errors[] = "الصف {$row}: اسم المنتج مطلوب.";
                continue;
            }

            $priceVal = trim((string) $price);
            if ($priceVal === '' || ! is_numeric($priceVal)) {
                $this->errors[] = "الصف {$row}: السعر يجب أن يكون رقماً.";
                continue;
            }

            $priceFloat = (float) $priceVal;
            if ($priceFloat < 0) {
                $this->errors[] = "الصف {$row}: السعر لا يمكن أن يكون سالباً.";
                continue;
            }

            $category = null;
            if (trim((string) $categoryName) !== '') {
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

    protected function getCellValue(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, string $col): mixed
    {
        $value = $sheet->getCell($col . $row)->getValue();

        return $value;
    }

    protected function looksLikeHeaderRow(mixed $cellA, mixed $cellC): bool
    {
        $strA = is_scalar($cellA) ? trim((string) $cellA) : '';
        $strC = is_scalar($cellC) ? trim((string) $cellC) : '';
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
