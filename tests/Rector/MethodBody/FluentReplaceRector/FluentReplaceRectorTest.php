<?php declare(strict_types=1);

namespace Rector\Tests\Rector\MethodBody\FluentReplaceRector;

use Rector\Rector\MethodBody\FluentReplaceRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use Rector\Tests\Rector\MethodBody\FluentReplaceRector\Source\FluentInterfaceClass;

final class FluentReplaceRectorTest extends AbstractRectorTestCase
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
        yield [__DIR__ . '/Fixture/some_command.php.inc'];
        yield [__DIR__ . '/Fixture/multiple_some_command.php.inc'];
        yield [__DIR__ . '/Fixture/skip_date_time_modify_command.php.inc'];
    }

    /**
     * @return mixed[]
     */
    protected function getRectorsWithConfiguration(): array
    {
        return [
            FluentReplaceRector::class => [
                '$classesToDefluent' => [FluentInterfaceClass::class, '*Command'],
            ],
        ];
    }
}
