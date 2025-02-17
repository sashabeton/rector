<?php declare(strict_types=1);

namespace Rector\Tests\Rector\MagicDisclosure\UnsetAndIssetToMethodCallRector;

use Nette\DI\Container;
use Rector\Rector\MagicDisclosure\UnsetAndIssetToMethodCallRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class UnsetAndIssetToMethodCallRectorTest extends AbstractRectorTestCase
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
            UnsetAndIssetToMethodCallRector::class => [
                '$typeToMethodCalls' => [
                    Container::class => [
                        'isset' => 'hasService',
                        'unset' => 'removeService',
                    ],
                ],
            ],
        ];
    }
}
