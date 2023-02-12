<?php
declare(strict_types = 1);

namespace Tests\Innmind\HttpParser\ServerRequest;

use Innmind\HttpParser\{
    ServerRequest\DecodeCookie,
    ServerRequest\Transform,
    Request\Parse,
};
use Innmind\TimeContinuum\Earth\Clock;
use Innmind\Stream\Streams;
use Innmind\Http\Message\ServerRequest;
use Innmind\Immutable\Str;
use PHPUnit\Framework\TestCase;

class DecodeCookieTest extends TestCase
{
    public function testDecode()
    {
        $request = (new Parse(new Clock, Streams::fromAmbientAuthority()))(
            Str::of(\file_get_contents('fixtures/cookie.txt'))->chunk(10),
        )
            ->map(new Transform)
            ->map(new DecodeCookie)
            ->match(
                static fn($request) => $request,
                static fn() => null,
            );

        $this->assertInstanceOf(ServerRequest::class, $request);
        $this->assertCount(3, $request->cookies());
        $this->assertSame(
            '298zf09hf012fh2',
            $request->cookies()->get('PHPSESSID')->match(
                static fn($value) => $value,
                static fn() => null,
            ),
        );
        $this->assertSame(
            'u32t4o3tb3gg43',
            $request->cookies()->get('csrftoken')->match(
                static fn($value) => $value,
                static fn() => null,
            ),
        );
        $this->assertSame(
            '1',
            $request->cookies()->get('_gat')->match(
                static fn($value) => $value,
                static fn() => null,
            ),
        );
    }

    public function testDecodeWhenNoCookieHeader()
    {
        $request = (new Parse(new Clock, Streams::fromAmbientAuthority()))(
            Str::of(\file_get_contents('fixtures/get.txt'))->chunk(10),
        )
            ->map(new Transform)
            ->map(new DecodeCookie)
            ->match(
                static fn($request) => $request,
                static fn() => null,
            );

        $this->assertInstanceOf(ServerRequest::class, $request);
        $this->assertCount(0, $request->cookies());
    }
}
