<?php declare(strict_types=1);

namespace Rector\Tests\Rector\MagicDisclosure\ToStringToMethodCallRector;

use Rector\Rector\MagicDisclosure\ToStringToMethodCallRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use Symfony\Component\Config\ConfigCache;

final class ToStringToMethodCallRectorTest extends AbstractRectorTestCase
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
            ToStringToMethodCallRector::class => [
                '$methodNamesByType' => [
                    ConfigCache::class => 'getPath',
                ],
            ],
        ];
    }
}
