<?php declare(strict_types=1);

namespace Rector\Php\Tests\Rector\Property\VarToPublicPropertyRector;

use Rector\Php\Rector\Property\VarToPublicPropertyRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class VarToPublicPropertyRectorTest extends AbstractRectorTestCase
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
        return VarToPublicPropertyRector::class;
    }
}
