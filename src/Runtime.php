<?php declare(strict_types=1);

namespace WyriHaximus\React\Parallel;

use Closure;
use parallel\Future;
use parallel\Runtime as ParallelRuntime;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

final class Runtime
{
    /** @var string */
    private $id;

    /** @var ParallelRuntime */
    private $runtime;

    /** @var FutureToPromiseConverter */
    private $futureToPromiseConverter;

    public function __construct(FutureToPromiseConverter $futureToPromiseConverter, string $autoload)
    {
        $this->runtime = new ParallelRuntime($autoload);
        $this->id = \spl_object_hash($this->runtime);
        $this->futureToPromiseConverter = $futureToPromiseConverter;
    }

    /**
     * @param  Closure           $callable
     * @param  array<int, mixed> $args
     * @return PromiseInterface
     */
    public function run(Closure $callable, ...$args): PromiseInterface
    {
        $future = $this->runtime->run($callable, $args);

        if ($future instanceof Future) {
            return $this->futureToPromiseConverter->convert($future);
        }

        return resolve($future);
    }

    public function close(): void
    {
        $this->runtime->close();
    }

    public function kill(): void
    {
        $this->runtime->kill();
    }
}
