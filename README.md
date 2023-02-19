# HTTP Parser

[![Build Status](https://github.com/innmind/http-parser/workflows/CI/badge.svg?branch=main)](https://github.com/innmind/http-parser/actions?query=workflow%3ACI)
[![codecov](https://codecov.io/gh/innmind/http-parser/branch/develop/graph/badge.svg)](https://codecov.io/gh/innmind/http-parser)
[![Type Coverage](https://shepherd.dev/github/innmind/http-parser/coverage.svg)](https://shepherd.dev/github/innmind/http-parser)

Set of classes to parse any sequence of strings into [`innmind/http`](https://packagist.org/packages/innmind/http) objects.

This is useful if you want to parse requests savec in files or build an http server.

## Installation

```sh
composer require innmind/http-parser
```

## Usage

```php
use Innmind\HttpParser\{
    Request\Parse,
    ServerRequest\Transform,
    ServerRequest\DecodeCookie,
    ServerRequest\DecodeQuery,
    ServerRequest\DecodeForm,
};
use Innmind\TimeContinuum\Earth\Clock;
use Innmind\IO\IO;
use Innmind\Stream\Streams;
use Innmind\Http\Message\ServerRequest;
use Innmind\Immutable\Str;

// this data could come from anywhere
$raw = <<<RAW
POST /some-form HTTP/1.1
Host: innmind.com
Content-Type: application/x-www-form-urlencoded
Content-Length: 23
Accept-Language: fr-fr
Cookie: PHPSESSID=298zf09hf012fh2; csrftoken=u32t4o3tb3gg43; _gat=1

some[key]=value&foo=bar

RAW;
$streams = Streams::fromAmbientAuthority();
$io = IO::of(static fn($timeout) => match ($timeout) {
    null => $streams->watch()->waitForever(),
    default => $streams->watch()->timeoutAfter($timeout),
});
$parse = Parse::of($streams, new Clock);

$stream = $streams
    ->temporary()
    ->new()
    ->write(Str::of($raw))
    ->flatMap(static fn($stream) => $stream->rewind())
    ->match(
        static fn($stream) => $stream,
        static fn() => throw new \RuntimeException('Stream not writable'),
    );
$chunks = $io
    ->readable()
    ->wrap($stream)
    ->watch()
    ->chunks(10); // chunk size doesn't matter and doesn't have to be the same for each chunk

$request = $parse($chunks)
    ->map(Transform::of())
    ->map(DecodeCookie::of())
    ->map(DecodeQuery::of())
    ->map(DecodeForm::of())
    ->match(
        static fn($request) => $request,
        static fn() => throw new \RuntimeException,
    );
$request instanceof ServerRequest, // true
```
