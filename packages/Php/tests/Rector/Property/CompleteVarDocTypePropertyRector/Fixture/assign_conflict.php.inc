<?php

namespace Rector\Php\Tests\Rector\Property\CompleteVarDocTypePropertyRector\Fixture;

class Robocop
{

}

final class AssignConflict
{
    private $eventDispatcher;

    public function __construct(\EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function run(Robocop $stdClass)
    {
        $this->eventDispatcher = $stdClass;
    }
}

?>
-----
<?php

namespace Rector\Php\Tests\Rector\Property\CompleteVarDocTypePropertyRector\Fixture;

class Robocop
{

}

final class AssignConflict
{
    /**
     * @var \EventDispatcher|\Rector\Php\Tests\Rector\Property\CompleteVarDocTypePropertyRector\Fixture\Robocop
     */
    private $eventDispatcher;

    public function __construct(\EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function run(Robocop $stdClass)
    {
        $this->eventDispatcher = $stdClass;
    }
}

?>
