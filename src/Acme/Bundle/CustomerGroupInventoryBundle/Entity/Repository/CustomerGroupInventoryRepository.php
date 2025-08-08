<?php

namespace Acme\Bundle\CustomerGroupInventoryBundle\Entity\Repository;

use Acme\Bundle\CustomerGroupInventoryBundle\Entity\CustomerGroupInventory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\WebsiteBundle\Entity\Website;

class CustomerGroupInventoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerGroupInventory::class);
    }

    /**
     * Find inventory record for specific product, customer group and website
     */
    public function findOneFor(Product $product, ?CustomerGroup $group, ?Website $website): ?CustomerGroupInventory
    {
        if (!$group) {
            return null;
        }

        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.product = :product')
            ->andWhere('i.customerGroup = :group')
            ->andWhere('i.isActive = :active')
            ->setParameter('product', $product)
            ->setParameter('group', $group)
            ->setParameter('active', true)
            ->setMaxResults(1);

        if ($website) {
            $qb->andWhere('i.website = :website OR i.website IS NULL')
               ->setParameter('website', $website)
               ->addSelect('(CASE WHEN i.website = :website THEN 0 ELSE 1 END) AS HIDDEN website_priority')
               ->orderBy('website_priority', 'ASC');
        } else {
            $qb->andWhere('i.website IS NULL');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
    
    /**
     * Alias for backward compatibility
     */
    public function findOneByProductAndGroup(
        Product $product,
        CustomerGroup $customerGroup,
        ?Website $website = null
    ): ?CustomerGroupInventory {
        return $this->findOneFor($product, $customerGroup, $website);
    }

    /**
     * Find all inventory records for a product
     */
    public function findAllForProduct(Product $product): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.product = :product')
            ->setParameter('product', $product)
            ->orderBy('i.customerGroup', 'ASC')
            ->addOrderBy('i.website', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all inventory records for a customer group
     */
    public function findAllForCustomerGroup(CustomerGroup $group): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.customerGroup = :group')
            ->setParameter('group', $group)
            ->orderBy('i.product', 'ASC')
            ->addOrderBy('i.website', 'ASC')
            ->getQuery()
            ->getResult();
    }
}