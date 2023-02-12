<?php
declare(strict_types = 1);

namespace Tests\Innmind\HttpParser\ServerRequest;

use Innmind\HttpParser\{
    ServerRequest\DecodeForm,
    ServerRequest\Transform,
    Request\Parse,
};
use Innmind\TimeContinuum\Earth\Clock;
use Innmind\Http\Message\ServerRequest;
use Innmind\Stream\Streams;
use Innmind\Immutable\Str;
use PHPUnit\Framework\TestCase;

class DecodeFormTest extends TestCase
{
    public function testDecode()
    {
        $request = (new Parse(new Clock, Streams::fromAmbientAuthority()))(
            Str::of(\file_get_contents('fixtures/post.txt'))->chunk(10),
        )
            ->map(new Transform)
            ->map(new DecodeForm)
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
        $request = (new Parse(new Clock, Streams::fromAmbientAuthority()))(
            Str::of(\file_get_contents('fixtures/get.txt'))->chunk(10),
        )
            ->map(new Transform)
            ->map(new DecodeForm)
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
