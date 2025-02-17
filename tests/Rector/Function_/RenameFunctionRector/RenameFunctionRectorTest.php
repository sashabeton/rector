<?php declare(strict_types=1);

namespace Rector\Tests\Rector\Function_\RenameFunctionRector;

use Rector\Rector\Function_\RenameFunctionRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class RenameFunctionRectorTest extends AbstractRectorTestCase
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
        yield [__DIR__ . '/Fixture/fixture2.php.inc'];
        yield [__DIR__ . '/Fixture/double_function.php.inc'];
    }

    /**
     * @return mixed[]
     */
    protected function getRectorsWithConfiguration(): array
    {
        return [
            RenameFunctionRector::class => [
                '$oldFunctionToNewFunction' => [
                    'view' => 'Laravel\Templating\render',
                    'sprintf' => 'Safe\sprintf',
                    'hebrevc' => ['nl2br', 'hebrev'],
                ],
            ],
        ];
    }
}
