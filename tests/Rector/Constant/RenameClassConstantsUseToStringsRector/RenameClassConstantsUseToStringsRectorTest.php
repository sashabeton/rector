<?php declare(strict_types=1);

namespace Rector\Tests\Rector\Constant\RenameClassConstantsUseToStringsRector;

use Nette\Configurator;
use Rector\Rector\Constant\RenameClassConstantsUseToStringsRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class RenameClassConstantsUseToStringsRectorTest extends AbstractRectorTestCase
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
            RenameClassConstantsUseToStringsRector::class => [
                '$oldConstantsToNewValuesByType' => [
                    Configurator::class => [
                        'DEVELOPMENT' => 'development',
                        'PRODUCTION' => 'production',
                    ],
                ],
            ],
        ];
    }
}
