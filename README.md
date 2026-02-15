# HTTP Parser

[![CI](https://github.com/Innmind/http-parser/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/Innmind/http-parser/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/innmind/http-parser/branch/develop/graph/badge.svg)](https://codecov.io/gh/innmind/http-parser)
[![Type Coverage](https://shepherd.dev/github/innmind/http-parser/coverage.svg)](https://shepherd.dev/github/innmind/http-parser)

Set of classes to parse any stream into [`innmind/http`](https://packagist.org/packages/innmind/http) objects.

This is useful if you want to parse requests saved in files or build an http server.

Features supported:
- [x] Decoding requests
- [ ] Reading streamed requests with `Transfer-Encoding: chunked`
- [x] Extracting `Cookie`s
- [x] Extracting form data
- [x] Extracting query data
- [ ] Reading multipart bodies

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
use Innmind\Time\Clock;
use Innmind\IO\IO;
use Innmind\Http\ServerRequest;
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
$tmp = \fopen('php://temp', 'w+');
$io = IO::fromAmbientAuthority()
    ->streams()
    ->acquire($tmp);
$io
    ->write()
    ->sink(Sequence::of(Str::of($raw)))
    ->unwrap();
\fseek($tmp, 0);

$request = $parse($io->read())
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
