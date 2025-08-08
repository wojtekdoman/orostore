<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Oro\Bundle\EntityConfigBundle\Metadata\Attribute\Config;
use Oro\Bundle\EntityConfigBundle\Metadata\Attribute\ConfigField;

/**
 * Customer Group Inventory Entity
 */
#[ORM\Entity(repositoryClass: 'Acme\Bundle\CustomerGroupInventoryBundle\Entity\Repository\CustomerGroupInventoryRepository')]
#[ORM\Table(name: 'acme_cg_inventory')]
#[ORM\UniqueConstraint(name: 'uniq_acme_cgi_pcgws', columns: ['product_id', 'customer_group_id', 'website_id'])]
#[ORM\Index(columns: ['product_id'], name: 'idx_acme_cgi_product')]
#[ORM\Index(columns: ['customer_group_id'], name: 'idx_acme_cgi_cg')]
#[ORM\Index(columns: ['website_id'], name: 'idx_acme_cgi_ws')]
#[ORM\HasLifecycleCallbacks]
#[Config(
    defaultValues: [
        'ownership' => [
            'owner_type' => 'ORGANIZATION',
            'owner_field_name' => 'organization',
            'owner_column_name' => 'organization_id'
        ],
        'security' => [
            'type' => 'ACL',
            'group_name' => '',
            'category' => 'catalog'
        ],
        'dataaudit' => [
            'auditable' => true
        ]
    ]
)]
class CustomerGroupInventory
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: CustomerGroup::class)]
    #[ORM\JoinColumn(name: 'customer_group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    private CustomerGroup $customerGroup;

    #[ORM\ManyToOne(targetEntity: Website::class)]
    #[ORM\JoinColumn(name: 'website_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    private ?Website $website = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6, options: ['default' => '0'])]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    private string $quantity = '0';

    #[ORM\Column(name: 'inventory_status', type: 'string', length: 32, options: ['default' => 'in_stock'])]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    private string $inventoryStatus = 'in_stock';

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    private \DateTimeInterface $updatedAt;

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getCustomerGroup(): CustomerGroup
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(CustomerGroup $customerGroup): self
    {
        $this->customerGroup = $customerGroup;
        return $this;
    }

    public function getWebsite(): ?Website
    {
        return $this->website;
    }

    public function setWebsite(?Website $website): self
    {
        $this->website = $website;
        return $this;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): self
    {
        $this->organization = $organization;
        return $this;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getInventoryStatus(): string
    {
        return $this->inventoryStatus;
    }

    public function setInventoryStatus(string $inventoryStatus): self
    {
        $this->inventoryStatus = $inventoryStatus;
        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }
}