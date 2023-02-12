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
            ->forAll(Set\Integers::above(1))
            ->then(function($size) {
                $chunks = Str::of(\file_get_contents('fixtures/get.txt'))->chunk($size);
                $parse = new Parse(new Clock);

                $request = $parse($chunks)->match(
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
            ->forAll(Set\Integers::above(1))
            ->then(function($size) {
                $chunks = Str::of(\file_get_contents('fixtures/post.txt'))->chunk($size);
                $parse = new Parse(new Clock);

                $request = $parse($chunks)->match(
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
                    "some[key]=value&foo=bar\n",
                    $request->body()->toString(),
                );
            });
    }
}
