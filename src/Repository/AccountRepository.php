<?php
// src/Repository/AccountRepository.php
namespace App\Repository;

use App\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\ItemInterface;


class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    public function findForUpdate(int $id): ?Account
    {
        return $this->createQueryBuilder('a')
            ->where('a.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    public function findWithCache(int $id): ?Account
    {
        $cache = $this->getEntityManager()->getConfiguration()->getResultCache();

        return $cache->get(
            'account_'.$id,
            function(ItemInterface $item) use ($id) {
                $item->expiresAfter(3600); // Cache for 1 hour
                return $this->createQueryBuilder('a')
                    ->where('a.id = :id')
                    ->setParameter('id', $id)
                    ->getQuery()
                    ->getOneOrNullResult();
            }
        );
    }

    public function invalidateCache(int $accountId): void
    {
        $cache = $this->getEntityManager()->getConfiguration()->getResultCache();
        $cache->delete('account_'.$accountId);
    }
}