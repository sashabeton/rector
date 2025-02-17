<?php

namespace Rector\Php\Tests\Rector\Property\TypedPropertyRector\Fixture;

final class DefaultValues
{
    /**
     * @var bool
     */
    private $name = 'not_a_bool';

    /**
     * @var bool
     */
    private $isItRealName = false;

    /**
     * @var bool
     */
    private $isItRealNameNull = null;

    /**
     * @var string
     */
    private $size = false;

    /**
     * @var float
     */
    private $a = 42.42;

    /**
     * @var float
     */
    private $b = 42;

    /**
     * @var float
     */
    private $c = 'hey';

    /**
     * @var int
     */
    private $e = 42.42;

    /**
     * @var int
     */
    private $f = 42;

    /**
     * @var array
     */
    private $g = [1, 2, 3];

    /**
     * @var iterable
     */
    private $h = [1, 2, 3];
}

?>
-----
<?php

namespace Rector\Php\Tests\Rector\Property\TypedPropertyRector\Fixture;

final class DefaultValues
{
    /**
     * @var bool
     */
    private string $name = 'not_a_bool';

    /**
     * @var bool
     */
    private bool $isItRealName = false;

    /**
     * @var bool
     */
    private ?bool $isItRealNameNull = null;

    /**
     * @var string
     */
    private bool $size = false;

    /**
     * @var float
     */
    private float $a = 42.42;

    /**
     * @var float
     */
    private int $b = 42;

    /**
     * @var float
     */
    private string $c = 'hey';

    /**
     * @var int
     */
    private float $e = 42.42;

    /**
     * @var int
     */
    private int $f = 42;

    /**
     * @var array
     */
    private array $g = [1, 2, 3];

    /**
     * @var iterable
     */
    private array $h = [1, 2, 3];
}

?>
