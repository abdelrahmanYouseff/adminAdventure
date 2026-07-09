<?php

namespace App\Services;

use App\Models\SiteVisit;
use Illuminate\Http\Request;

class SiteVisitService
{
    public function __construct(
        private readonly GeoIpService $geoIp,
    ) {}

    public function record(Request $request): void
    {
        $ip = $request->ip();
        $geo = $this->geoIp->lookup($ip);

        SiteVisit::create([
            'session_id' => $request->session()->getId(),
            'user_id' => $request->user()?->id,
            'ip_address' => $ip,
            'country' => $geo['country'],
            'country_code' => $geo['country_code'],
            'city' => $geo['city'],
            'path' => '/'.ltrim($request->path(), '/'),
            'referrer' => $request->headers->get('referer'),
            'visited_at' => now(),
        ]);
    }

    /**
     * @return array{
     *     total_visits: int,
     *     unique_visitors: int,
     *     today_visits: int,
     *     today_unique_visitors: int
     * }
     */
    public function summaryStats(): array
    {
        return [
            'total_visits' => SiteVisit::count(),
            'unique_visitors' => (int) SiteVisit::query()->distinct()->count('session_id'),
            'today_visits' => SiteVisit::query()->whereDate('visited_at', today())->count(),
            'today_unique_visitors' => (int) SiteVisit::query()
                ->whereDate('visited_at', today())
                ->distinct()
                ->count('session_id'),
        ];
    }

    /**
     * @return list<array{country: string, country_code: string|null, visits: int, unique_visitors: int}>
     */
    public function visitorsByCountry(int $days = 30): array
    {
        return SiteVisit::query()
            ->selectRaw('country, country_code, COUNT(*) as visits, COUNT(DISTINCT session_id) as unique_visitors')
            ->where('visited_at', '>=', now()->subDays($days))
            ->groupBy('country', 'country_code')
            ->orderByDesc('unique_visitors')
            ->get()
            ->map(fn ($row) => [
                'country' => $row->country ?? 'غير معروف',
                'country_code' => $row->country_code,
                'visits' => (int) $row->visits,
                'unique_visitors' => (int) $row->unique_visitors,
            ])
            ->values()
            ->all();
    }
}
