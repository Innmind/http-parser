<?php
declare(strict_types = 1);

namespace Tests\Innmind\HttpParser\ServerRequest;

use Innmind\HttpParser\ServerRequest\{
    DecodeQuery,
    Transform,
};
use Innmind\Http\{
    Request,
    Method,
    ProtocolVersion,
};
use Innmind\Url\Url;
use Innmind\BlackBox\{
    PHPUnit\BlackBox,
    Set,
    PHPUnit\Framework\TestCase,
};

class DecodeQueryTest extends TestCase
{
    use BlackBox;

    public function testDecodeQueryFromUrl()
    {
        $this
            ->forAll(
                Set::of(...Method::cases()),
                Set::of(...ProtocolVersion::cases()),
            )
            ->then(function($method, $protocol) {
                $request = Request::of(
                    Url::of('/?some[key]=value&foo=bar'), // without the first // it interprets the host as the scheme
                    $method,
                    $protocol,
                );

                $serverRequest = DecodeQuery::of()(Transform::of()($request));

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
