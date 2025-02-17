<?php declare(strict_types=1);

namespace Rector\NodeTypeResolver\PhpDoc\NodeAnalyzer;

use Nette\Utils\Strings;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\PhpDocParser\Ast\Node as PhpDocParserNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocChildNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Rector\BetterPhpDocParser\Annotation\AnnotationNaming;
use Rector\BetterPhpDocParser\Ast\PhpDocNodeTraverser;
use Rector\BetterPhpDocParser\Attributes\Ast\AttributeAwareNodeFactory;
use Rector\BetterPhpDocParser\Attributes\Ast\PhpDoc\AttributeAwarePhpDocNode;
use Rector\BetterPhpDocParser\Attributes\Ast\PhpDoc\AttributeAwarePhpDocTagNode;
use Rector\BetterPhpDocParser\Attributes\Ast\PhpDoc\AttributeAwareVarTagValueNode;
use Rector\BetterPhpDocParser\Attributes\Ast\PhpDoc\Type\AttributeAwareIdentifierTypeNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\BetterPhpDocParser\Printer\PhpDocInfoPrinter;
use Rector\DoctrinePhpDocParser\Contract\Ast\PhpDoc\DoctrineRelationTagValueNodeInterface;
use Rector\Exception\ShouldNotHappenException;
use Rector\NodeTypeResolver\Exception\MissingTagException;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\NodeTypeResolver\StaticTypeMapper;

/**
 * @see \Rector\NodeTypeResolver\Tests\PhpDoc\NodeAnalyzer\DocBlockManipulatorTest
 */
final class DocBlockManipulator
{
    /**
     * @var PhpDocInfoFactory
     */
    private $phpDocInfoFactory;

    /**
     * @var PhpDocInfoPrinter
     */
    private $phpDocInfoPrinter;

    /**
     * @var AttributeAwareNodeFactory
     */
    private $attributeAwareNodeFactory;

    /**
     * @var PhpDocNodeTraverser
     */
    private $phpDocNodeTraverser;

    /**
     * @var StaticTypeMapper
     */
    private $staticTypeMapper;

    /**
     * @var bool
     */
    private $hasPhpDocChanged = false;

    /**
     * @var DocBlockClassRenamer
     */
    private $docBlockClassRenamer;

    /**
     * @var DocBlockNameImporter
     */
    private $docBlockNameImporter;

    public function __construct(
        PhpDocInfoFactory $phpDocInfoFactory,
        PhpDocInfoPrinter $phpDocInfoPrinter,
        AttributeAwareNodeFactory $attributeAwareNodeFactory,
        PhpDocNodeTraverser $phpDocNodeTraverser,
        StaticTypeMapper $staticTypeMapper,
        DocBlockClassRenamer $docBlockClassRenamer,
        DocBlockNameImporter $docBlockNameImporter
    ) {
        $this->phpDocInfoFactory = $phpDocInfoFactory;
        $this->phpDocInfoPrinter = $phpDocInfoPrinter;
        $this->attributeAwareNodeFactory = $attributeAwareNodeFactory;
        $this->phpDocNodeTraverser = $phpDocNodeTraverser;
        $this->staticTypeMapper = $staticTypeMapper;
        $this->docBlockClassRenamer = $docBlockClassRenamer;
        $this->docBlockNameImporter = $docBlockNameImporter;
    }

    public function hasTag(Node $node, string $name): bool
    {
        if ($node->getDocComment() === null) {
            return false;
        }

        // simple check
        $pattern = '#@(\\\\)?' . preg_quote(ltrim($name, '@'), '#') . '#';
        if (Strings::match($node->getDocComment()->getText(), $pattern)) {
            return true;
        }

        // allow only class nodes further
        if (! class_exists($name)) {
            return false;
        }

        // advanced check, e.g. for "Namespaced\Annotations\DI"
        $phpDocInfo = $this->createPhpDocInfoFromNode($node);

        return (bool) $phpDocInfo->getByType($name);
    }

    public function addTag(Node $node, PhpDocChildNode $phpDocChildNode): void
    {
        $phpDocChildNode = $this->attributeAwareNodeFactory->createFromNode($phpDocChildNode);

        if ($node->getDocComment() !== null) {
            $phpDocInfo = $this->createPhpDocInfoFromNode($node);
            $phpDocNode = $phpDocInfo->getPhpDocNode();
            $phpDocNode->children[] = $phpDocChildNode;
            $this->updateNodeWithPhpDocInfo($node, $phpDocInfo);
        } else {
            $phpDocNode = new AttributeAwarePhpDocNode([$phpDocChildNode]);
            $node->setDocComment(new Doc($phpDocNode->__toString()));
        }
    }

