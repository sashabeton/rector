<?php declare(strict_types=1);

namespace Rector\DoctrinePhpDocParser\Contract\Ast\PhpDoc;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode;

interface DoctrineRelationTagValueNodeInterface extends PhpDocTagValueNode
{
    public function getTargetEntity(): ?string;

    public function getFqnTargetEntity(): ?string;

    public function changeTargetEntity(string $targetEntity): void;
}
