<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\ServerRequest;

use Innmind\Http\{
    Message\Request,
    Message\ServerRequest,
    Message,
    Header,
};
use Innmind\Url\{
    Authority\Host,
    Authority\UserInformation,
    Authority\UserInformation\User,
    Authority\UserInformation\Password,
    Query,
};
use Innmind\Immutable\{
    Str,
    Predicate\Instance,
};

final class Transform
{
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
                ->flatMap(static fn($header) => $header->values()->find(static fn() => true))
                ->keep(Instance::of(Header\HostValue::class))
                ->match(
                    static fn($value) => $authority
                        ->withHost($value->host())
                        ->withPort($value->port()),
                    static fn() => $authority,
                );
            $url = $url->withAuthority($authority);
        }

        if ($url->authority()->userInformation()->equals(UserInformation::none())) {
            $authority = $url->authority();
            $authority = $headers
                ->find(Header\Authorization::class)
                ->flatMap(static fn($header) => $header->values()->find(static fn() => true))
                ->keep(Instance::of(Header\AuthorizationValue::class))
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

        if (!$url->query()->equals(Query::none())) {
            \parse_str($url->query()->toString(), $query);
            $query = Message\Query::of($query);
        }

        return new ServerRequest\ServerRequest(
            $url,
            $request->method(),
            $request->protocolVersion(),
            $headers,
            $request->body(),
            null,
            null,
            $query,
        );
    }
}