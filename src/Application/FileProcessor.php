<?php declare(strict_types=1);

namespace Rector\Application;

use Nette\Loaders\RobotLoader;
use PhpParser\Lexer;
use PhpParser\Node;
use Rector\Exception\ShouldNotHappenException;
use Rector\NodeTypeResolver\FileSystem\CurrentFileInfoProvider;
use Rector\NodeTypeResolver\NodeScopeAndMetadataDecorator;
use Rector\PhpParser\NodeTraverser\RectorNodeTraverser;
use Rector\PhpParser\Parser\Parser;
use Rector\PhpParser\Printer\FormatPerservingPrinter;
use Symplify\PackageBuilder\FileSystem\SmartFileInfo;

final class FileProcessor
{
    /**
     * @var mixed[][]
     */
    private $tokensByFilePath = [];

    /**
     * @var FormatPerservingPrinter
     */
    private $formatPerservingPrinter;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var Lexer
     */
    private $lexer;

    /**
     * @var RectorNodeTraverser
     */
    private $rectorNodeTraverser;

    /**
     * @var NodeScopeAndMetadataDecorator
     */
    private $nodeScopeAndMetadataDecorator;

    /**
     * @var CurrentFileInfoProvider
     */
    private $currentFileInfoProvider;

    /**
     * @var bool
     */
    private $areStubsLoaded = false;

    public function __construct(
        FormatPerservingPrinter $formatPerservingPrinter,
        Parser $parser,
        Lexer $lexer,
        RectorNodeTraverser $rectorNodeTraverser,
        NodeScopeAndMetadataDecorator $nodeScopeAndMetadataDecorator,
        CurrentFileInfoProvider $currentFileInfoProvider
    ) {
        $this->formatPerservingPrinter = $formatPerservingPrinter;
        $this->parser = $parser;
        $this->lexer = $lexer;
        $this->rectorNodeTraverser = $rectorNodeTraverser;
        $this->nodeScopeAndMetadataDecorator = $nodeScopeAndMetadataDecorator;
        $this->currentFileInfoProvider = $currentFileInfoProvider;
    }

    public function parseFileInfoToLocalCache(SmartFileInfo $smartFileInfo): void
    {
        if (isset($this->tokensByFilePath[$smartFileInfo->getRealPath()])) {
            // already parsed
            return;
        }

        $this->currentFileInfoProvider->setCurrentFileInfo($smartFileInfo);

        [$newStmts, $oldStmts, $oldTokens] = $this->parseAndTraverseFileInfoToNodes($smartFileInfo);

        if ($newStmts === null) {
            throw new ShouldNotHappenException(sprintf(
                'Parsing of file "%s" went wrong. Might be caused by inlinced html. Does it have full "<?php" openings? Try re-run with --debug option to find out more.',
                $smartFileInfo->getRealPath()
            ));
        }

        // store tokens by absolute path, so we don't have to print them right now
        $this->tokensByFilePath[$smartFileInfo->getRealPath()] = [$newStmts, $oldStmts, $oldTokens];

        // @todo use filesystem cache to save parsing?
    }

    public function printToFile(SmartFileInfo $smartFileInfo): string
    {
        [$newStmts, $oldStmts, $oldTokens] = $this->tokensByFilePath[$smartFileInfo->getRealPath()];
        return $this->formatPerservingPrinter->printToFile($smartFileInfo, $newStmts, $oldStmts, $oldTokens);
    }

    /**
     * See https://github.com/nikic/PHP-Parser/issues/344#issuecomment-298162516.
     */
    public function printToString(SmartFileInfo $smartFileInfo): string
    {
        $this->makeSureFileIsParsed($smartFileInfo);

        [$newStmts, $oldStmts, $oldTokens] = $this->tokensByFilePath[$smartFileInfo->getRealPath()];
        return $this->formatPerservingPrinter->printToString($newStmts, $oldStmts, $oldTokens);
    }

    public function refactor(SmartFileInfo $smartFileInfo): void
    {
        $this->loadStubs();

        $this->makeSureFileIsParsed($smartFileInfo);

        [$newStmts, $oldStmts, $oldTokens] = $this->tokensByFilePath[$smartFileInfo->getRealPath()];
        $newStmts = $this->rectorNodeTraverser->traverse($newStmts);

        // this is needed for new tokens added in "afterTraverse()"
        $this->tokensByFilePath[$smartFileInfo->getRealPath()] = [$newStmts, $oldStmts, $oldTokens];
    }

    /**
     * @return Node[][]|mixed[]
     */
    private function parseAndTraverseFileInfoToNodes(SmartFileInfo $smartFileInfo): array
    {
        $oldStmts = $this->parser->parseFile($smartFileInfo->getRealPath());
        $oldTokens = $this->lexer->getTokens();

        // needed for \Rector\NodeTypeResolver\PHPStan\Scope\NodeScopeResolver
        $this->tokensByFilePath[$smartFileInfo->getRealPath()] = [$oldStmts, $oldStmts, $oldTokens];

        $newStmts = $this->nodeScopeAndMetadataDecorator->decorateNodesFromFile(
            $oldStmts,
            $smartFileInfo->getRealPath()
        );

        return [$newStmts, $oldStmts, $oldTokens];
    }

    private function makeSureFileIsParsed(SmartFileInfo $smartFileInfo): void
    {
        if (isset($this->tokensByFilePath[$smartFileInfo->getRealPath()])) {
            return;
        }

        throw new ShouldNotHappenException(sprintf(
            'File %s was not preparsed, so it cannot be printed.%sCheck "%s" method.',
            $smartFileInfo->getRealPath(),
            PHP_EOL,
            self::class . '::parseFileInfoToLocalCache()'
        ));
    }

    /**
     * Load stubs after composer autoload is loaded + rector "process <src>" is loaded,
     * so it is loaded only if the classes are really missing
     */
    private function loadStubs(): void
    {
        if ($this->areStubsLoaded) {
            return;
        }

        $stubDirectory = __DIR__ . '/../../stubs';

        $robotLoader = new RobotLoader();
        $robotLoader->addDirectory($stubDirectory);
        $robotLoader->setTempDirectory(sys_get_temp_dir() . '/rector_stubs');
        $robotLoader->register();

        $this->areStubsLoaded = true;
    }
}
