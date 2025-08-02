<?php
declare(strict_types = 1);

namespace Tests\Innmind\HttpParser\ServerRequest;

use Innmind\HttpParser\{
    ServerRequest\DecodeCookie,
    ServerRequest\Transform,
    Request\Parse,
};
use Innmind\TimeContinuum\Clock;
use Innmind\IO\IO;
use Innmind\Http\ServerRequest;
use Innmind\BlackBox\PHPUnit\Framework\TestCase;

class DecodeCookieTest extends TestCase
{
    private Parse $parse;

    public function setUp(): void
    {
        $this->parse = Parse::default(Clock::live());
    }

    public function testDecode()
    {
        $io = IO::fromAmbientAuthority()
            ->streams()
            ->acquire(\fopen('fixtures/cookie.txt', 'r'))
            ->read();

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
        $io = IO::fromAmbientAuthority()
            ->streams()
            ->acquire(\fopen('fixtures/post.txt', 'r'))
            ->read();

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
