# Bank Transfer API

A secure, transactional API for transferring funds between accounts with Redis caching and message queuing.

## 🚀 Quick Start

1. **Clone and set up the project**
   ```bash
   git clone [your-repo-url].git
   cd bank-transfer-api
   cp .env.dist .env  # Update with your settings if needed
   ```

2. **Start the services**
   ```bash
   docker-compose up -d --build
   ```

3. **Install dependencies**
   ```bash
   docker-compose exec app composer install
   ```

4. **Set up the database**
   ```bash
   # Run migrations
   docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction
   OR (Create database manually named bank_test and run schema update)
   docker-compose exec app php bin/console doctrine:schema:update --force --dump-sql
   # Load test data
   docker-compose exec app php bin/console doctrine:fixtures:load --no-interaction
   ```

5. **Start the message consumer** (in a new terminal)
   ```bash
   docker-compose exec -T app php bin/console messenger:consume async -vv
   ```

## 🧪 Testing

### Run Tests
```bash
# Specific test file
docker-compose exec app php bin/phpunit tests/Integration/TransferServiceTest.php
```

### Test Data
Fixtures create these test accounts:
- Account 1: John Doe, 1000.00 USD
- Account 2: Jane Smith, 500.00 USD
- Account 3: Euro Account, 2000.00 EUR

# 📚 API Documentation

## 🔄 Account Operations

### Get Account Details
**GET** `/api/accounts/{id}`

#### Headers
```
X-API-KEY: test_api_key_123
```

#### Example Request
```bash
curl -X GET http://localhost:8082/api/accounts/1 \
  -H "X-API-KEY: test_api_key_123"
```

#### Success Response (200 OK)
```json
{
  "id": 1,
  "owner": "John Doe",
  "balance": "1000.00",
  "currency": "USD"
}
```

## 💰 Transfer Funds

### Endpoint
**POST** `/api/transfers`

#### Headers
```
Content-Type: application/json
X-API-KEY: test_api_key_123
```

#### Request Body
```json
{
  "from_account_id": 1,
  "to_account_id": 2,
  "amount": "100.50",
  "currency": "USD"
}
```

#### Success Response (201 Created)
```json
{
  "transfer": {
    "id": 1,
    "from_account_id": 1,
    "to_account_id": 2,
    "amount": "100.50",
    "currency": "USD",
    "status": "completed",
    "created_at": "2023-11-24T12:00:00+00:00",
    "processed_at": "2023-11-24T12:00:01+00:00"
  }
}
```

### Error Responses
| Status Code | Error Type | Description |
|-------------|------------|-------------|
| 400 | Bad Request | Invalid input data |
| 401 | Unauthorized | Missing or invalid API key |
| 402 | Payment Required | Insufficient funds |
| 409 | Conflict | Transfer cannot be completed (e.g., currency mismatch) |
| 500 | Internal Server Error | Unexpected error |

## 🧪 Testing the API

### Example Test Cases

1. **Successful Transfer**
   ```bash
   curl -X POST http://localhost:8082/api/transfers \
     -H "Content-Type: application/json" \
     -H "X-API-KEY: test_api_key_123" \
     -d '{"from_account_id": 1, "to_account_id": 2, "amount": "100.00", "currency": "USD"}'
   ```

2. **Insufficient Funds**
   ```bash
   curl -X POST http://localhost:8082/api/transfers \
     -H "Content-Type: application/json" \
     -H "X-API-KEY: test_api_key_123" \
     -d '{"from_account_id": 2, "to_account_id": 1, "amount": "1000.00", "currency": "USD"}'
   ```

3. **Currency Mismatch**
   ```bash
   curl -X POST http://localhost:8082/api/transfers \
     -H "Content-Type: application/json" \
     -H "X-API-KEY: test_api_key_123" \
     -d '{"from_account_id": 1, "to_account_id": 3, "amount": "100.00", "currency": "USD"}'
   ```

## 🛠 Development

### Running Services

```bash
# Start all services
docker-compose up -d

# Stop all services
docker-compose down

# Rebuild containers
docker-compose up -d --build
```

## Approximate time spent

- It tooks around 4-5 hours to complete this task, including project setup from scratch using docker.

## AI tools and prompts used

- As AI tool I have used **Windsurf** with it's free model **SWE-1** with PhpStorm
- You can find prompt.txt file in the root directory of the project
