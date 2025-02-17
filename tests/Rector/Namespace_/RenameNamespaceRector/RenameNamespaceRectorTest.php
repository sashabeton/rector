<?php declare(strict_types=1);

namespace Rector\Tests\Rector\Namespace_\RenameNamespaceRector;

use Rector\Rector\Namespace_\RenameNamespaceRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class RenameNamespaceRectorTest extends AbstractRectorTestCase
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
        yield [__DIR__ . '/Fixture/fixture3.php.inc'];
        yield [__DIR__ . '/Fixture/fixture4.php.inc'];
        yield [__DIR__ . '/Fixture/fixture5.php.inc'];
    }

    /**
     * @return mixed[]
     */
    protected function getRectorsWithConfiguration(): array
    {
        return [
            RenameNamespaceRector::class => [
                '$oldToNewNamespaces' => [
                    'OldNamespace' => 'NewNamespace',
                    'OldNamespaceWith\OldSplitNamespace' => 'NewNamespaceWith\NewSplitNamespace',
                    'Old\Long\AnyNamespace' => 'Short\AnyNamespace',
                    'PHPUnit_Framework_' => 'PHPUnit\Framework\\',
                ],
            ],
        ];
    }
}
