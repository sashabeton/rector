<?php

namespace Rector\Php\Tests\Rector\Property\TypedPropertyRector\Fixture;

class PropperParent
{

}

final class MatchTypesParent extends PropperParent
{
    /**
     * @var parent
     */
    private $j;
}

?>
-----
<?php

namespace Rector\Php\Tests\Rector\Property\TypedPropertyRector\Fixture;

class PropperParent
{

}

final class MatchTypesParent extends PropperParent
{
    /**
     * @var parent
     */
    private parent $j;
}

?>
