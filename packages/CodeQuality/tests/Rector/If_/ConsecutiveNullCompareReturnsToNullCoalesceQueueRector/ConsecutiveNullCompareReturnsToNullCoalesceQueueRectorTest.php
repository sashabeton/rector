<?php declare(strict_types=1);

namespace Rector\CodeQuality\Tests\Rector\If_\ConsecutiveNullCompareReturnsToNullCoalesceQueueRector;

use Rector\CodeQuality\Rector\If_\ConsecutiveNullCompareReturnsToNullCoalesceQueueRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class ConsecutiveNullCompareReturnsToNullCoalesceQueueRectorTest extends AbstractRectorTestCase
{
    /**
     * @dataProvider provideDataForTest()
     */
    public function test(string $file): void
    {
        $this->doTestFile($file);
    }

    /**
     * @return string[]
     */
    public function provideDataForTest(): iterable
    {
        yield [__DIR__ . '/Fixture/fixture.php.inc'];
        yield [__DIR__ . '/Fixture/triplets.php.inc'];
    }

    protected function getRectorClass(): string
    {
        return ConsecutiveNullCompareReturnsToNullCoalesceQueueRector::class;
    }
}
