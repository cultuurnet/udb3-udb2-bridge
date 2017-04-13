<?php

namespace CultuurNet\UDB3\UDB2\Label;

use Broadway\Domain\AggregateRoot;

interface LabelApplierInterface
{
    public function apply(AggregateRoot $entity);
}
