<?php declare(strict_types=1);

namespace Rector\BetterPhpDocParser\PhpDocInfo;

use Nette\Utils\Strings;
use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use Rector\BetterPhpDocParser\Annotation\AnnotationNaming;
use Rector\BetterPhpDocParser\Attributes\Ast\PhpDoc\AttributeAwareParamTagValueNode;
use Rector\BetterPhpDocParser\Attributes\Ast\PhpDoc\AttributeAwarePhpDocNode;
use Rector\BetterPhpDocParser\Attributes\Ast\PhpDoc\AttributeAwareReturnTagValueNode;
use Rector\BetterPhpDocParser\Attributes\Ast\PhpDoc\AttributeAwareVarTagValueNode;
use Rector\BetterPhpDocParser\Attributes\Contract\Ast\AttributeAwareNodeInterface;
use Rector\Exception\ShouldNotHappenException;
use Rector\NodeTypeResolver\StaticTypeMapper;

/**
 * @see \Rector\BetterPhpDocParser\Tests\PhpDocInfo\PhpDocInfo\PhpDocInfoTest
 */
final class PhpDocInfo
{
    /**
     * @var string
     */
    private $originalContent;

    /**
     * @var mixed[]
     */
    private $tokens = [];

    /**
     * @var AttributeAwarePhpDocNode
     */
    private $phpDocNode;

    /**
     * @var AttributeAwarePhpDocNode
     */
    private $originalPhpDocNode;

    /**
     * @var StaticTypeMapper
     */
    private $staticTypeMapper;

    /**
     * @var Node
     */
    private $node;

    /**
     * @param mixed[] $tokens
     */
    public function __construct(
        AttributeAwarePhpDocNode $attributeAwarePhpDocNode,
        array $tokens,
        string $originalContent,
        StaticTypeMapper $staticTypeMapper,
        Node $node
    ) {
        $this->phpDocNode = $attributeAwarePhpDocNode;
        $this->tokens = $tokens;
        $this->originalPhpDocNode = clone $attributeAwarePhpDocNode;
        $this->originalContent = $originalContent;
        $this->staticTypeMapper = $staticTypeMapper;
        $this->node = $node;
    }

    public function getOriginalContent(): string
    {
        return $this->originalContent;
    }

    public function getPhpDocNode(): AttributeAwarePhpDocNode
    {
        return $this->phpDocNode;
    }

    public function getOriginalPhpDocNode(): AttributeAwarePhpDocNode
    {
        return $this->originalPhpDocNode;
    }

    /**
     * @return mixed[]
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    public function getVarTagValue(): ?AttributeAwareVarTagValueNode
    {
        return $this->getPhpDocNode()->getVarTagValues()[0] ?? null;
    }

    public function getReturnTagValue(): ?AttributeAwareReturnTagValueNode
    {
        return $this->getPhpDocNode()->getReturnTagValues()[0] ?? null;
    }

    /**
     * @return AttributeAwareParamTagValueNode[]
     */
    public function getParamTagValues(): array
    {
        return $this->getPhpDocNode()->getParamTagValues();
    }

    /**
     * @return PhpDocTagNode[]
     */
    public function getTagsByName(string $name): array
    {
        $name = AnnotationNaming::normalizeName($name);

        /** @var AttributeAwareNodeInterface[]|PhpDocTagNode[] $tags */
        $tags = $this->phpDocNode->getTags();

        $tags = array_filter($tags, function (PhpDocTagNode $tag) use ($name): bool {
            return $tag->name === $name;
        });

        return array_values($tags);
    }

    /**
     * @return AttributeAwareNodeInterface|TypeNode
     */
    public function getParamTypeNode(string $paramName): ?TypeNode
    {
        $paramName = '$' . ltrim($paramName, '$');

        foreach ($this->phpDocNode->getParamTagValues() as $paramTagsValue) {
            if ($paramTagsValue->parameterName === $paramName) {
                return $paramTagsValue->type;
            }
        }

        return null;
    }

    public function getParamType(string $name): Type
    {
        $paramTagValue = $this->getParamTagValueByName($name);
        if ($paramTagValue === null) {
            return new MixedType();
        }

        return $this->staticTypeMapper->mapPHPStanPhpDocTypeToPHPStanType($paramTagValue, $this->node);
    }

    public function getVarType(): Type
    {
        $varTagValue = $this->getVarTagValue();
        if ($varTagValue === null) {
            return new MixedType();
        }

        return $this->staticTypeMapper->mapPHPStanPhpDocTypeToPHPStanType($varTagValue, $this->node);
    }

    public function getReturnType(): Type
    {
        $returnTypeValueNode = $this->getReturnTagValue();
        if ($returnTypeValueNode === null) {
            return new MixedType();
        }

        return $this->staticTypeMapper->mapPHPStanPhpDocTypeToPHPStanType($returnTypeValueNode, $this->node);
    }

    public function removeTagValueNodeFromNode(PhpDocTagValueNode $phpDocTagValueNode): void
    {
        foreach ($this->phpDocNode->children as $key => $phpDocChildNode) {
            if ($phpDocChildNode instanceof PhpDocTagNode) {
                if ($phpDocChildNode->value !== $phpDocTagValueNode) {
                    continue;
                }

                unset($this->phpDocNode->children[$key]);
            }
        }
    }

    public function getByType(string $type): ?PhpDocTagValueNode
    {
        $this->ensureTypeIsTagValueNode($type, __METHOD__);

        foreach ($this->phpDocNode->children as $phpDocChildNode) {
            if ($phpDocChildNode instanceof PhpDocTagNode) {
                if (is_a($phpDocChildNode->value, $type, true)) {
                    return $phpDocChildNode->value;
                }
            }
        }

        return null;
    }

    private function getParamTagValueByName(string $name): ?AttributeAwareParamTagValueNode
    {
        $phpDocNode = $this->getPhpDocNode();

        /** @var AttributeAwareParamTagValueNode $paramTagValue */
        foreach ($phpDocNode->getParamTagValues() as $paramTagValue) {
            if (Strings::match($paramTagValue->parameterName, '#^(\$)?' . $name . '$#')) {
                return $paramTagValue;
            }
        }

        return null;
    }

    private function ensureTypeIsTagValueNode(string $type, string $location): void
    {
        if (is_a($type, PhpDocTagValueNode::class, true)) {
            return;
        }

        throw new ShouldNotHappenException(sprintf(
            'Type "%s" passed to "%s()" method must be child of "%s"',
            $type,
            $location,
            PhpDocTagValueNode::class
        ));
    }
}
