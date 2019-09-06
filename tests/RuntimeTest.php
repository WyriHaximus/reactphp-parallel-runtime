<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Parallel;

use React\EventLoop\Factory;
use React\Promise\ExtendedPromiseInterface;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Parallel\FutureToPromiseConverter;
use WyriHaximus\React\Parallel\Runtime;
use parallel\Runtime\Error\Closed;
use function Safe\sleep;
use function WyriHaximus\React\timedPromise;

/**
 * @internal
 */
final class RuntimeTest extends AsyncTestCase
{
    public function testConvertSuccess(): void
    {
        $loop = Factory::create();
        $runtime = new Runtime(new FutureToPromiseConverter($loop), \dirname(__DIR__) . '/vendor/autoload.php');

        /** @var ExtendedPromiseInterface $promise */
        $promise = $runtime->run(function () {
            sleep(3);

            return 3;
        });

        $promise->always(function () use ($runtime): void {
            $runtime->close();
        });

        $loop->run();
        $three = $this->await($promise, $loop, 3.3);

        self::assertSame(3, $three);
    }

    public function testConvertFailure(): void
    {
        self::expectException(\Exception::class);
        self::expectExceptionMessage('Rethrow exception');

        $loop = Factory::create();
        $runtime = new Runtime(new FutureToPromiseConverter($loop), \dirname(__DIR__) . '/vendor/autoload.php');

        /** @var ExtendedPromiseInterface $promise */
        $promise = $runtime->run(function (): void {
            sleep(3);

            throw new \Exception('Rethrow exception');
        });

        $promise->always(function () use ($runtime): void {
            $runtime->close();
        });

        $loop->run();
        $three = $this->await($promise, $loop, 3.3);

        self::assertSame(3, $three);
    }

    public function testWeClosedTheThread(): void
    {
        self::expectException(Closed::class);
        self::expectExceptionMessage('Runtime closed');

        $loop = Factory::create();
        $runtime = new Runtime(new FutureToPromiseConverter($loop), \dirname(__DIR__) . '/vendor/autoload.php');

        /** @var ExtendedPromiseInterface $promise */
        $promise = timedPromise($loop, 1, $runtime)->then(function (Runtime $runtime) {
            return $runtime->run(function (): int {
                return 3;
            });
        });

        $loop->futureTick(function () use ($runtime): void {
            $runtime->close();
        });

        try {
            $this->await($promise, $loop, 3.3);
        } catch (\Exception $exception) {
            throw $exception->getPrevious();
        }
    }

    public function testWeKilledTheThread(): void
    {
        self::expectException(Closed::class);
        self::expectExceptionMessage('Runtime closed');

        $loop = Factory::create();
        $runtime = new Runtime(new FutureToPromiseConverter($loop), \dirname(__DIR__) . '/vendor/autoload.php');

        /** @var ExtendedPromiseInterface $promise */
        $promise = timedPromise($loop, 1, $runtime)->then(function (Runtime $runtime) {
            return $runtime->run(function (): int {
                return 3;
            });
        });

        $loop->futureTick(function () use ($runtime): void {
            $runtime->kill();
        });

        try {
            $this->await($promise, $loop, 3.3);
        } catch (\Exception $exception) {
            throw $exception->getPrevious();
        }
    }
}
