<?php
// src/DataFixtures/AppFixtures.php
namespace App\DataFixtures;

use App\Entity\Account;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create two test accounts
        $account1 = new Account();
        $account1->setOwner('John Doe');
        $account1->setBalance('1000.00');
        $account1->setCurrency('USD');
        $manager->persist($account1);

        $account2 = new Account();
        $account2->setOwner('Jane Smith');
        $account2->setBalance('500.00');
        $account2->setCurrency('USD');
        $manager->persist($account2);

        // Add EUR account for currency testing
        $account3 = new Account();
        $account3->setOwner('Euro Account');
        $account3->setBalance('2000.00');
        $account3->setCurrency('EUR');
        $manager->persist($account3);

        $manager->flush();

        // Output the created account IDs for reference
        echo "Created accounts:\n";
        echo "  - ID: {$account1->getId()}, Owner: {$account1->getOwner()}, Balance: {$account1->getBalance()} {$account1->getCurrency()}\n";
        echo "  - ID: {$account2->getId()}, Owner: {$account2->getOwner()}, Balance: {$account2->getBalance()} {$account2->getCurrency()}\n";
        echo "  - ID: {$account3->getId()}, Owner: {$account3->getOwner()}, Balance: {$account3->getBalance()} {$account3->getCurrency()}\n";
    }
}