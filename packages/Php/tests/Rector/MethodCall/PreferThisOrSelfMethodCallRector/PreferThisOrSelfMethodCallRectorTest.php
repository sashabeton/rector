<?php declare(strict_types=1);

namespace Rector\Php\Tests\Rector\MethodCall\PreferThisOrSelfMethodCallRector;

use Rector\Php\Rector\MethodCall\PreferThisOrSelfMethodCallRector;
use Rector\Php\Tests\Rector\MethodCall\PreferThisOrSelfMethodCallRector\Source\AbstractTestCase;
use Rector\Php\Tests\Rector\MethodCall\PreferThisOrSelfMethodCallRector\Source\BeLocalClass;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class PreferThisOrSelfMethodCallRectorTest extends AbstractRectorTestCase
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
        yield [__DIR__ . '/Fixture/to_self.php.inc'];
        yield [__DIR__ . '/Fixture/to_this.php.inc'];
    }

    /**
     * @return mixed[]
     */
    protected function getRectorsWithConfiguration(): array
    {
        return [
            PreferThisOrSelfMethodCallRector::class => [
                '$typeToPreference' => [
                    AbstractTestCase::class => 'self',
                    BeLocalClass::class => 'this',
                ],
            ],
        ];
    }
}
