<?php declare(strict_types=1);

namespace Rector\Php\Tests\Rector\FuncCall\StringifyStrNeedlesRector;

use Rector\Php\Rector\FuncCall\StringifyStrNeedlesRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class StringifyStrNeedlesRectorTest extends AbstractRectorTestCase
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
        yield [__DIR__ . '/Fixture/trait.php.inc'];
        yield [__DIR__ . '/Fixture/skip_twice.php.inc'];
    }

    protected function getRectorClass(): string
    {
        return StringifyStrNeedlesRector::class;
    }
}
