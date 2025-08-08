<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Model;

/**
 * Data transfer object for resolved inventory information
 */
class ResolvedInventory
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $quantity,
        public readonly bool $overriddenByGroup,
        public readonly ?string $groupName = null
    ) {}

    public function isInStock(): bool
    {
        return $this->status === 'in_stock';
    }

    public function isAvailable(): bool
    {
        return in_array($this->status, ['in_stock', 'pre_order', 'backorder']);
    }

    public function getStatusLabel(): string
    {
        return 'acme.cginventory.status.' . $this->status;
    }
}