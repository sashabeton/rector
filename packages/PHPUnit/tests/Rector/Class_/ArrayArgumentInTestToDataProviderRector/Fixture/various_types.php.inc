<?php

namespace Rector\PHPUnit\Tests\Rector\Class_\ArrayArgumentInTestToDataProviderRector\Fixture;

class VariousTypesTest extends \PHPUnit\Framework\TestCase
{
    public function test()
    {
        $this->doTestMultiple([1, '2', 3.5]);
    }
}

?>
-----
<?php

namespace Rector\PHPUnit\Tests\Rector\Class_\ArrayArgumentInTestToDataProviderRector\Fixture;

class VariousTypesTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @param int|string|float $variable
     * @dataProvider provideDataForTest()
     */
    public function test($variable)
    {
        $this->doTestSingle($variable);
    }
    /**
     * @return int[]|string[]|float[]
     */
    public function provideDataForTest(): iterable
    {
        yield [1];
        yield ['2'];
        yield [3.5];
    }
}

?>
