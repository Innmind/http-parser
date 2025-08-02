<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request\Frame;

use Innmind\Http\{
    Method,
    ProtocolVersion,
};
use Innmind\Url\Url;
use Innmind\IO\Frame;
use Innmind\Immutable\{
    Str,
    Maybe,
    Predicate\Instance,
};

final class FirstLine
{
    /**
     * @return Frame<Maybe<array{Method, Url, ProtocolVersion}>>
     */
    public static function new(): Frame
    {
        return Frame::line()
            ->map(static fn($line) => $line->trim())
            ->map(static fn(Str $line) => $line->capture('~^(?<method>[A-Z]+) (?<url>.+) HTTP/(?<protocol>1(\.[01])?)$~'))
            ->map(static function($parts) {
                $method = $parts
                    ->get('method')
                    ->map(static fn($method) => $method->toUpper()->toString())
                    ->flatMap(Method::maybe(...));
                $url = $parts
                    ->get('url')
                    ->map(static fn($url) => $url->toString())
                    ->flatMap(Url::maybe(...));
                $protocol = $parts
                    ->get('protocol')
                    ->map(static fn($protocol) => $protocol->toString())
                    ->map(static fn($protocol) => match ($protocol) {
                        '1.0' => ProtocolVersion::v10,
                        '1.1' => ProtocolVersion::v11,
                        default => null,
                    })
                    ->keep(Instance::of(ProtocolVersion::class));

                return Maybe::all($method, $url, $protocol)->map(
                    static fn(Method $method, Url $url, ProtocolVersion $protocol) => [
                        $method,
                        $url,
                        $protocol,
                    ],
                );
            });
    }
}