    public function removeTagFromNode(Node $node, string $name, bool $shouldSkipEmptyLinesAbove = false): void
    {
        if ($node->getDocComment() === null) {
            return;
        }

        $phpDocInfo = $this->createPhpDocInfoFromNode($node);

        $this->removeTagByName($phpDocInfo, $name);
        $this->updateNodeWithPhpDocInfo($node, $phpDocInfo, $shouldSkipEmptyLinesAbove);
    }

    public function changeType(Node $node, Type $oldType, Type $newType): void
    {
        if (! $this->hasNodeTypeTags($node)) {
            return;
        }

        $phpDocInfo = $this->createPhpDocInfoFromNode($node);
        $hasNodeChanged = $this->docBlockClassRenamer->renamePhpDocType(
            $phpDocInfo->getPhpDocNode(),
            $oldType,
            $newType,
            $node
        );

        if ($hasNodeChanged) {
            $this->updateNodeWithPhpDocInfo($node, $phpDocInfo);
        }
    }

    public function replaceAnnotationInNode(Node $node, string $oldAnnotation, string $newAnnotation): void
    {
        if ($node->getDocComment() === null) {
            return;
        }

        $phpDocInfo = $this->createPhpDocInfoFromNode($node);
        $this->replaceTagByAnother($phpDocInfo->getPhpDocNode(), $oldAnnotation, $newAnnotation);

        $this->updateNodeWithPhpDocInfo($node, $phpDocInfo);
    }

    public function getReturnType(Node $node): Type
    {
        if ($node->getDocComment() === null) {
            return new MixedType();
        }

        $phpDocInfo = $this->createPhpDocInfoFromNode($node);

        return $phpDocInfo->getReturnType();
    }

    /**
     * With "name" as key
     *
     * @param Function_|ClassMethod|Closure  $functionLike
     * @return Type[]
     */
    public function getParamTypesByName(FunctionLike $functionLike): array
    {
        if ($functionLike->getDocComment() === null) {
            return [];
        }

        $phpDocInfo = $this->createPhpDocInfoFromNode($functionLike);

        $paramTypesByName = [];

        foreach ($phpDocInfo->getParamTagValues() as $paramTagValueNode) {
            $parameterName = $paramTagValueNode->parameterName;

            $paramTypesByName[$parameterName] = $this->staticTypeMapper->mapPHPStanPhpDocTypeToPHPStanType(
                $paramTagValueNode,
                $functionLike
            );
        }

        return $paramTypesByName;
    }

    /**
     * @final
     * @return PhpDocTagNode[]
     */
    public function getTagsByName(Node $node, string $name): array
    {
        if ($node->getDocComment() === null) {
            return [];
        }

        $phpDocInfo = $this->createPhpDocInfoFromNode($node);

        return $phpDocInfo->getTagsByName($name);
    }

    public function changeVarTag(Node $node, Type $newType): void
    {
        $currentVarType = $this->getVarType($node);

        // make sure the tags are not identical, e.g imported class vs FQN class
        if ($this->areTypesEquals($currentVarType, $newType)) {
            return;
        }

        $this->removeTagFromNode($node, 'var', true);
        $this->addTypeSpecificTag($node, 'var', $newType);

        // to invoke the node override
        $node->setAttribute(AttributeKey::ORIGINAL_NODE, null);
    }

    public function addReturnTag(Node $node, Type $newType): void
    {
        $currentReturnType = $this->getReturnType($node);

        // make sure the tags are not identical, e.g imported class vs FQN class
        if ($this->areTypesEquals($currentReturnType, $newType)) {
            return;
        }

        $this->removeTagFromNode($node, 'return');
        $this->addTypeSpecificTag($node, 'return', $newType);
    }

    /**
     * @final
     */
    public function getTagByName(Node $node, string $name): PhpDocTagNode
    {
        if (! $this->hasTag($node, $name)) {
            throw new MissingTagException(sprintf('Tag "%s" was not found at "%s" node.', $name, get_class($node)));
        }

        /** @var PhpDocTagNode[] $foundTags */
        $foundTags = $this->getTagsByName($node, $name);
        return array_shift($foundTags);
    }

