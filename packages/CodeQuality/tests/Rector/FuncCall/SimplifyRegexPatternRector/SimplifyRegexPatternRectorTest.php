<?php declare(strict_types=1);

namespace Rector\CodeQuality\Tests\Rector\FuncCall\SimplifyRegexPatternRector;

use Rector\CodeQuality\Rector\FuncCall\SimplifyRegexPatternRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class SimplifyRegexPatternRectorTest extends AbstractRectorTestCase
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
        yield [__DIR__ . '/Fixture/pattern_in_constant.php.inc'];
    }

    protected function getRectorClass(): string
    {
        return SimplifyRegexPatternRector::class;
    }
}
