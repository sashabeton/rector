<?php declare(strict_types=1);

namespace Rector\Php\Tests\Rector\FuncCall\RemoveExtraParametersRector;

use Rector\Php\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class RemoveExtraParametersRectorTest extends AbstractRectorTestCase
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
        yield [__DIR__ . '/Fixture/better_func_get_all.php.inc'];
        yield [__DIR__ . '/Fixture/remove_another_class_method_call_extra_argument.php.inc'];
        yield [__DIR__ . '/Fixture/methods.php.inc'];
        yield [__DIR__ . '/Fixture/static_calls.php.inc'];
        yield [__DIR__ . '/Fixture/fixture.php.inc'];
        yield [__DIR__ . '/Fixture/func_get_all.php.inc'];
        yield [__DIR__ . '/Fixture/external_scope.php.inc'];
        yield [__DIR__ . '/Fixture/static_call_parent.php.inc'];
        yield [__DIR__ . '/Fixture/skip_commented_param_func_get_args.php.inc'];
        yield [__DIR__ . '/Fixture/skip_call_user_func_array.php.inc'];
        yield [__DIR__ . '/Fixture/skip_invoke.php.inc'];
    }

    protected function getRectorClass(): string
    {
        return RemoveExtraParametersRector::class;
    }
}
