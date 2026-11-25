<?php

namespace MirkoHaaser\Teams\Tests\Unit;

use InvalidArgumentException;
use MirkoHaaser\Teams\Support\DeepLink;
use PHPUnit\Framework\TestCase;

class DeepLinkTest extends TestCase
{
    public function test_builds_links_with_context(): void
    {
        $links = new DeepLink('app-id', ['home', 'items']);

        $this->assertSame('https://teams.microsoft.com/l/entity/app-id/home', $links->toTab('home'));
        $this->assertSame(
            'https://teams.microsoft.com/l/entity/app-id/items?context='.rawurlencode('{"subEntityId":"42"}'),
            $links->toTab('items', '42'),
        );
        $this->assertSame('https://teams.microsoft.com/l/entity/app-id/home', $links->firstTab());
    }

    public function test_drops_context_that_makes_the_link_too_long(): void
    {
        $this->assertSame(
            'https://teams.microsoft.com/l/entity/app-id/home',
            (new DeepLink('app-id', ['home']))->toTab('home', str_repeat('x', 3000)),
        );
    }

    public function test_unknown_tabs_fail_loudly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DeepLink('app-id', ['home']))->toTab('nope');
    }
}
