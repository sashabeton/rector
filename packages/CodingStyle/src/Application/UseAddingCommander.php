<?php declare(strict_types=1);

namespace Rector\CodingStyle\Application;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Namespace_;
use Rector\CodingStyle\Imports\UsedImportsResolver;
use Rector\Contract\PhpParser\Node\CommanderInterface;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PhpParser\Node\BetterNodeFinder;
use Rector\PHPStan\Type\FullyQualifiedObjectType;
use Symplify\PackageBuilder\FileSystem\SmartFileInfo;

final class UseAddingCommander implements CommanderInterface
{
    /**
     * @var FullyQualifiedObjectType[][]
     */
    private $useImportTypesInFilePath = [];

    /**
     * @var FullyQualifiedObjectType[][]
     */
    private $functionUseImportTypesInFilePath = [];

    /**
     * @var UseImportsAdder
     */
    private $useImportsAdder;

    /**
     * @var UsedImportsResolver
     */
    private $usedImportsResolver;

    /**
     * @var BetterNodeFinder
     */
    private $betterNodeFinder;

    public function __construct(
        UseImportsAdder $useImportsAdder,
        UsedImportsResolver $usedImportsResolver,
        BetterNodeFinder $betterNodeFinder
    ) {
        $this->useImportsAdder = $useImportsAdder;
        $this->usedImportsResolver = $usedImportsResolver;
        $this->betterNodeFinder = $betterNodeFinder;
    }

    public function addUseImport(Node $node, FullyQualifiedObjectType $fullyQualifiedObjectType): void
    {
        /** @var SmartFileInfo|null $fileInfo */
        $fileInfo = $node->getAttribute(AttributeKey::FILE_INFO);
        if ($fileInfo === null) {
            return;
        }

        $this->useImportTypesInFilePath[$fileInfo->getRealPath()][] = $fullyQualifiedObjectType;
    }

    public function addFunctionUseImport(Node $node, FullyQualifiedObjectType $fullyQualifiedObjectType): void
    {
        /** @var SmartFileInfo $fileInfo */
        $fileInfo = $node->getAttribute(AttributeKey::FILE_INFO);
        $this->functionUseImportTypesInFilePath[$fileInfo->getRealPath()][] = $fullyQualifiedObjectType;
    }

    /**
     * @param Stmt[] $nodes
     * @return Stmt[]
     */
    public function traverseNodes(array $nodes): array
    {
        // no nodes → just return
        if (! isset($nodes[0])) {
            return $nodes;
        }

        $filePath = $this->getRealPathFromNode($nodes[0]);

        $useImportTypes = $this->useImportTypesInFilePath[$filePath] ?? [];
        $functionUseImportTypes = $this->functionUseImportTypesInFilePath[$filePath] ?? [];

        // nothing to import
        if ($useImportTypes === [] && $functionUseImportTypes === []) {
            return $nodes;
        }

        // clear applied imports, so isActive() doesn't return any false positives
        unset($this->useImportTypesInFilePath[$filePath], $this->functionUseImportTypesInFilePath[$filePath]);

        // A. has namespace? add under it
        $namespace = $this->betterNodeFinder->findFirstInstanceOf($nodes, Namespace_::class);
        if ($namespace instanceof Namespace_) {
            $this->useImportsAdder->addImportsToNamespace($namespace, $useImportTypes, $functionUseImportTypes);
            return $nodes;
        }

        // B. no namespace? add in the top
        return $this->useImportsAdder->addImportsToStmts($nodes, $useImportTypes, $functionUseImportTypes);
    }

    public function isActive(): bool
    {
        return count($this->useImportTypesInFilePath) > 0 || count($this->functionUseImportTypesInFilePath) > 0;
    }

    public function isShortImported(Node $node, FullyQualifiedObjectType $fullyQualifiedObjectType): bool
    {
        $filePath = $this->getRealPathFromNode($node);
        $shortName = $fullyQualifiedObjectType->getShortName();

        $fileUseImports = $this->useImportTypesInFilePath[$filePath] ?? [];
        foreach ($fileUseImports as $fileUseImport) {
            if ($fileUseImport->getShortName() === $shortName) {
                return true;
            }
        }

        $fileFunctionUseImportTypes = $this->functionUseImportTypesInFilePath[$filePath] ?? [];
        foreach ($fileFunctionUseImportTypes as $fileFunctionUseImportType) {
            if ($fileFunctionUseImportType->getShortName() === $fullyQualifiedObjectType->getShortName()) {
                return true;
            }
        }

        return false;
    }

    public function isImportShortable(Node $node, FullyQualifiedObjectType $fullyQualifiedObjectType): bool
    {
        $filePath = $this->getRealPathFromNode($node);

        $fileUseImportTypes = $this->useImportTypesInFilePath[$filePath] ?? [];

        foreach ($fileUseImportTypes as $useImportType) {
            if ($fullyQualifiedObjectType->equals($useImportType)) {
                return true;
            }
        }

        return false;
    }

    public function analyseFileInfoUseStatements(Node $node): void
    {
        $filePath = $this->getRealPathFromNode($node);

        // already analysed
        if (isset($this->useImportTypesInFilePath[$filePath])) {
            return;
        }

        $usedImportTypes = $this->usedImportsResolver->resolveForNode($node);
        foreach ($usedImportTypes as $usedImportType) {
            $this->useImportTypesInFilePath[$filePath][] = $usedImportType;
        }
    }

    public function hasImport(Node $node, FullyQualifiedObjectType $fullyQualifiedObjectType): bool
    {
        $useImports = $this->getUseImportTypesByNode($node);

        foreach ($useImports as $useImport) {
            if ($useImport->equals($fullyQualifiedObjectType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * This prevents importing:
     * - App\Some\Product
     *
     * if there is already:
     * - use App\Another\Product
     */
    public function canImportBeAdded(Node $node, FullyQualifiedObjectType $fullyQualifiedObjectType): bool
    {
        $useImportTypes = $this->getUseImportTypesByNode($node);

        foreach ($useImportTypes as $useImportType) {
            if (! $useImportType->equals($fullyQualifiedObjectType)) {
                if ($useImportType->areShortNamesEqual($fullyQualifiedObjectType)) {
                    return false;
                }
            }

            if ($useImportType->equals($fullyQualifiedObjectType)) {
                return true;
            }
        }

        return true;
    }

    /**
     * @return FullyQualifiedObjectType[]
     */
    private function getUseImportTypesByNode(Node $node): array
    {
        $filePath = $this->getRealPathFromNode($node);

        return $this->useImportTypesInFilePath[$filePath] ?? [];
    }

    private function getRealPathFromNode(Node $node): ?string
    {
        /** @var SmartFileInfo|null $fileInfo */
        $fileInfo = $node->getAttribute(AttributeKey::FILE_INFO);
        if ($fileInfo === null) {
            return null;
        }

        return $fileInfo->getRealPath();
    }
}
