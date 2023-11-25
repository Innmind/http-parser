# Changelog

## [Unreleased]

## Changed

- `Innmind\HttpParser\Request\Parse::of()` now expects an instance of `Innmind\Http\Factory\Header\TryFactory`
- `Innmind\HttpParser\Request\Parse::__invoke()` now expects an instance of `Innmind\IO\Readable\Stream`

## 1.1.0 - 2023-09-23

### Added

- Support for `innmind/immutable:~5.0`
- Support for `innmind/io:~2.0`

### Removed

- Support for PHP `8.1`
