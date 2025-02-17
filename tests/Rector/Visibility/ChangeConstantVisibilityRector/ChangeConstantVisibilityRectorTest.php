<?php declare(strict_types=1);

namespace Rector\Tests\Rector\Visibility\ChangeConstantVisibilityRector;

use Rector\Rector\Visibility\ChangeConstantVisibilityRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use Rector\Tests\Rector\Visibility\ChangeConstantVisibilityRector\Source\ParentObject;

final class ChangeConstantVisibilityRectorTest extends AbstractRectorTestCase
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
    }

    /**
     * @return mixed[]
     */
    protected function getRectorsWithConfiguration(): array
    {
        return [
            ChangeConstantVisibilityRector::class => [
                '$constantToVisibilityByClass' => [
                    ParentObject::class => [
                        'TO_BE_PUBLIC_CONSTANT' => 'public',
                        'TO_BE_PROTECTED_CONSTANT' => 'protected',
                        'TO_BE_PRIVATE_CONSTANT' => 'private',
                    ],
                    'Rector\Tests\Rector\Visibility\ChangePropertyVisibilityRector\Source\AnotherClassWithInvalidConstants' => [
                        'TO_BE_PRIVATE_CONSTANT' => 'private',
                    ],
                ],
            ],
        ];
    }
}
