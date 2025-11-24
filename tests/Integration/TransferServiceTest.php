<?php
// tests/Integration/TransferServiceTest.php
namespace App\Tests\Integration;

use App\Entity\Account;
use App\Repository\AccountRepository;
use App\Service\AccountService;
use App\Service\TransferService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\MessageBusInterface;

class TransferServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private TransferService $transferService;
    private AccountRepository $accountRepository;
    private LockFactory $lockFactory;
    private MessageBusInterface $messageBus;
    private AccountService $accountService;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        $this->accountRepository = $this->entityManager->getRepository(Account::class);
        $this->messageBus = self::getContainer()->get('messenger.bus.default');
        $this->accountService = self::getContainer()->get(AccountService::class);

        // Prefer an in-memory lock store for tests to avoid depending on a running Redis instance.
        // If you *do* want to test with Redis, set env var USE_TEST_REDIS=1 and make sure host/port are reachable.
        $useRedis = (bool) ($_ENV['USE_TEST_REDIS'] ?? $_SERVER['USE_TEST_REDIS'] ?? false);
        if ($useRedis) {
            try {
                $redis = new \Redis();
                // allow overriding host/port for CI via env
                $redisHost = $_ENV['TEST_REDIS_HOST'] ?? $_SERVER['TEST_REDIS_HOST'] ?? 'redis';
                $redisPort = (int) ($_ENV['TEST_REDIS_PORT'] ?? $_SERVER['TEST_REDIS_PORT'] ?? 6379);
                $redis->connect($redisHost, $redisPort);
                $store = new RedisStore($redis);
            } catch (\Throwable $e) {
                // fallback to in-memory if Redis isn't reachable
                $store = new InMemoryStore();
            }
        } else {
            $store = new InMemoryStore();
        }

        $this->lockFactory = new LockFactory($store);

        $this->transferService = new TransferService(
            $this->entityManager,
            self::getContainer()->get('monolog.logger'),
            $this->lockFactory,
            $this->messageBus,
            $this->accountService
        );

        $this->clearDatabase();
    }

    protected function tearDown(): void
    {
        // Close the EM before Kernel shutdown to avoid "EntityManager is open" warnings
        if ($this->entityManager && $this->entityManager->isOpen()) {
            $this->entityManager->close();
        }

        parent::tearDown();
    }

    private function clearDatabase(): void
    {
        // Use createSchemaManager() (replacement for deprecated getSchemaManager())
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        // For write queries use executeStatement() where available (safer than executeQuery for non-select)
        $conn = $this->entityManager->getConnection();
        if (method_exists($conn, 'executeStatement')) {
            $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
            foreach ($tables as $table) {
                // backticks not strictly necessary, but keep table names safe
                $conn->executeStatement(sprintf('TRUNCATE TABLE %s', $table));
            }
            $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        } else {
            // fallback for older DBAL versions
            $conn->executeQuery('SET FOREIGN_KEY_CHECKS=0');
            foreach ($tables as $table) {
                $conn->executeQuery(sprintf('TRUNCATE TABLE %s', $table));
            }
            $conn->executeQuery('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function createAccount(string $owner, string $balance, string $currency = 'USD'): Account
    {
        $account = new Account();
        $account->setOwner($owner);
        $account->setBalance($balance);
        $account->setCurrency($currency);

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $account;
    }


    public function testTransferFunds(): void
    {
        // Create test accounts
        $account1 = $this->createAccount('John Doe', '1000.00', 'USD');
        $account2 = $this->createAccount('Jane Smith', '500.00', 'USD');

        // Perform transfer
        $transfer = $this->transferService->transferFunds(
            $account1->getId(),
            $account2->getId(),
            '100.50',
            'USD'
        );

        // Refresh entities from database
        $this->entityManager->clear();
        $account1 = $this->accountRepository->find($account1->getId());
        $account2 = $this->accountRepository->find($account2->getId());

        // Assert balances
        $this->assertSame('899.50', $account1->getBalance());
        $this->assertSame('600.50', $account2->getBalance());
        $this->assertSame('completed', $transfer->getStatus());
    }

    public function testInsufficientFunds(): void
    {
        $account1 = $this->createAccount('John Doe', '50.00', 'USD');
        $account2 = $this->createAccount('Jane Smith', '100.00', 'USD');

        $this->expectException(\App\Exception\InsufficientFundsException::class);

        $this->transferService->transferFunds(
            $account1->getId(),
            $account2->getId(),
            '100.01',
            'USD'
        );
    }

    public function testConcurrentTransfers(): void
    {
        // Create test accounts
        $account1 = $this->createAccount('John Doe', '1000.00', 'USD');
        $account2 = $this->createAccount('Jane Smith', '500.00', 'USD');

        // Create a mock lock that always fails to acquire
        $lock = $this->createMock(\Symfony\Component\Lock\LockInterface::class);
        $lock->method('acquire')->willReturn(false);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $transferService = new TransferService(
            $this->entityManager,
            self::getContainer()->get('monolog.logger'),
            $lockFactory,
            $this->messageBus,
            $this->accountService
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not acquire lock for transfer');

        $transferService->transferFunds(
            $account1->getId(),
            $account2->getId(),
            '100.00',
            'USD'
        );
    }

    public function testTransferWithCurrencyMismatch(): void
    {
        $account1 = $this->createAccount('John Doe', '1000.00', 'USD');
        $account2 = $this->createAccount('Jane Smith', '500.00', 'EUR');

        $this->expectException(\App\Exception\TransferException::class);
        $this->expectExceptionMessage('Currency mismatch');

        $this->transferService->transferFunds(
            $account1->getId(),
            $account2->getId(),
            '100.00',
            'USD'
        );
    }

    public function testTransferToSameAccount(): void
    {
        $account = $this->createAccount('John Doe', '1000.00', 'USD');

        $this->expectException(\App\Exception\TransferException::class);
        $this->expectExceptionMessage('Cannot transfer to the same account');

        $this->transferService->transferFunds(
            $account->getId(),
            $account->getId(),
            '100.00',
            'USD'
        );
    }
}