<?php
// src/Controller/TransferController.php
namespace App\Controller;

use App\Entity\Transfer;
use App\Exception\InsufficientFundsException;
use App\Exception\TransferException;
use App\Service\TransferService;
use App\Service\AccountService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TransferController extends AbstractController
{
    private TransferService $transferService;
    private AccountService $accountService;
    private SerializerInterface $serializer;
    private ValidatorInterface $validator;
    private LoggerInterface $logger;
    private string $apiKey;

    public function __construct(
        TransferService $transferService,
        AccountService $accountService,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        string $apiKey
    ) {
        $this->transferService = $transferService;
        $this->accountService = $accountService;
        $this->serializer = $serializer;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->apiKey = $apiKey;
    }

    #[Route('/api/accounts/{id}', name: 'get_account', methods: ['GET'])]
    public function getAccount(int $id): JsonResponse
    {
        $account = $this->accountService->getAccount($id);
        return $this->json($account);
    }

    #[Route('/api/transfers', name: 'transfer_funds', methods: ['POST'])]
    public function transfer(Request $request): JsonResponse
    {
        // Validate API key
        $apiKey = $request->headers->get('X-API-KEY');
        if ($apiKey !== $this->apiKey) {
            return $this->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid API key',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            // Parse and validate request
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $errors = $this->validateTransferRequest($data);

            if (count($errors) > 0) {
                return $this->json([
                    'error' => 'Validation failed',
                    'errors' => $errors,
                ], Response::HTTP_BAD_REQUEST);
            }

            // Process the transfer
            $transfer = $this->transferService->transferFunds(
                $data['from_account_id'],
                $data['to_account_id'],
                $data['amount'],
                $data['currency']
            );

            // Return success response
            return $this->json(
                ['transfer' => $this->serializeTransfer($transfer)],
                Response::HTTP_CREATED
            );
        } catch (\JsonException $e) {
            return $this->json([
                'error' => 'Invalid JSON',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (InsufficientFundsException $e) {
            return $this->json([
                'error' => 'Insufficient funds',
                'message' => $e->getMessage(),
            ], Response::HTTP_PAYMENT_REQUIRED);
        } catch (TransferException $e) {
            return $this->json([
                'error' => 'Transfer failed',
                'message' => $e->getMessage(),
            ], Response::HTTP_CONFLICT);
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error during transfer', [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function validateTransferRequest(array $data): array
    {
        $constraints = new Assert\Collection([
            'from_account_id' => [
                new Assert\NotBlank(),
                new Assert\Type(['type' => 'numeric']),
                new Assert\Positive(),
            ],
            'to_account_id' => [
                new Assert\NotBlank(),
                new Assert\Type(['type' => 'numeric']),
                new Assert\Positive(),
                new Assert\NotEqualTo([
                    'value' => $data['from_account_id'] ?? null,
                    'message' => 'Cannot transfer to the same account',
                ]),
            ],
            'amount' => [
                new Assert\NotBlank(),
                new Assert\Type(['type' => 'string']),
                new Assert\Regex([
                    'pattern' => '/^\d+(\.\d{1,2})?$/',
                    'message' => 'Amount must be a positive number with up to 2 decimal places',
                ]),
                new Assert\GreaterThan(['value' => '0']),
            ],
            'currency' => [
                new Assert\NotBlank(),
                new Assert\Type(['type' => 'string']),
                new Assert\Length(['min' => 3, 'max' => 3]),
            ],
        ]);

        $violations = $this->validator->validate($data, $constraints);
        $errors = [];

        foreach ($violations as $violation) {
            $field = preg_replace('/[\[\]]/', '', $violation->getPropertyPath());
            $errors[$field] = $violation->getMessage();
        }

        return $errors;
    }

    private function serializeTransfer(Transfer $transfer): array
    {
        return [
            'id' => $transfer->getId(),
            'from_account_id' => $transfer->getFromAccount()->getId(),
            'to_account_id' => $transfer->getToAccount()->getId(),
            'amount' => $transfer->getAmount(),
            'currency' => $transfer->getCurrency(),
            'status' => $transfer->getStatus(),
            'created_at' => $transfer->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'processed_at' => $transfer->getProcessedAt() ? $transfer->getProcessedAt()->format(\DateTimeInterface::ATOM) : null,
        ];
    }
}