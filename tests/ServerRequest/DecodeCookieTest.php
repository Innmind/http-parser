<?php
declare(strict_types = 1);

namespace Tests\Innmind\HttpParser\ServerRequest;

use Innmind\HttpParser\{
    ServerRequest\DecodeCookie,
    ServerRequest\Transform,
    Request\Parse,
};
use Innmind\TimeContinuum\Earth\Clock;
use Innmind\IO\IO;
use Innmind\Stream\Streams;
use Innmind\Url\Path;
use Innmind\Http\ServerRequest;
use Innmind\BlackBox\PHPUnit\Framework\TestCase;

class DecodeCookieTest extends TestCase
{
    private Parse $parse;

    public function setUp(): void
    {
        $this->parse = Parse::default(new Clock);
    }

    public function testDecode()
    {
        $streams = Streams::fromAmbientAuthority();
        $io = IO::of($streams->watch()->waitForever(...))
            ->readable()
            ->wrap($streams->readable()->open(Path::of('fixtures/cookie.txt')));

        $request = ($this->parse)($io)
            ->map(Transform::of())
            ->map(DecodeCookie::of())
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
        $streams = Streams::fromAmbientAuthority();
        $io = IO::of($streams->watch()->waitForever(...))
            ->readable()
            ->wrap($streams->readable()->open(Path::of('fixtures/get.txt')));

        $request = ($this->parse)($io)
            ->map(Transform::of())
            ->map(DecodeCookie::of())
            ->match(
                static fn($request) => $request,
                static fn() => null,
            );

        $this->assertInstanceOf(ServerRequest::class, $request);
        $this->assertCount(0, $request->cookies());
    }
}
