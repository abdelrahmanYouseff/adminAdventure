<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Order::observe(OrderObserver::class);

        Event::listen(MessageSending::class, function (MessageSending $event): void {
            $configured = (array) config('mail.bcc', []);
            if ($configured === []) {
                return;
            }

            $message = $event->message;
            $already = array_change_key_case(array_merge(
                $message->getTo() ?? [],
                $message->getCc() ?? [],
                $message->getBcc() ?? [],
            ), CASE_LOWER);

            foreach ($configured as $email) {
                if (! is_string($email) || $email === '') {
                    continue;
                }

                $normalized = strtolower(trim($email));
                if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                if (array_key_exists($normalized, $already)) {
                    continue;
                }

                $message->addBcc($normalized);
                $already[$normalized] = true;
            }
        });
    }
}
