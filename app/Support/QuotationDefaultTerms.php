<?php

namespace App\Support;

class QuotationDefaultTerms
{
    /**
     * @return list<array{ar: string, en: string}>
     */
    public static function all(): array
    {
        return [
            [
                'ar' => 'يُسدَّد 100٪ من المبلغ عند الموافقة، مع إرفاق إيصال التحويل.',
                'en' => '100% of the amount is payable upon approval, and the transfer receipt must be attached.',
            ],
            [
                'ar' => 'يحوّل العميل مبلغ تأمين مسترد بنسبة 40٪ من قيمة الطلب بموجب إيصال منفصل، ويُسترد بعد التأكد من سلامة جميع الألعاب المسلَّمة.',
                'en' => 'A refundable security deposit of 40% of the order value is to be transferred by the client through a separate receipt, and it is refunded after confirming that all games delivered to the client are undamaged.',
            ],
            [
                'ar' => 'يتم التوريد والتركيب بعد تحويل مبلغ التأمين.',
                'en' => 'Supply and installation of the games take place after the security deposit has been transferred.',
            ],
            [
                'ar' => 'في حال تأخر العميل عن إعادة الألعاب، تُحتسب غرامة بنسبة 120٪ عن كل يوم تأخير في التسليم.',
                'en' => 'If the client delays the return of the games, a penalty of 120% is charged for each day of delay in delivery.',
            ],
            [
                'ar' => 'في حال إلغاء الفعالية من قبل العميل لا يُسترد المبلغ، ويُسجَّل رصيدًا دائنًا لدى الشركة يمكن استخدامه خارج المواسم والإجازات الرسمية.',
                'en' => 'If the client cancels the event, the amount is not refunded; it is recorded as a credit balance with the company, and the client may use it outside of seasons and official holidays.',
            ],
            [
                'ar' => 'أي عطل فني ناتج عن سوء استخدام الألعاب بعد تسليمها من الشركة يقع كاملًا على مسؤولية العميل.',
                'en' => 'Any technical malfunction resulting from misuse of the games after their delivery by the company is the full responsibility of the client.',
            ],
            [
                'ar' => 'يتحمل العميل كامل التكلفة والمسؤولية إذا اختلفت تفاصيل الموقع عن الوصف الفعلي (بما في ذلك ما يتعلق بالتركيب).',
                'en' => 'The client bears the full cost and responsibility if the site details differ from the actual description (including with regard to installation).',
            ],
        ];
    }

    /**
     * @param  mixed  $terms
     * @return list<array{ar: string, en: string}>
     */
    public static function sanitize(mixed $terms): array
    {
        if (! is_array($terms)) {
            return [];
        }

        $clean = [];

        foreach ($terms as $term) {
            if (! is_array($term)) {
                continue;
            }

            $ar = trim((string) ($term['ar'] ?? ''));
            $en = trim((string) ($term['en'] ?? ''));

            if ($ar === '' && $en === '') {
                continue;
            }

            $clean[] = [
                'ar' => $ar !== '' ? $ar : $en,
                'en' => $en !== '' ? $en : $ar,
            ];
        }

        return array_values($clean);
    }
}
