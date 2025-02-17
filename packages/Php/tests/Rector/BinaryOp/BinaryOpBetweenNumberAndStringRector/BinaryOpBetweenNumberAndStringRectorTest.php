<?php declare(strict_types=1);

namespace Rector\Php\Tests\Rector\BinaryOp\BinaryOpBetweenNumberAndStringRector;

use Rector\Php\Rector\BinaryOp\BinaryOpBetweenNumberAndStringRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class BinaryOpBetweenNumberAndStringRectorTest extends AbstractRectorTestCase
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
        yield [__DIR__ . '/Fixture/ignore_concatenation_dot.php.inc'];
    }

    protected function getRectorClass(): string
    {
        return BinaryOpBetweenNumberAndStringRector::class;
    }
}
