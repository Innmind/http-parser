<?php
declare(strict_types = 1);

namespace Tests\Innmind\HttpParser\ServerRequest;

use Innmind\HttpParser\ServerRequest\Transform;
use Innmind\Http\{
    Request,
    ServerRequest,
    Method,
    ProtocolVersion,
    Headers,
    Header,
};
use Innmind\Url\{
    Url,
    Authority\Host,
    Authority\Port,
};
use Innmind\BlackBox\{
    PHPUnit\BlackBox,
    Set,
    PHPUnit\Framework\TestCase,
};

class TransformTest extends TestCase
{
    use BlackBox;

    public function testInjectHostInUrlFromHeader()
    {
        $this
            ->forAll(
                Set\Elements::of(...Method::cases()),
                Set\Elements::of(...ProtocolVersion::cases()),
            )
            ->then(function($method, $protocol) {
                $request = Request::of(
                    Url::of('/foo'),
                    $method,
                    $protocol,
                    Headers::of(
                        Header\Host::of(Host::of('innmind.com'), Port::of(8080)),
                    ),
                );

                $serverRequest = Transform::of()($request);

                $this->assertSame(
                    'innmind.com:8080/foo',
                    $serverRequest->url()->toString(),
                );
            });
    }

    public function testDoesntInjectHostInUrlFromHeaderIfAlreadyInUrl()
    {
        $this
            ->forAll(
                Set\Elements::of(...Method::cases()),
                Set\Elements::of(...ProtocolVersion::cases()),
            )
            ->then(function($method, $protocol) {
                $request = Request::of(
                    Url::of('//example.com:443/foo'), // without the first // it interprets the host as the scheme
                    $method,
                    $protocol,
                    Headers::of(
                        Header\Host::of(Host::of('innmind.com'), Port::of(8080)),
                    ),
                );

                $serverRequest = Transform::of()($request);

                $this->assertSame(
                    'example.com:443/foo',
                    $serverRequest->url()->toString(),
                );
            });
    }

    public function testInjectUserInformationInUrlFromHeader()
    {
        $this
            ->forAll(
                Set\Elements::of(...Method::cases()),
                Set\Elements::of(...ProtocolVersion::cases()),
            )
            ->then(function($method, $protocol) {
                $request = Request::of(
                    Url::of('/foo'),
                    $method,
                    $protocol,
                    Headers::of(
                        Header\Authorization::of(
                            'Basic',
                            \base64_encode('foo:bar'),
                        ),
                    ),
                );

                $serverRequest = Transform::of()($request);

                $this->assertSame(
                    'foo:bar@/foo',
                    $serverRequest->url()->toString(),
                );
            });
    }

    public function testDoesntInjectUserInformationInUrlFromHeaderIfNotBasicScheme()
    {
        $this
            ->forAll(
                Set\Elements::of(...Method::cases()),
                Set\Elements::of(...ProtocolVersion::cases()),
                Set\Strings::madeOf(Set\Chars::alphanumerical())->atLeast(1),
                Set\Strings::any(),
            )
            ->then(function($method, $protocol, $scheme, $token) {
                $request = Request::of(
                    Url::of('/foo'),
                    $method,
                    $protocol,
                    Headers::of(
                        Header\Authorization::of($scheme, $token),
                    ),
                );

                $serverRequest = Transform::of()($request);

                $this->assertSame(
                    '/foo',
                    $serverRequest->url()->toString(),
                );
            });
    }

    public function testDoesntInjectUserInformationInUrlFromHeaderIfAlreadyInUrl()
    {
        $this
            ->forAll(
                Set\Elements::of(...Method::cases()),
                Set\Elements::of(...ProtocolVersion::cases()),
            )
            ->then(function($method, $protocol) {
                $request = Request::of(
                    Url::of('//some:pwd@/foo'), // without the first // it interprets the host as the scheme
                    $method,
                    $protocol,
                    Headers::of(
                        Header\Authorization::of(
                            'Basic',
                            \base64_encode('foo:bar'),
                        ),
                    ),
                );

                $serverRequest = Transform::of()($request);

                $this->assertSame(
                    'some:pwd@/foo',
                    $serverRequest->url()->toString(),
                );
            });
    }
}
