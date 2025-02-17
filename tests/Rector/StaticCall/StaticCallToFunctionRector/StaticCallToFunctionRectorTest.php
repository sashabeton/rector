<?php declare(strict_types=1);

namespace Rector\Tests\Rector\StaticCall\StaticCallToFunctionRector;

use Rector\Rector\StaticCall\StaticCallToFunctionRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use Rector\Tests\Rector\StaticCall\StaticCallToFunctionRector\Source\SomeOldStaticClass;

final class StaticCallToFunctionRectorTest extends AbstractRectorTestCase
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

    /**
     * @return mixed[]
     */
    protected function getRectorsWithConfiguration(): array
    {
        return [
            StaticCallToFunctionRector::class => [
                '$staticCallToFunctionByType' => [
                    SomeOldStaticClass::class => [
                        'render' => 'view',
                    ],
                ],
            ],
        ];
    }
}
