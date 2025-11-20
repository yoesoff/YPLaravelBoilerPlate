````markdown
# YPLaravelBoilerPlate

## Purpose
Demonstrates clean Laravel code applying **SOLID principles** (SRP, DIP) with a service layer, domain exceptions, thin controllers, explicit validation, and seeded role-based test users.

---

## Table of Contents
- [Features](#features)
- [Architecture](#architecture)
- [Directory Structure](#directory-structure)
- [Seeded Test Users](#seeded-test-users)
- [Setup](#setup)
- [Running With Docker](#running-with-docker)
- [Running Locally](#running-locally)
- [Authentication Flow](#authentication-flow)
- [Sample Endpoints](#sample-endpoints) 👈 **POSTMAN Added Here**
- [Error Format](#error-format)
- [Testing](#testing)
- [Contributing](#contributing)
- [Security](#security)

---

## Features
- **JWT authentication** (register, login, logout, refresh, me)
- Protected user CRUD with **role/permission checks**
- **Service layer** (`UserService`) + interface binding (DIP)
- **Domain exceptions** for explicit failure modes
- **Centralized validation** via Form Requests
- Dockerized PostgreSQL environment
- Seeded sample users for quick testing

---

## Architecture 🏛️

This boilerplate adheres to the following principles and structure:

| Component | Responsibility | Principles Applied |
| :--- | :--- | :--- |
| **Controllers** | Thin orchestration, delegate to `UserService` | Single Responsibility Principle (SRP) |
| **Service Layer** | Encapsulates user lifecycle, permissions, side effects | SRP |
| **Domain Exceptions** | Explicit failure modes: forbidden, duplicate email, not found | Clean Error Handling |
| **Validation** | `CreateUserRequest` / `UpdateUserRequest` isolate rules | SRP |
| **Dependency Inversion** | Controller depends on `UserServiceInterface` (bound in container) | Dependency Inversion Principle (DIP) |
| **Auth** | JWT (Authorization: Bearer <token>) on protected routes | Standard Authentication |



---

## Directory Structure
- `app/Services` **business logic**
- `app/Exceptions/Domain` domain error types
- `app/Http/Controllers/Api` REST controllers
- `app/Http/Requests` validation classes
- `database/migrations` schema
- `database/seeders/UserSeeder.php` role test users
- `routes/api.php` API routes

---

## Seeded Test Users
Seeder creates 3 users per role (**Administrator**, **Manager**, **User**) if absent. All use password: `password123`.

| Role | Emails (example) |
| :--- | :--- |
| **Administrator** | administrator1@example.com, administrator2@example.com, administrator3@example.com |
| **Manager** | manager1@example.com, manager2@example.com, manager3@example.com |
| **User** | user1@example.com, user2@example.com, user3@example.com |

Quick login test:
```bash
curl -X POST http://localhost:8080/api/login \
 -H "Content-Type: application/json" \
 -d '{"email":"administrator1@example.com","password":"password123"}'
````

-----

## Setup

```bash
git clone [https://github.com/your-org/YPLaravelBoilerPlate.git](https://github.com/your-org/YPLaravelBoilerPlate.git)
cd YPLaravelBoilerPlate
cp .env.example .env
composer install
php artisan key:generate
```

Adjust DB host:

- Docker: `DB_HOST=yplaravelboilerplate_db`
- Local (no Docker): `DB_HOST=localhost`

Set `JWT_SECRET` (already present in `.env`).

-----

## Running With Docker

```bash
docker compose up --build
```

Startup command runs migrations + seeds automatically. App available at: **http://localhost:8080**

-----

## Running Locally (no Docker)

Ensure PostgreSQL running and `.env` points to localhost:

```bash
php artisan migrate --seed
php artisan serve
```

App: **http://localhost:8000**

-----

## Authentication Flow

1.  Register or use seeded credentials
2.  Login issues **JWT** (signed with `JWT_SECRET`)
3.  Client sends `Authorization: Bearer <token>`
4.  Middleware resolves user context
5.  Logout (token invalidation strategy: client discard / blacklist)

-----

## Sample Endpoints 🔌

### **Postman Collection (Recommended)**

Use this public link to quickly import all sample requests (login, register, CRUD) into your Postman workspace for easy testing.

| Action                 | Link |
|:-----------------------| :--- |
| **Postman Import URL** | `https://orange-satellite-484597.postman.co/workspace/YPLaravelBoilerPlate~b47d789d-c1eb-440f-b25f-0de37d079e47/collection/26625601-33b68dc8-8ff8-4a5b-b758-ee44ac8230f2?action=share&creator=26625601` |

### **cURL Examples**

```bash
# Register
curl -X POST http://localhost:8080/api/register \
 -H "Content-Type: application/json" \
 -d '{"name":"Alice","email":"alice@example.com","password":"Secret123"}'

# Login (seeded admin)
curl -X POST http://localhost:8080/api/login \
 -H "Content-Type: application/json" \
 -d '{"email":"administrator1@example.com","password":"password123"}'

# List users (protected)
curl -X GET http://localhost:8080/api/users \
 -H "Authorization: Bearer YOUR_JWT"

# Create user (admin/manager)
curl -X POST http://localhost:8080/api/users \
 -H "Authorization: Bearer ADMIN_JWT" \
 -H "Content-Type: application/json" \
 -d '{"name":"Bob","email":"bob@example.com","password":"Secret123","role":"User"}'
```

-----

## Error Format

Validation:

```json
{
  "error": "validation_failed",
  "messages": {
    "email": ["Email already exists."]
  }
}
```

Other HTTP/Domain Errors:

- `403` forbidden
- `404` not\_found
- `409` email\_exists
- `500` user\_creation\_failed / update\_failed / email\_failed

-----

## Testing

```bash
php artisan test
```

Add unit tests for permission matrix, duplicate email, exception mapping.

-----

## Contributing

- Fork
- Branch
- **Conventional Commits** (feat:, fix:, refactor:)
- Pull Request

-----

## Security

- Do not commit **real secrets**
- Rotate `JWT_SECRET` carefully (revokes tokens)
- Use **HTTPS in production**

-----

## License

Add chosen license.

```

I've added the Postman details under **Sample Endpoints** and included a conceptual image tag in the **Architecture** section to better illustrate the service layer concept.

Let me know if you'd like any other parts of the documentation reviewed or expanded!
```
