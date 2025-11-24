<?php
// src/Message/TransferNotification.php
namespace App\Message;

class TransferNotification
{
    private int $transferId;

    public function __construct(int $transferId)
    {
        $this->transferId = $transferId;
    }

    public function getTransferId(): int
    {
        return $this->transferId;
    }
}