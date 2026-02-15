<?php
declare(strict_types = 1);

namespace Tests\Innmind\HttpParser\ServerRequest;

use Innmind\HttpParser\{
    ServerRequest\DecodeForm,
    ServerRequest\Transform,
    Request\Parse,
};
use Innmind\Time\Clock;
use Innmind\Http\ServerRequest;
use Innmind\IO\IO;
use Innmind\BlackBox\PHPUnit\Framework\TestCase;

class DecodeFormTest extends TestCase
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
            ->acquire(\fopen('fixtures/post.txt', 'r'))
            ->read();

        $request = ($this->parse)($io)
            ->map(Transform::of())
            ->map(DecodeForm::of())
            ->match(
                static fn($request) => $request,
                static fn() => null,
            );

        $this->assertInstanceOf(ServerRequest::class, $request);
        $this->assertSame('', $request->body()->toString());
        $this->assertSame(
            'value',
            $request
                ->form()
                ->dictionary('some')
                ->flatMap(static fn($data) => $data->get('key'))
                ->match(
                    static fn($value) => $value,
                    static fn() => null,
                ),
        );
        $this->assertSame(
            'bar',
            $request
                ->form()
                ->get('foo')
                ->match(
                    static fn($value) => $value,
                    static fn() => null,
                ),
        );
    }

    public function testDecodeEmptyBody()
    {
        $io = IO::fromAmbientAuthority()
            ->streams()
            ->acquire(\fopen('fixtures/get.txt', 'r'))
            ->read();

        $request = ($this->parse)($io)
            ->map(Transform::of())
            ->map(DecodeForm::of())
            ->match(
                static fn($request) => $request,
                static fn() => null,
            );

        $this->assertInstanceOf(ServerRequest::class, $request);
        $this->assertSame('', $request->body()->toString());
        $this->assertSame(
            [],
            $request->form()->data(),
        );
    }
}
