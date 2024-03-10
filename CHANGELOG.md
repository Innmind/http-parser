# Changelog

## [Unreleased]

### Added

- Allow to parse data coming from a `Innmind\IO\Sockets\Client`

### Changed

- Requires `innmind/io:~2.7`

## 2.0.0 - 2023-11-25

## Changed

- Requires `innmind/immutable:~5.2`
- Requires `innmind/http:~7.0`
- `Innmind\HttpParser\Request\Parse::of()` now expects an instance of `Innmind\Http\Factory\Header\TryFactory`
- `Innmind\HttpParser\Request\Parse::__invoke()` now expects an instance of `Innmind\IO\Readable\Stream`

### Removed

- Support for `innmind/io:~1.0`

## 1.1.0 - 2023-09-23

### Added

- Support for `innmind/immutable:~5.0`
- Support for `innmind/io:~2.0`

### Removed

- Support for PHP `8.1`
