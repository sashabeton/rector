<?php

namespace Rector\Php\Tests\Rector\Property\TypedPropertyRector\Fixture;

final class ClassWithProperty
{
    /**
     * @var int
     */
    private $count;

    /**
     * @var int|null|bool
     */
    private $multiCount;

    /**
     * @var void
     */
    private $shouldBeSkipped;

    /**
     * @var callable
     */
    private $shouldBeSkippedToo;
}

?>
-----
<?php

namespace Rector\Php\Tests\Rector\Property\TypedPropertyRector\Fixture;

final class ClassWithProperty
{
    /**
     * @var int
     */
    private int $count;

    /**
     * @var int|null|bool
     */
    private $multiCount;

    /**
     * @var void
     */
    private $shouldBeSkipped;

    /**
     * @var callable
     */
    private $shouldBeSkippedToo;
}

?>
