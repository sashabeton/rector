<?php declare(strict_types=1);

namespace Rector\CakePHP\Tests\Rector\Name\ChangeSnakedFixtureNameToCamel;

use Rector\CakePHP\Rector\Name\ChangeSnakedFixtureNameToCamelRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class ChangeSnakedFixtureNameToCamelTest extends AbstractRectorTestCase
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
        return ChangeSnakedFixtureNameToCamelRector::class;
    }
}
