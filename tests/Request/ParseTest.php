<?php
declare(strict_types = 1);

namespace Tests\Innmind\HttpParser\Request;

use Innmind\HttpParser\Request\Parse;
use Innmind\TimeContinuum\Earth\Clock;
use Innmind\Http\{
    Message\Request,
    Message\Method,
    ProtocolVersion,
};
use Innmind\IO\IO;
use Innmind\Url\Path;
use Innmind\Stream\Streams;
use Innmind\Immutable\Str;
use PHPUnit\Framework\TestCase;
use Innmind\BlackBox\{
    PHPUnit\BlackBox,
    Set,
};

class ParseTest extends TestCase
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

                $request = Parse::of($streams, new Clock)($chunks)->match(
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

    public function testParseGetAllAtOnce()
    {
        $content = Str::of("GET /foo HTTP/1.1\r\n")
            ->append("Host: localhost:8080\r\n")
            ->append("Upgrade-Insecure-Requests: 1\r\n")
            ->append("Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n")
            ->append("User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.3 Safari/605.1.15\r\n")
            ->append("Accept-Language: en-GB,en;q=0.9\r\n")
            ->append("Accept-Encoding: gzip, deflate\r\n")
            ->append("Connection: keep-alive\r\n")
            ->append("\r\n");
        $streams = Streams::fromAmbientAuthority();
        $stream = $streams
            ->temporary()
            ->new()
            ->write($content)
            ->flatMap(static fn($stream) => $stream->rewind())
            ->match(
                static fn($stream) => $stream,
                static fn() => null,
            );
        $chunks = IO::of($streams->watch()->waitForever(...))
            ->readable()
            ->wrap($stream)
            ->chunks(8192);

        $request = Parse::of($streams, new Clock)($chunks)->match(
            static fn($request) => $request,
            static fn() => null,
        );

        $this->assertInstanceOf(Request::class, $request);
        $this->assertSame(Method::get, $request->method());
        $this->assertSame('/foo', $request->url()->toString());
        $this->assertSame(ProtocolVersion::v11, $request->protocolVersion());
        $this->assertCount(7, $request->headers());
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

                $request = Parse::of($streams, new Clock)($chunks)->match(
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

                $request = Parse::of($streams, new Clock)($chunks)->match(
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
                    'some[key]=value&foo=bar',
                    $request->body()->toString(),
                );
            });
    }

    public function testParseGetWithBackslashR()
    {
        $this
            ->forAll(Set\Integers::between(1, 8192))
            ->then(function($size) {
                $raw = <<<RAW
                GET /hello HTTP/1.1\r
                User-Agent: Mozilla/4.0 (compatible; MSIE5.01; Windows NT)\r
                Host: innmind.com\r
                Accept-Language: fr-fr\r
                Accept-Encoding: gzip, deflate\r
                Connection: Keep-Alive\r
                \r

                RAW;
                $streams = Streams::fromAmbientAuthority();
                $chunks = IO::of($streams->watch()->waitForever(...))
                    ->readable()
                    ->wrap(
                        $streams
                            ->temporary()
                            ->new()
                            ->write(Str::of($raw))
                            ->flatMap(static fn($stream) => $stream->rewind())
                            ->match(
                                static fn($stream) => $stream,
                                static fn() => null,
                            ),
                    )
                    ->chunks($size);

                $request = Parse::of($streams, new Clock)($chunks)->match(
                    static fn($request) => $request,
                    static fn() => null,
                );

                $this->assertInstanceOf(Request::class, $request);
                $this->assertSame(Method::get, $request->method());
                $this->assertSame('/hello', $request->url()->toString());
                $this->assertSame(ProtocolVersion::v11, $request->protocolVersion());
                $this->assertCount(5, $request->headers());
            });
    }

    public function testParsePostWithBackslashR()
    {
        $this
            ->forAll(Set\Integers::between(1, 8192))
            ->then(function($size) {
                $raw = <<<RAW
                POST /some-form HTTP/1.1\r
                User-Agent: Mozilla/4.0 (compatible; MSIE5.01; Windows NT)\r
                Host: innmind.com\r
                Content-Type: application/x-www-form-urlencoded\r
                Content-Length: 23\r
                Accept-Language: fr-fr\r
                Accept-Encoding: gzip, deflate\r
                Connection: Keep-Alive\r
                \r
                some[key]=value&foo=bar\r
                \r

                RAW;
                $streams = Streams::fromAmbientAuthority();
                $chunks = IO::of($streams->watch()->waitForever(...))
                    ->readable()
                    ->wrap(
                        $streams
                            ->temporary()
                            ->new()
                            ->write(Str::of($raw))
                            ->flatMap(static fn($stream) => $stream->rewind())
                            ->match(
                                static fn($stream) => $stream,
                                static fn() => null,
                            ),
                    )
                    ->chunks($size);

                $request = Parse::of($streams, new Clock)($chunks)->match(
                    static fn($request) => $request,
                    static fn() => null,
                );

                $this->assertInstanceOf(Request::class, $request);
                $this->assertSame(Method::post, $request->method());
                $this->assertSame('/some-form', $request->url()->toString());
                $this->assertSame(ProtocolVersion::v11, $request->protocolVersion());
                $this->assertCount(7, $request->headers());
                $this->assertSame(
                    'some[key]=value&foo=bar',
                    $request->body()->toString(),
                );
            });
    }

    public function testParsePostWithBackslashRWithChunkEndingWithBackslashR()
    {
        $raw = <<<RAW
        POST /some-form HTTP/1.1\r
        User-Agent: Mozilla/4.0 (compatible; MSIE5.01; Windows NT)\r
        Host: innmind.com\r
        Content-Type: application/x-www-form-urlencoded\r
        Content-Length: 23\r
        Accept-Language: fr-fr\r
        Accept-Encoding: gzip, deflate\r
        Connection: Keep-Alive\r
        \r
        some[key]=value&foo=bar\r
        \r

        RAW;
        $streams = Streams::fromAmbientAuthority();
        // first chunk ending in the middle of line between the headers and
        // the body
        $chunks = IO::of($streams->watch()->waitForever(...))
            ->readable()
            ->wrap(
                $streams
                    ->temporary()
                    ->new()
                    ->write(Str::of($raw))
                    ->flatMap(static fn($stream) => $stream->rewind())
                    ->match(
                        static fn($stream) => $stream,
                        static fn() => null,
                    ),
            )
            ->chunks(255);

        $request = Parse::of($streams, new Clock)($chunks)->match(
            static fn($request) => $request,
            static fn() => null,
        );

        $this->assertInstanceOf(Request::class, $request);
        $this->assertSame(Method::post, $request->method());
        $this->assertSame('/some-form', $request->url()->toString());
        $this->assertSame(ProtocolVersion::v11, $request->protocolVersion());
        $this->assertCount(7, $request->headers());
        $this->assertSame(
            'some[key]=value&foo=bar',
            $request->body()->toString(),
        );
    }
}
