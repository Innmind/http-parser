<?php
declare(strict_types = 1);

namespace Innmind\HttpParser\Request;

use Innmind\TimeContinuum\Clock;
use Innmind\Http\Message\Request;
use Innmind\IO\Readable\Chunks;
use Innmind\Immutable\{
    Maybe,
    Fold,
};

final class Parse2
{
    private Clock $clock;

    public function __construct(Clock $clock)
    {
        $this->clock = $clock;
    }

    /**
     * @return Maybe<Request>
     */
    public function __invoke(Chunks $chunks): Maybe
    {
        /** @var Fold<null, Request, Buffer2> */
        $fold = Fold::with(Buffer2::new($this->clock));

        return $chunks
            ->fold(
                $fold,
                static fn(Buffer2 $buffer, $chunk) => $buffer($chunk),
            )
            ->flatMap(static fn($result) => $result->maybe());
    }
}
