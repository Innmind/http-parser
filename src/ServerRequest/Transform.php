<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\ServerRequest;

use Innmind\Http\{
    Message\Request,
    Message\ServerRequest,
    Header,
};
use Innmind\Url\{
    Authority\Host,
    Authority\UserInformation,
    Authority\UserInformation\User,
    Authority\UserInformation\Password,
};
use Innmind\Immutable\Str;

final class Transform
{
    private function __construct()
    {
    }

    public function __invoke(Request $request): ServerRequest
    {
        $url = $request->url();
        $headers = $request->headers();

        // add the host and port from the header in the url so it can be accessed
        // both ways
        if ($url->authority()->host()->equals(Host::none())) {
            $authority = $url->authority();
            $authority = $headers
                ->find(Header\Host::class)
                ->match(
                    static fn($header) => $authority
                        ->withHost($header->host())
                        ->withPort($header->port()),
                    static fn() => $authority,
                );
            $url = $url->withAuthority($authority);
        }

        if ($url->authority()->userInformation()->equals(UserInformation::none())) {
            $authority = $url->authority();
            $authority = $headers
                ->find(Header\Authorization::class)
                ->filter(static fn($authorization) => $authorization->scheme() === 'Basic')
                ->map(static fn($authorization) => \base64_decode($authorization->parameter(), true) ?: '')
                ->map(Str::of(...))
                ->map(static fn($basic) => $basic->split(':'))
                ->filter(static fn($basic) => $basic->size() === 2)
                ->map(static function($basic) {
                    /** @psalm-suppress PossiblyUndefinedArrayOffset size checked above */
                    [$user, $password] = $basic->toList();

                    return UserInformation::of(
                        User::of($user->toString()),
                        Password::of($password->toString()),
                    );
                })
                ->match(
                    static fn($userInformation) => $authority->withUserInformation($userInformation),
                    static fn() => $authority,
                );
            $url = $url->withAuthority($authority);
        }

        $query = null;

        return new ServerRequest\ServerRequest(
            $url,
            $request->method(),
            $request->protocolVersion(),
            $headers,
            $request->body(),
        );
    }

    public static function of(): self
    {
        return new self;
    }
}
