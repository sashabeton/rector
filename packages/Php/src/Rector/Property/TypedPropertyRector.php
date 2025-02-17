<?php declare(strict_types=1);

namespace Rector\Php\Rector\Property;

use PhpParser\Node;
use PhpParser\Node\Stmt\Property;
use PHPStan\Type\MixedType;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;
use Rector\TypeDeclaration\TypeInferer\PropertyTypeInferer;

/**
 * @source https://wiki.php.net/rfc/typed_properties_v2#proposal
 *
 * @see \Rector\Php\Tests\Rector\Property\TypedPropertyRector\TypedPropertyRectorTest
 */
final class TypedPropertyRector extends AbstractRector
{
    /**
     * @var PropertyTypeInferer
     */
    private $propertyTypeInferer;

    public function __construct(PropertyTypeInferer $propertyTypeInferer)
    {
        $this->propertyTypeInferer = $propertyTypeInferer;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Changes property `@var` annotations from annotation to type.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
final class SomeClass 
{
    /** 
     * @var int 
     */
    private count; 
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
final class SomeClass 
{
    private int count; 
}
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Property::class];
    }

    /**
     * @param Property $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isAtLeastPhpVersion('7.4')) {
            return null;
        }

        // type is already set → skip
        if ($node->type !== null) {
            return null;
        }

        $varType = $this->propertyTypeInferer->inferProperty($node);
        if ($varType instanceof MixedType) {
            return null;
        }

        $propertyTypeNode = $this->staticTypeMapper->mapPHPStanTypeToPhpParserNode($varType, 'property');
        if ($propertyTypeNode === null) {
            return null;
        }

        $node->type = $propertyTypeNode;

        return $node;
    }
}
