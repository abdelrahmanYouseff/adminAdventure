<?php

namespace App\Support;

class CheckoutRedirect
{
    /**
     * After payment: stay on the current host when developing locally,
     * otherwise use the configured app URL (production).
     */
    public static function homeAfterPayment(string $orderNumber): string
    {
        $request = request();

        if ($request && self::isLocalHost($request->getHost())) {
            return $request->getSchemeAndHttpHost()
                .'/home?'.http_build_query(['paid_order' => $orderNumber]);
        }

        return route('home', ['paid_order' => $orderNumber]);
    }

    public static function home(): string
    {
        $request = request();

        if ($request && self::isLocalHost($request->getHost())) {
            return $request->getSchemeAndHttpHost().'/home';
        }

        return route('home');
    }

    private static function isLocalHost(string $host): bool
    {
        $host = strtolower($host);

        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }
}
