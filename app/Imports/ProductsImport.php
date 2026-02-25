<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Product;

class ProductsImport
{
    protected int $imported = 0;

    protected array $errors = [];

    /**
     * Import from file path. Supports CSV (UTF-8). For Excel, user can save as CSV.
     * Columns: A/1 = اسم المنتج, B/2 = الفئة, C/3 = السعر
     */
    public function importFromPath(string $path): void
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            $this->importFromCsv($path);

            return;
        }

        if (in_array($ext, ['xlsx', 'xls'], true) && class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            $this->importFromExcel($path);

            return;
        }

        if (in_array($ext, ['xlsx', 'xls'], true)) {
            $this->errors[] = 'لا يدعم السيرفر قراءة Excel. احفظ الملف كـ CSV من Excel (ملف ← حفظ باسم ← CSV UTF-8) وارفع الملف CSV.';

            return;
        }

        $this->errors[] = 'صيغة غير مدعومة. استخدم CSV أو Excel.';
    }

    protected function importFromCsv(string $path): void
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->errors[] = 'تعذر فتح الملف.';

            return;
        }

        $rowNum = 0;
        $startRow = 1;

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rowNum++;
            $productName = isset($row[0]) ? trim((string) $row[0]) : '';
            $categoryName = isset($row[1]) ? trim((string) $row[1]) : '';
            $price = isset($row[2]) ? trim((string) $row[2]) : '';

            if ($rowNum === 1 && $this->looksLikeHeaderRow($productName, $price)) {
                continue;
            }

            if ($productName === '' && $categoryName === '' && $price === '') {
                continue;
            }

            if ($productName === '') {
                $this->errors[] = "الصف {$rowNum}: اسم المنتج مطلوب.";
                continue;
            }

            if ($price === '' || ! is_numeric($price)) {
                $this->errors[] = "الصف {$rowNum}: السعر يجب أن يكون رقماً.";
                continue;
            }

            $priceFloat = (float) $price;
            if ($priceFloat < 0) {
                $this->errors[] = "الصف {$rowNum}: السعر لا يمكن أن يكون سالباً.";
                continue;
            }

            $category = null;
            if ($categoryName !== '') {
                $category = Category::where('category_name', $categoryName)->first();
                if (! $category) {
                    $category = Category::create(['category_name' => $categoryName]);
                }
            }

            Product::create([
                'product_name' => $productName,
                'price' => $priceFloat,
                'category_id' => $category?->id,
                'status' => 'active',
                'description' => null,
            ]);

            $this->imported++;
        }

        fclose($handle);
    }

    protected function importFromExcel(string $path): void
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        if ($highestRow < 1) {
            return;
        }

        $startRow = 1;
        $row1A = $this->getCellValue($sheet, 1, 'A');
        $row1C = $this->getCellValue($sheet, 1, 'C');
        if ($this->looksLikeHeaderRow($row1A, $row1C)) {
            $startRow = 2;
        }

        for ($row = $startRow; $row <= $highestRow; $row++) {
            $productName = trim((string) $this->getCellValue($sheet, $row, 'A'));
            $categoryName = trim((string) $this->getCellValue($sheet, $row, 'B'));
            $price = trim((string) $this->getCellValue($sheet, $row, 'C'));

            if ($productName === '' && $categoryName === '' && $price === '') {
                continue;
            }

            if ($productName === '') {
                $this->errors[] = "الصف {$row}: اسم المنتج مطلوب.";
                continue;
            }

            if ($price === '' || ! is_numeric($price)) {
                $this->errors[] = "الصف {$row}: السعر يجب أن يكون رقماً.";
                continue;
            }

            $priceFloat = (float) $price;
            if ($priceFloat < 0) {
                $this->errors[] = "الصف {$row}: السعر لا يمكن أن يكون سالباً.";
                continue;
            }

            $category = null;
            if ($categoryName !== '') {
                $category = Category::where('category_name', $categoryName)->first();
                if (! $category) {
                    $category = Category::create(['category_name' => $categoryName]);
                }
            }

            Product::create([
                'product_name' => $productName,
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
        return $sheet->getCell($col . $row)->getValue();
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
