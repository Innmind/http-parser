<?php
declare(strict_types = 1);

namespace Tests\Innmind\HttpParser\Request;

use Innmind\HttpParser\Request\Parse;
use Innmind\Time\Clock;
use Innmind\Http\{
    Request,
    Method,
    ProtocolVersion,
    Factory\Header\Factory,
};
use Innmind\IO\IO;
use Innmind\Immutable\{
    Str,
    Sequence,
};
use Innmind\BlackBox\PHPUnit\Framework\TestCase;

class ParseTest extends TestCase
{
    private Parse $parse;

    public function setUp(): void
    {
        $this->parse = Parse::of(Factory::new(Clock::live()));
    }

    public function testParseGet()
    {
        $io = IO::fromAmbientAuthority()
            ->streams()
            ->acquire(\fopen('fixtures/get.txt', 'r'))
            ->read();

        $request = ($this->parse)($io)->match(
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
        $tmp = \fopen('php://temp', 'w+');
        $io = IO::fromAmbientAuthority()
            ->streams()
            ->acquire($tmp);
        $_ = $io
            ->write()
            ->sink(Sequence::of($content))
            ->unwrap();
        \fseek($tmp, 0);
        $io = $io->read();

        $request = ($this->parse)($io)->match(
            static fn($request) => $request,
            static fn() => null,
        );

        $this->assertInstanceOf(Request::class, $request);
        $this->assertSame(Method::get, $request->method());
        $this->assertSame('/foo', $request->url()->toString());
        $this->assertSame(ProtocolVersion::v11, $request->protocolVersion());
        $this->assertCount(7, $request->headers());
    }

    public function testParsePostWithoutABody()
    {
        $content = Str::of("POST /foo HTTP/1.1\r\n")
            ->append("Host: localhost:8080\r\n")
            ->append("Content-Length: 0\r\n")
            ->append("Connection: close\r\n")
            ->append("\r\n");
        $tmp = \fopen('php://temp', 'w+');
        $io = IO::fromAmbientAuthority()
            ->streams()
            ->acquire($tmp);
        $_ = $io
            ->write()
            ->sink(Sequence::of($content))
            ->unwrap();
        \fseek($tmp, 0);
        $io = $io->read();

        $request = ($this->parse)($io)->match(
            static fn($request) => $request,
            static fn() => null,
        );

        $this->assertInstanceOf(Request::class, $request);
        $this->assertSame(Method::post, $request->method());
        $this->assertSame('/foo', $request->url()->toString());
        $this->assertSame(ProtocolVersion::v11, $request->protocolVersion());
        $this->assertCount(3, $request->headers());
    }

    public function testParsePost()
    {
        $io = IO::fromAmbientAuthority()
            ->streams()
            ->acquire(\fopen('fixtures/post.txt', 'r'))
            ->read();

        $request = ($this->parse)($io)->match(
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
    }

    public function testParseUnboundedPost()
    {
        $io = IO::fromAmbientAuthority()
            ->streams()
            ->acquire(\fopen('fixtures/unbounded-post.txt', 'r'))
            ->read();

        $request = ($this->parse)($io)->match(
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
    }

    public function testParseGetWithBackslashR()
    {
        $raw = <<<RAW
        GET /hello HTTP/1.1\r
        User-Agent: Mozilla/4.0 (compatible; MSIE5.01; Windows NT)\r
        Host: innmind.com\r
        Accept-Language: fr-fr\r
        Accept-Encoding: gzip, deflate\r
        Connection: Keep-Alive\r
        \r

        RAW;
        $tmp = \fopen('php://temp', 'w+');
        $io = IO::fromAmbientAuthority()
            ->streams()
            ->acquire($tmp);
        $_ = $io
            ->write()
            ->sink(Sequence::of(Str::of($raw)))
            ->unwrap();
        \fseek($tmp, 0);
        $io = $io->read();

        $request = ($this->parse)($io)->match(
            static fn($request) => $request,
            static fn() => null,
        );

        $this->assertInstanceOf(Request::class, $request);
        $this->assertSame(Method::get, $request->method());
        $this->assertSame('/hello', $request->url()->toString());
        $this->assertSame(ProtocolVersion::v11, $request->protocolVersion());
        $this->assertCount(5, $request->headers());
    }

    public function testParsePostWithBackslashR()
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
        $tmp = \fopen('php://temp', 'w+');
        $io = IO::fromAmbientAuthority()
            ->streams()
            ->acquire($tmp);
        $_ = $io
            ->write()
            ->sink(Sequence::of(Str::of($raw)))
            ->unwrap();
        \fseek($tmp, 0);
        $io = $io->read();

        $request = ($this->parse)($io)->match(
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
        // first chunk ending in the middle of line between the headers and
        // the body
        $tmp = \fopen('php://temp', 'w+');
        $io = IO::fromAmbientAuthority()
            ->streams()
            ->acquire($tmp);
        $_ = $io
            ->write()
            ->sink(Sequence::of(Str::of($raw)))
            ->unwrap();
        \fseek($tmp, 0);
        $io = $io->read();

        $request = ($this->parse)($io)->match(
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
