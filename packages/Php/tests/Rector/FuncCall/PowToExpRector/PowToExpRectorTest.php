<?php declare(strict_types=1);

namespace Rector\Php\Tests\Rector\FuncCall\PowToExpRector;

use Rector\Php\Rector\FuncCall\PowToExpRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * Some tests copied from:
 * - https://github.com/FriendsOfPHP/PHP-CS-Fixer/commit/14660432d9d0b66bf65135d793b52872cc6eccbc#diff-b412676c923661ef450f4a0903c5442a
 */
final class PowToExpRectorTest extends AbstractRectorTestCase
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

    protected function getRectorClass(): string
    {
        return PowToExpRector::class;
    }
}
