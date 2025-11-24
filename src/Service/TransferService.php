<?php
// src/Service/TransferService.php
namespace App\Service;

use App\Entity\Account;
use App\Entity\Transfer;
use App\Exception\InsufficientFundsException;
use App\Exception\TransferException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\TransferNotification;
use App\Service\AccountService;

class TransferService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private LockFactory $lockFactory;

    private MessageBusInterface $messageBus;

    private AccountService $accountService;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        LockFactory $lockFactory,
        MessageBusInterface $messageBus,
        AccountService $accountService
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->lockFactory = $lockFactory;
        $this->messageBus = $messageBus;
        $this->accountService = $accountService;
    }

    /**
     * @throws TransferException
     * @throws InsufficientFundsException
     * @throws \Throwable
     */
    public function transferFunds(int $fromAccountId, int $toAccountId, string $amount, string $currency): Transfer
    {
        // Create a transfer record
        $transfer = new Transfer();
        $transfer->setAmount($amount);
        $transfer->setCurrency($currency);

        // Use a distributed lock to prevent concurrent transfers for the same accounts
        $lock = $this->acquireDistributedLock($fromAccountId, $toAccountId);

        try {
            $this->entityManager->beginTransaction();

            // Lock both accounts in a consistent order to prevent deadlocks
            $fromAccount = $this->findAndLockAccount($fromAccountId);
            $toAccount = $this->findAndLockAccount($toAccountId);

            // Set accounts on transfer
            $transfer->setFromAccount($fromAccount);
            $transfer->setToAccount($toAccount);

            // Validate accounts
            $this->validateAccounts($fromAccount, $toAccount, $amount, $currency);

            // Perform the transfer using AccountService
            $this->accountService->updateAccountBalance(
                $fromAccount,
                bcsub($fromAccount->getBalance(), $amount, 2)
            );
            
            $this->accountService->updateAccountBalance(
                $toAccount,
                bcadd($toAccount->getBalance(), $amount, 2)
            );

            // Update transfer status
            $transfer->markAsCompleted();

            // Persist changes
            $this->entityManager->persist($transfer);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Transfer completed', [
                'transfer_id' => $transfer->getId(),
                'from_account' => $fromAccountId,
                'to_account' => $toAccountId,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            $this->messageBus->dispatch(new TransferNotification($transfer->getId()));
            return $transfer;
        } catch (InsufficientFundsException $e) {
            $this->handleTransferFailure($transfer, 'Insufficient funds', $e);
            throw $e;
        } catch (\Throwable $e) {
            $this->handleTransferFailure($transfer, 'Transfer failed', $e);
            throw new TransferException('Transfer failed: ' . $e->getMessage(), 0, $e);
        } finally {
            // Release the lock
            $lock->release();
        }
    }

    /**
     * @throws \Doctrine\ORM\EntityNotFoundException
     */
    private function findAndLockAccount(int $accountId): Account
    {
        // First try to get from cache
        $account = $this->accountService->getAccount($accountId);

        if (!$account) {
            throw new \Doctrine\ORM\EntityNotFoundException("Account {$accountId} not found");
        }

        // For update operation, we still need to use the repository directly
        // to acquire a lock, but we'll use AccountService for the actual update
        $account = $this->entityManager->getRepository(Account::class)
            ->findForUpdate($accountId);

        return $account;
    }

    /**
     * @throws InsufficientFundsException
     * @throws TransferException
     */
    private function validateAccounts(Account $fromAccount, Account $toAccount, string $amount, string $currency): void
    {
        if ($fromAccount->getId() === $toAccount->getId()) {
            throw new TransferException('Cannot transfer to the same account');
        }

        if ($fromAccount->getCurrency() !== $currency || $toAccount->getCurrency() !== $currency) {
            throw new TransferException('Currency mismatch');
        }

        if (bccomp($fromAccount->getBalance(), $amount, 2) < 0) {
            throw new InsufficientFundsException('Insufficient funds');
        }
    }

    private function acquireDistributedLock(int $fromAccountId, int $toAccountId): LockInterface
    {
        // Always lock in a consistent order to prevent deadlocks
        $minId = min($fromAccountId, $toAccountId);
        $maxId = max($fromAccountId, $toAccountId);

        $lock = $this->lockFactory->createLock("transfer_{$minId}_{$maxId}", 30, true);

        if (!$lock->acquire()) {
            throw new \RuntimeException('Could not acquire lock for transfer');
        }

        return $lock;
    }

    private function handleTransferFailure(Transfer $transfer, string $error, \Throwable $exception = null): void
    {
        $this->entityManager->rollback();
        $transfer->markAsFailed($error . ($exception ? ': ' . $exception->getMessage() : ''));

        try {
            $this->entityManager->persist($transfer);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to save failed transfer', [
                'error' => $e->getMessage(),
                'transfer' => $transfer->getId(),
            ]);
        }

        $this->logger->error($error, [
            'exception' => $exception ? $exception->getMessage() : null,
            'transfer' => $transfer->getId(),
        ]);
    }
}