<?php declare(strict_types=1);

namespace Rector\Tests\Rector\Typehint\ParentTypehintedArgumentRector;

use Rector\Rector\Typehint\ParentTypehintedArgumentRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use Rector\Tests\Rector\Typehint\ParentTypehintedArgumentRector\Source\ClassMetadataFactory;
use Rector\Tests\Rector\Typehint\ParentTypehintedArgumentRector\Source\ParserInterface;

final class ParentTypehintedArgumentRectorTest extends AbstractRectorTestCase
{
    /**
     * @dataProvider provideDataForTest()
     */
    public function test(string $file): void
    {
        $this->doTestFile($file);
    }

    /**
     * @return string[]
     */
    public function provideDataForTest(): iterable
    {
        yield [__DIR__ . '/Fixture/SomeClassImplementingParserInterface.php'];
        yield [__DIR__ . '/Fixture/MyMetadataFactory.php'];
    }

    /**
     * @return mixed[]
     */
    protected function getRectorsWithConfiguration(): array
    {
        return [
            ParentTypehintedArgumentRector::class => [
                '$typehintForArgumentByMethodAndClass' => [
                    ParserInterface::class => [
                        'parse' => [
                            'code' => 'string',
                        ],
                    ],
                    ClassMetadataFactory::class => [
                        'setEntityManager' => [
                            '$em' => 'Doctrine\ORM\EntityManagerInterface',
                        ],
                    ],
                ],
            ],
        ];
    }
}
