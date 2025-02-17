<?php declare(strict_types=1);

namespace Rector\ZendToSymfony\Tests\Rector\MethodCall\ThisHelperToServiceMethodCallRector;

use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use Rector\ZendToSymfony\Rector\MethodCall\ThisHelperToServiceMethodCallRector;

final class ThisHelperToServiceMethodCallRectorTest extends AbstractRectorTestCase
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
        yield [__DIR__ . '/Fixture/this_helper_to_service.php.inc'];
    }

    protected function getRectorClass(): string
    {
        return ThisHelperToServiceMethodCallRector::class;
    }
}
