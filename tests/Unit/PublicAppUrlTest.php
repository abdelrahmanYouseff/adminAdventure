<?php

namespace Tests\Unit;

use App\Support\PublicAppUrl;
use Tests\TestCase;

class PublicAppUrlTest extends TestCase
{
    public function test_strips_www_from_public_url(): void
    {
        config(['app.public_url' => 'https://www.admin.adventureksa.com']);

        $this->assertSame(
            'https://admin.adventureksa.com/order/shz71/location',
            PublicAppUrl::to('/order/shz71/location')
        );
    }

    public function test_uses_public_url_without_www(): void
    {
        config(['app.public_url' => 'https://admin.adventureksa.com']);

        $this->assertSame('https://admin.adventureksa.com', PublicAppUrl::base());
    }
}