    public function getVarType(Node $node): Type
    {
        if ($node->getDocComment() === null) {
            return new MixedType();
        }

        return $this->createPhpDocInfoFromNode($node)->getVarType();
    }

    public function removeTagByName(PhpDocInfo $phpDocInfo, string $tagName): void
    {
        $phpDocNode = $phpDocInfo->getPhpDocNode();

        // A. remove class-based tag
        if (class_exists($tagName)) {
            $phpDocTagNode = $phpDocInfo->getByType($tagName);
            if ($phpDocTagNode) {
                $this->removeTagFromPhpDocNode($phpDocNode, $phpDocTagNode);
            }
        }

        // B. remove string-based tags
        $tagName = AnnotationNaming::normalizeName($tagName);
        $phpDocTagNodes = $phpDocInfo->getTagsByName($tagName);
        foreach ($phpDocTagNodes as $phpDocTagNode) {
            $this->removeTagFromPhpDocNode($phpDocNode, $phpDocTagNode);
        }
    }

    /**
     * @param PhpDocTagNode|PhpDocTagValueNode $phpDocTagOrPhpDocTagValueNode
     */
    public function removeTagFromPhpDocNode(PhpDocNode $phpDocNode, $phpDocTagOrPhpDocTagValueNode): void
    {
        // remove specific tag
        foreach ($phpDocNode->children as $key => $phpDocChildNode) {
            if ($phpDocChildNode === $phpDocTagOrPhpDocTagValueNode) {
                unset($phpDocNode->children[$key]);
                return;
            }
        }

        // or by type
        foreach ($phpDocNode->children as $key => $phpDocChildNode) {
            if (! $phpDocChildNode instanceof PhpDocTagNode) {
                continue;
            }

            if ($phpDocChildNode->value === $phpDocTagOrPhpDocTagValueNode) {
                unset($phpDocNode->children[$key]);
            }
        }
    }

    public function replaceTagByAnother(PhpDocNode $phpDocNode, string $oldTag, string $newTag): void
    {
        $oldTag = AnnotationNaming::normalizeName($oldTag);
        $newTag = AnnotationNaming::normalizeName($newTag);

        foreach ($phpDocNode->children as $phpDocChildNode) {
            if (! $phpDocChildNode instanceof PhpDocTagNode) {
                continue;
            }

            if ($phpDocChildNode->name === $oldTag) {
                $phpDocChildNode->name = $newTag;
            }
        }
    }

    public function importNames(Node $node): void
    {
        if ($node->getDocComment() === null) {
            return;
        }

        $phpDocInfo = $this->createPhpDocInfoFromNode($node);
        $hasNodeChanged = $this->docBlockNameImporter->importNames($phpDocInfo, $node);

        if ($hasNodeChanged) {
            $this->updateNodeWithPhpDocInfo($node, $phpDocInfo);
        }
    }

    /**
     * @param string[] $excludedClasses
     */
    public function changeUnderscoreType(Node $node, string $namespacePrefix, array $excludedClasses): void
    {
        if ($node->getDocComment() === null) {
            return;
        }

        $phpDocInfo = $this->createPhpDocInfoFromNode($node);
        $phpDocNode = $phpDocInfo->getPhpDocNode();
        $phpParserNode = $node;

        $this->phpDocNodeTraverser->traverseWithCallable($phpDocNode, function (PhpDocParserNode $node) use (
            $namespacePrefix,
            $excludedClasses,
            $phpParserNode
        ): PhpDocParserNode {
            if (! $node instanceof IdentifierTypeNode) {
                return $node;
            }

            $staticType = $this->staticTypeMapper->mapPHPStanPhpDocTypeNodeToPHPStanType($node, $phpParserNode);
            if (! $staticType instanceof ObjectType) {
                return $node;
            }

            if (! Strings::startsWith($staticType->getClassName(), $namespacePrefix)) {
                return $node;
            }

            // excluded?
            if (in_array($staticType->getClassName(), $excludedClasses, true)) {
                return $node;
            }

            // change underscore to \\
            $nameParts = explode('_', $staticType->getClassName());
            $node->name = '\\' . implode('\\', $nameParts);

            $this->hasPhpDocChanged = true;

            return $node;
        });

        if ($this->hasPhpDocChanged === false) {
            return;
        }

        $this->updateNodeWithPhpDocInfo($node, $phpDocInfo);
    }

