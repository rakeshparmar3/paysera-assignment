<?php
// src/MessageHandler/TransferNotificationHandler.php
namespace App\MessageHandler;

use App\Message\TransferNotification;
use App\Repository\TransferRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TransferNotificationHandler
{
    public function __construct(
        private TransferRepository $transferRepository,
        private LoggerInterface $logger
    ) {}

    public function __invoke(TransferNotification $message)
    {
        $transfer = $this->transferRepository->find($message->getTransferId());
        if ($transfer) {
            $this->logger->info('Processing transfer notification', [
                'transfer_id' => $transfer->getId(),
                'amount' => $transfer->getAmount(),
                'currency' => $transfer->getCurrency()
            ]);
            // Add notification logic here (email, SMS, etc.)
        }
    }
}