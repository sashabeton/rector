<?php declare(strict_types=1);

namespace Rector\CodingStyle\Tests\Rector\FuncCall\SimpleArrayCallableToStringRector;

use Rector\CodingStyle\Rector\FuncCall\SimpleArrayCallableToStringRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class SimpleArrayCallableToStringRectorTest extends AbstractRectorTestCase
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
    }

    protected function getRectorClass(): string
    {
        return SimpleArrayCallableToStringRector::class;
    }
}
