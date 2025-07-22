<?php

declare(strict_types=1);

namespace n5s\BlockMigrations\Migration;

use n5s\BlockVisitor\Visitor\BlockVisitorInterface;

abstract class AbstractSingleVisitorBlockMigration extends AbstractBlockMigration
{
    public function __construct()
    {
        parent::__construct([$this->getVisitor()]);
    }

    abstract protected function getVisitor(): BlockVisitorInterface;
}
