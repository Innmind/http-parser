<?php
declare(strict_types = 1);

namespace Tests\Innmind\HttpParser\Request;

use Innmind\HttpParser\Request\Parse2;
use Innmind\TimeContinuum\Earth\Clock;
use Innmind\Http\{
    Message\Request,
    Message\Method,
    ProtocolVersion,
};
use Innmind\IO\IO;
use Innmind\Url\Path;
use Innmind\Stream\Streams;
use PHPUnit\Framework\TestCase;
use Innmind\BlackBox\{
    PHPUnit\BlackBox,
    Set,
};

class Parse2Test extends TestCase
{
    use BlackBox;

    public function testParseGet()
    {
        $this
            ->forAll(Set\Integers::between(1, 8192))
            ->then(function($size) {
                $streams = Streams::fromAmbientAuthority();
                $chunks = IO::of($streams->watch()->waitForever(...))
                    ->readable()
                    ->wrap($streams->readable()->open(Path::of('fixtures/get.txt')))
                    ->chunks($size);

                $request = (new Parse2($streams, new Clock))($chunks)->match(
                    static fn($request) => $request,
                    static fn() => null,
                );

                $this->assertInstanceOf(Request::class, $request);
                $this->assertSame(Method::get, $request->method());
                $this->assertSame('/hello', $request->url()->toString());
                $this->assertSame(ProtocolVersion::v11, $request->protocolVersion());
                $this->assertCount(5, $request->headers());
                $this->assertSame(
                    'User-Agent: Mozilla/4.0 (compatible; MSIE5.01; Windows NT)',
                    $request->headers()->get('user-agent')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    'Host: innmind.com',
                    $request->headers()->get('host')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    'Accept-Language: fr-fr;q=1',
                    $request->headers()->get('accept-language')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    'Accept-Encoding: gzip;q=1, deflate;q=1',
                    $request->headers()->get('accept-encoding')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    'Connection: Keep-Alive',
                    $request->headers()->get('connection')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
            });
    }

    public function testParsePost()
    {
        $this
            ->forAll(Set\Integers::between(1, 8192))
            ->then(function($size) {
                $streams = Streams::fromAmbientAuthority();
                $chunks = IO::of($streams->watch()->waitForever(...))
                    ->readable()
                    ->wrap($streams->readable()->open(Path::of('fixtures/post.txt')))
                    ->chunks($size);

                $request = (new Parse2($streams, new Clock))($chunks)->match(
                    static fn($request) => $request,
                    static fn() => null,
                );

                $this->assertInstanceOf(Request::class, $request);
                $this->assertSame(Method::post, $request->method());
                $this->assertSame('/some-form', $request->url()->toString());
                $this->assertSame(ProtocolVersion::v11, $request->protocolVersion());
                $this->assertCount(7, $request->headers());
                $this->assertSame(
                    'User-Agent: Mozilla/4.0 (compatible; MSIE5.01; Windows NT)',
                    $request->headers()->get('user-agent')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    'Host: innmind.com',
                    $request->headers()->get('host')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    'Content-Type: application/x-www-form-urlencoded',
                    $request->headers()->get('content-type')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    'Content-Length: 23',
                    $request->headers()->get('content-length')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    'Accept-Language: fr-fr;q=1',
                    $request->headers()->get('accept-language')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    'Accept-Encoding: gzip;q=1, deflate;q=1',
                    $request->headers()->get('accept-encoding')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    'Connection: Keep-Alive',
                    $request->headers()->get('connection')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    'some[key]=value&foo=bar',
                    $request->body()->toString(),
                );
            });
    }

    public function testParseUnboundedPost()
    {
        $this
            ->forAll(Set\Integers::between(1, 8192))
            ->then(function($size) {
                $streams = Streams::fromAmbientAuthority();
                $chunks = IO::of($streams->watch()->waitForever(...))
                    ->readable()
                    ->wrap($streams->readable()->open(Path::of('fixtures/unbounded-post.txt')))
                    ->chunks($size);

                $request = (new Parse2($streams, new Clock))($chunks)->match(
                    static fn($request) => $request,
                    static fn() => null,
                );

                $this->assertInstanceOf(Request::class, $request);
                $this->assertSame(Method::post, $request->method());
                $this->assertSame('/some-form', $request->url()->toString());
                $this->assertSame(ProtocolVersion::v11, $request->protocolVersion());
                $this->assertCount(6, $request->headers());
                $this->assertSame(
                    'User-Agent: Mozilla/4.0 (compatible; MSIE5.01; Windows NT)',
                    $request->headers()->get('user-agent')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    'Host: innmind.com',
                    $request->headers()->get('host')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    'Content-Type: application/x-www-form-urlencoded',
                    $request->headers()->get('content-type')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    'Accept-Language: fr-fr;q=1',
                    $request->headers()->get('accept-language')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    'Accept-Encoding: gzip;q=1, deflate;q=1',
                    $request->headers()->get('accept-encoding')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    'Connection: Keep-Alive',
                    $request->headers()->get('connection')->match(
                        static fn($header) => $header->toString(),
                        static fn() => null,
                    ),
                );
                $this->assertSame(
                    "some[key]=value&foo=bar\n\n",
                    $request->body()->toString(),
                );
            });
    }
}