    /**
     * For better performance
     */
    public function hasNodeTypeTags(Node $node): bool
    {
        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return false;
        }

        if ((bool) Strings::match($docComment->getText(), '#\@(param|throws|return|var)\b#')) {
            return true;
        }

        $phpDocInfo = $this->createPhpDocInfoFromNode($node);

        // has any type node?

        foreach ($phpDocInfo->getPhpDocNode()->children as $phpDocChildNode) {
            if ($phpDocChildNode instanceof PhpDocTagNode) {
                // is custom class, it can contain some type info
                if (Strings::startsWith(get_class($phpDocChildNode->value), 'Rector\\')) {
                    return true;
                }
            }
        }

        return false;
    }

    public function updateNodeWithPhpDocInfo(
        Node $node,
        PhpDocInfo $phpDocInfo,
        bool $shouldSkipEmptyLinesAbove = false
    ): bool {
        // skip if has no doc comment
        if ($node->getDocComment() === null) {
            return false;
        }

        $phpDoc = $this->phpDocInfoPrinter->printFormatPreserving($phpDocInfo, $shouldSkipEmptyLinesAbove);
        if ($phpDoc !== '') {
            // no change, don't save it
            if ($node->getDocComment()->getText() === $phpDoc) {
                return false;
            }

            $node->setDocComment(new Doc($phpDoc));
            return true;
        }

        // no comments, null
        $node->setAttribute('comments', null);

        return true;
    }

    public function getDoctrineFqnTargetEntity(Node $node): ?string
    {
        if ($node->getDocComment() === null) {
            return null;
        }

        $phpDocInfo = $this->createPhpDocInfoFromNode($node);

        $relationTagValueNode = $phpDocInfo->getByType(DoctrineRelationTagValueNodeInterface::class);
        if ($relationTagValueNode === null) {
            return null;
        }

        return $relationTagValueNode->getFqnTargetEntity();
    }

    public function createPhpDocInfoFromNode(Node $node): PhpDocInfo
    {
        if ($node->getDocComment() === null) {
            throw new ShouldNotHappenException(sprintf(
                'Node must have a comment. Check `$node->getDocComment() !== null` before passing it to %s',
                __METHOD__
            ));
        }

        return $this->phpDocInfoFactory->createFromNode($node);
    }

    public function getParamTypeByName(FunctionLike $functionLike, string $paramName): Type
    {
        $this->ensureParamNameStartsWithDollar($paramName, __METHOD__);

        $paramTypes = $this->getParamTypesByName($functionLike);
        return $paramTypes[$paramName] ?? new MixedType();
    }

    /**
     * All class-type tags are FQN by default to keep default convention through the code.
     * Some people prefer FQN, some short. FQN can be shorten with \Rector\CodingStyle\Rector\Namespace_\ImportFullyQualifiedNamesRector later, while short prolonged not
     */
    private function addTypeSpecificTag(Node $node, string $name, Type $type): void
    {
        $docStringType = $this->staticTypeMapper->mapPHPStanTypeToDocString($type);
        if ($docStringType === '') {
            return;
        }

        // there might be no phpdoc at all
        if ($node->getDocComment() !== null) {
            $phpDocInfo = $this->createPhpDocInfoFromNode($node);
            $phpDocNode = $phpDocInfo->getPhpDocNode();

            $varTagValueNode = new AttributeAwareVarTagValueNode(new AttributeAwareIdentifierTypeNode(
                $docStringType
            ), '', '');
            $phpDocNode->children[] = new AttributeAwarePhpDocTagNode('@' . $name, $varTagValueNode);

            $this->updateNodeWithPhpDocInfo($node, $phpDocInfo);
        } else {
            // create completely new docblock
            $varDocComment = sprintf("/**\n * @%s %s\n */", $name, $docStringType);
            $node->setDocComment(new Doc($varDocComment));
        }
    }

    private function areTypesEquals(Type $firstType, Type $secondType): bool
    {
        return $this->staticTypeMapper->createTypeHash($firstType) === $this->staticTypeMapper->createTypeHash(
            $secondType
        );
    }

    private function ensureParamNameStartsWithDollar(string $paramName, string $location): void
    {
        if (Strings::startsWith($paramName, '$')) {
            return;
        }

        throw new ShouldNotHappenException(sprintf(
            'Param name "%s" must start with "$" in "%s()" method.',
            $paramName,
            $location
        ));
    }
}
