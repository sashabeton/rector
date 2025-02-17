<?php declare(strict_types=1);

namespace Rector\BetterPhpDocParser\Ast\PhpDoc\PHPDI;

use DI\Annotation\Inject;
use Rector\BetterPhpDocParser\PhpDocParser\Ast\PhpDoc\AbstractTagValueNode;

final class PHPDIInjectTagValueNode extends AbstractTagValueNode
{
    /**
     * @var string
     */
    public const SHORT_NAME = '@Inject';

    /**
     * @var string
     */
    public const CLASS_NAME = Inject::class;

    /**
     * @var ?string
     */
    private $value;

    public function __construct(?string $value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        if ($this->value === null) {
            return '';
        }

        return '(' . $this->value . ')';
    }
}
