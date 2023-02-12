<?php
declare(strict_types = 1);

namespace Tests\Innmind\HttpParser\ServerRequest;

use Innmind\HttpParser\ServerRequest\{
    DecodeQuery,
    Transform,
};
use Innmind\Http\{
    Message\Request\Request,
    Message\Method,
    ProtocolVersion,
};
use Innmind\Url\Url;
use PHPUnit\Framework\TestCase;
use Innmind\BlackBox\{
    PHPUnit\BlackBox,
    Set,
};

class DecodeQueryTest extends TestCase
{
    use BlackBox;

    public function testDecodeQueryFromUrl()
    {
        $this
            ->forAll(
                Set\Elements::of(...Method::cases()),
                Set\Elements::of(...ProtocolVersion::cases()),
            )
            ->then(function($method, $protocol) {
                $request = new Request(
                    Url::of('/?some[key]=value&foo=bar'), // without the first // it interprets the host as the scheme
                    $method,
                    $protocol,
                );

                $serverRequest = (new DecodeQuery)((new Transform)($request));

                $this->assertSame(
                    'value',
                    $serverRequest
                        ->query()
                        ->dictionary('some')
                        ->flatMap(static fn($data) => $data->get('key'))
                        ->match(
                            static fn($value) => $value,
                            static fn() => null,
                        ),
                );
                $this->assertSame(
                    'bar',
                    $serverRequest
                        ->query()
                        ->get('foo')
                        ->match(
                            static fn($value) => $value,
                            static fn() => null,
                        ),
                );
            });
    }
}
