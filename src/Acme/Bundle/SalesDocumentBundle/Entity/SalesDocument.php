<?php

namespace Acme\Bundle\SalesDocumentBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\EntityBundle\EntityProperty\DatesAwareInterface;
use Oro\Bundle\EntityBundle\EntityProperty\DatesAwareTrait;
use Oro\Bundle\EntityConfigBundle\Metadata\Attribute\Config;
use Oro\Bundle\EntityConfigBundle\Metadata\Attribute\ConfigField;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\UserBundle\Entity\User;

/**
 * Sales Document entity - represents invoices and other sales documents from ERP
 */
#[ORM\Entity]
#[ORM\Table(name: 'acme_sales_document')]
#[Config(
    defaultValues: [
        'entity' => ['icon' => 'fa-file-pdf-o'],
        'security' => ['type' => 'ACL', 'group_name' => 'commerce', 'category' => 'orders'],
        'ownership' => [
            'owner_type' => 'USER',
            'owner_field_name' => 'owner',
            'owner_column_name' => 'user_owner_id',
            'organization_field_name' => 'organization',
            'organization_column_name' => 'organization_id',
            'frontend_owner_type' => 'FRONTEND_USER',
            'frontend_owner_field_name' => 'customerUser',
            'frontend_owner_column_name' => 'customer_user_id'
        ]
    ]
)]
class SalesDocument implements DatesAwareInterface
{
    use DatesAwareTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected ?int $id = null;

    #[ORM\Column(name: 'document_number', type: 'string', length: 100)]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected ?string $documentNumber = null;

    #[ORM\Column(name: 'document_type', type: 'string', length: 50)]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected ?string $documentType = 'invoice';

    #[ORM\Column(name: 'document_date', type: 'date', nullable: true)]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected ?\DateTime $documentDate = null;

    #[ORM\Column(name: 'amount', type: 'decimal', precision: 19, scale: 4, nullable: true)]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected ?float $amount = null;

    #[ORM\Column(name: 'currency', type: 'string', length: 3, nullable: true)]
    protected ?string $currency = null;

    #[ORM\Column(name: 'due_date', type: 'date', nullable: true)]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected ?\DateTime $dueDate = null;

    #[ORM\Column(name: 'amount_paid', type: 'decimal', precision: 19, scale: 4, nullable: true)]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected ?float $amountPaid = null;

    #[ORM\ManyToOne(targetEntity: CustomerUser::class)]
    #[ORM\JoinColumn(name: 'customer_user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    protected ?CustomerUser $customerUser = null;

    #[ORM\ManyToOne(targetEntity: File::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'file_id', referencedColumnName: 'id', nullable: false)]
    protected ?File $file = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_owner_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    protected ?User $owner = null;

    #[ORM\ManyToOne(targetEntity: 'Oro\Bundle\OrganizationBundle\Entity\Organization')]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    protected $organization;

    #[ORM\Column(name: 'erp_id', type: 'string', length: 100, nullable: true)]
    #[ConfigField(defaultValues: ['dataaudit' => ['auditable' => true]])]
    protected ?string $erpId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocumentNumber(): ?string
    {
        return $this->documentNumber;
    }

    public function setDocumentNumber(?string $documentNumber): self
    {
        $this->documentNumber = $documentNumber;
        return $this;
    }

    public function getDocumentType(): ?string
    {
        return $this->documentType;
    }

    public function setDocumentType(?string $documentType): self
    {
        $this->documentType = $documentType;
        return $this;
    }

    public function getDocumentDate(): ?\DateTime
    {
        return $this->documentDate;
    }

    public function setDocumentDate(?\DateTime $documentDate): self
    {
        $this->documentDate = $documentDate;
        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(?float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getDueDate(): ?\DateTime
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTime $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getAmountPaid(): ?float
    {
        return $this->amountPaid;
    }

    public function setAmountPaid(?float $amountPaid): self
    {
        $this->amountPaid = $amountPaid;
        return $this;
    }

    public function getCustomerUser(): ?CustomerUser
    {
        return $this->customerUser;
    }

    public function setCustomerUser(?CustomerUser $customerUser): self
    {
        $this->customerUser = $customerUser;
        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): self
    {
        $this->file = $file;
        return $this;
    }

    public function getOrganization()
    {
        return $this->organization;
    }

    public function setOrganization($organization): self
    {
        $this->organization = $organization;
        return $this;
    }

    public function getErpId(): ?string
    {
        return $this->erpId;
    }

    public function setErpId(?string $erpId): self
    {
        $this->erpId = $erpId;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function __toString(): string
    {
        return $this->documentNumber ?: '';
    }
}