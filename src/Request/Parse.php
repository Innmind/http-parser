<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request;

use Innmind\TimeContinuum\Clock;
use Innmind\Stream\Capabilities;
use Innmind\Http\Message\Request;
use Innmind\IO\Readable\Chunks;
use Innmind\Immutable\{
    Maybe,
    Fold,
};

final class Parse
{
    private Capabilities $capabilities;
    private Clock $clock;

    public function __construct(
        Capabilities $capabilities,
        Clock $clock,
    ) {
        $this->capabilities = $capabilities;
        $this->clock = $clock;
    }

    /**
     * @return Maybe<Request>
     */
    public function __invoke(Chunks $chunks): Maybe
    {
        /** @var Fold<null, Request, Buffer> */
        $fold = Fold::with(Buffer::new($this->capabilities, $this->clock));

        return $chunks
            ->fold(
                $fold,
                static fn(Buffer $buffer, $chunk) => $buffer($chunk),
            )
            ->flatMap(static fn($result) => $result->maybe());
    }
}
