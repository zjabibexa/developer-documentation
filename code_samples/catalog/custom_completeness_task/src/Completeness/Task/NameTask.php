<?php

namespace App\Completeness\Task;

use Ibexa\Bundle\ProductCatalog\UI\Completeness\Entry\BooleanEntry;
use Ibexa\Bundle\ProductCatalog\UI\Completeness\Entry\EntryInterface;
use Ibexa\Bundle\ProductCatalog\UI\Completeness\Task\AbstractTask;
use Ibexa\Contracts\ProductCatalog\Values\ProductInterface;

final class NameTask extends AbstractTask
{
    public function getIdentifier(): string
    {
        return 'name';
    }

    public function getName(): string
    {
        return 'Name';
    }

    public function getEntry(ProductInterface $product): ?EntryInterface
    {
      return new BooleanEntry($product->getName());
    }

    public function getSubtaskGroups(ProductInterface $product): ?array
    {
        return null;
    }

    public function getWeight(): int
    {
        return 1;
    }
}
