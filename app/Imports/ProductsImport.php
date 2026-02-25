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
     * @param string $path Full path to file (temp upload path may have no extension)
     * @param string|null $originalExtension Extension from uploaded file name (e.g. xlsx, csv)
     */
    public function importFromPath(string $path, ?string $originalExtension = null): void
    {
        $ext = $originalExtension !== null && $originalExtension !== ''
            ? strtolower($originalExtension)
            : strtolower(pathinfo($path, PATHINFO_EXTENSION));

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
            if ($rowNum === 1 && $productName !== '') {
                $productName = preg_replace('/^\xEF\xBB\xBF/', '', $productName);
            }

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

            $priceFloat = $this->parsePrice($price);
            if ($priceFloat === null) {
                $this->errors[] = "الصف {$rowNum}: السعر يجب أن يكون رقماً (صحيح أو عشري، مثل 100 أو 99.50).";
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
            $price = $this->getCellValue($sheet, $row, 'C');

            if ($productName === '' && $categoryName === '' && ($price === null || (string) $price === '')) {
                continue;
            }

            if ($productName === '') {
                $this->errors[] = "الصف {$row}: اسم المنتج مطلوب.";
                continue;
            }

            $priceFloat = $this->parsePrice($price);
            if ($priceFloat === null) {
                $this->errors[] = "الصف {$row}: السعر يجب أن يكون رقماً (صحيح أو عشري).";
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

    /**
     * Parse price: accepts int, float, or string (dot/comma decimal, Arabic numerals ٠-٩).
     * Returns float or null if invalid/negative.
     */
    protected function parsePrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return $value >= 0 ? (float) $value : null;
        }
        $s = trim((string) $value);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = $this->normalizeArabicNumerals($s);
        $s = preg_replace('/\s*(ج\.?\s*م\.?|EGP|ر\.?\s*س\.?|SAR|USD|\$|د\.?\s*ك\.?|د\.?\s*ع\.?)\s*$/ui', '', $s);
        $s = preg_replace('/^\s*(ج\.?\s*م\.?|EGP|ر\.?\s*س\.?|SAR|USD|\$)\s*/ui', '', $s);
        $s = preg_replace('/\s+/', '', $s);
        if ($s === '') {
            return null;
        }
        if (str_contains($s, ',') && ! str_contains($s, '.')) {
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace(',', '', $s);
        }
        if (! is_numeric($s)) {
            return null;
        }
        $f = (float) $s;

        return $f >= 0 ? $f : null;
    }

    /** Convert Arabic-Indic numerals (٠١٢٣٤٥٦٧٨٩) to Western (0-9). */
    protected function normalizeArabicNumerals(string $s): string
    {
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabic, $western, $s);
    }

    /** True if column C value looks like a "price" header (so we skip that row). */
    protected function isPriceHeader(mixed $cellC): bool
    {
        $str = is_scalar($cellC) ? trim((string) $cellC) : '';
        $priceHeaders = ['السعر', 'سعر', 'price', 'Price', 'PRICE', 'الثمن'];

        return in_array($str, $priceHeaders, true);
    }

    protected function looksLikeHeaderRow(mixed $cellA, mixed $cellC): bool
    {
        if ($this->isPriceHeader($cellC)) {
            return true;
        }
        $strA = is_scalar($cellA) ? trim((string) $cellA) : '';
        $strC = is_scalar($cellC) ? trim((string) $cellC) : '';
        $nameHeaders = ['اسم المنتج', 'اسم_المنتج', 'المنتج', 'product_name', 'product name', 'Product Name'];

        if (! in_array($strA, $nameHeaders, true)) {
            return false;
        }

        return $strC !== '' && $this->parsePrice($strC) === null;
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
