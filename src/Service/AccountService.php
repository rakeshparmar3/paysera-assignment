<?php
// src/Service/AccountService.php
namespace App\Service;

use App\Entity\Account;
use App\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AccountService
{
    public function __construct(
        private AccountRepository $accountRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function getAccount(int $id): ?Account
    {
        return $this->accountRepository->findWithCache($id);
    }

    public function updateAccountBalance(Account $account, string $newBalance): void
    {
        $account->setBalance($newBalance);
        $this->entityManager->persist($account);
        $this->entityManager->flush();

        // Invalidate cache after update
        $this->accountRepository->invalidateCache($account->getId());

        $this->logger->info('Account balance updated', [
            'account_id' => $account->getId(),
            'new_balance' => $newBalance
        ]);
    }
}