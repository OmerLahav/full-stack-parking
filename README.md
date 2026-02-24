# Smart Parking - Full Stack Application

A web application for managing a parking structure with real-time availability, concurrency-safe reservations, and background processing.

## Quick Start

```bash
docker-compose up
```

This spins up:
- **MySQL** (port 3306) - Database
- **Redis** (port 6379) - Message queue for WebSocket broadcasts
- **API** (port 8080) - REST API (runs migrations on startup)
- **Worker** - Background process that auto-releases stale reservations every 60 seconds
- **WebSocket** (port 8081) - Real-time updates for parking availability

### Seed Users

| Email | Password |
|-------|----------|
| driver1@parking.com | password123 |
| driver2@parking.com | password123 |

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /login | Authenticate and receive JWT. Body: `{ "email", "password" }` |
| GET | /spots | List all parking spots (requires JWT) |
| GET | /stats | Peak occupancy hours by hour (requires JWT). Returns `{ "peak_occupancy_hours": [{ "hour": 10, "occupancy": 5 }, ...] }` |
| GET | /reservations?date=YYYY-MM-DD | List booked reservations for a date (requires JWT) |
| POST | /reservations | Create reservation (requires JWT). Body: `{ "spot_id", "start_time", "end_time" }` |
| PUT | /reservations/{id}/complete | Mark reservation as completed (requires JWT) |

### Time Slots

Each day has 3 time slots:
- **08:00–12:00**
- **12:00–16:00**
- **16:00–20:00**

Example reservation payload:
```json
{
  "spot_id": 1,
  "start_time": "2025-02-22 08:00:00",
  "end_time": "2025-02-22 12:00:00"
}
```

## Architecture Decisions

### Concurrency Handling (Race Condition)

**Approach: Pessimistic locking with `SELECT ... FOR UPDATE`**

When two users try to reserve the same spot at the same time:
1. Both requests enter a database transaction
2. The first request locks the parking spot row (`SELECT ... FOR UPDATE` on `parking_spots`) — this serializes all bookings for that spot, including when the slot is empty
3. The second request blocks on the same lock
4. The first request checks for overlapping reservations (finds none), inserts, commits — releases the lock
5. The second request unblocks, finds the new reservation overlapping, rolls back, returns **409 Conflict** with: *"This time slot is no longer available. Another user has just reserved it."*

**Why not optimistic concurrency?** Optimistic locking (version column, retry) adds complexity and can still fail under high contention. Database-level locking is the standard, reliable approach for this use case.

### WebSocket vs REST

- **REST** handles all mutations: login, create reservation, complete reservation, fetch spots and reservations
- **WebSocket** is read-only: it broadcasts reservation changes to all connected clients
- **Flow**: When the API or Worker creates/completes a reservation, it pushes a message to a Redis queue. The WebSocket server polls this queue every 200ms and broadcasts to connected clients. Clients receive `{ channel, data: { change, reservation } }` and update their UI accordingly.

### Separation of Concerns

| Layer | Responsibility |
|-------|----------------|
| **Database** | `Database.php`, migrations, repositories (CRUD) |
| **API** | Slim routes, middleware (JWT auth), request/response |
| **Services** | Business logic (AuthService, ReservationService, StatsService) |
| **Background Worker** | Stale reservation checker, runs independently |
| **WebSocket** | Real-time broadcast, no business logic |

### Adding OIDC (Google/Okta/GitHub) Later

Auth uses a strategy pattern (`AuthStrategyInterface`). To add OIDC without rewriting core logic:

1. **Schema** – Migration: add `auth_provider`, `auth_provider_id` columns; make `password_hash` nullable.
2. **UserRepository** – Add `findByProviderId(provider, providerId)` and `findOrCreateByOidc(provider, providerId, email)`.
3. **OidcAuthStrategy** – Implement `AuthStrategyInterface`, use `findOrCreateByOidc`; inject into AuthService.
4. **AuthService** – Add `authenticateFromOidc(provider, claims)` that calls the OIDC strategy; reuse `createToken()`.
5. **AuthController** – Add `loginWithOidc()` and route `POST /auth/oidc`.
6. **Token validation** – Validate the ID token (JWKS) server-side before passing claims to `authenticateFromOidc()`.

`createToken()` and `validateToken()` require no changes; they work with any `['id', 'email']` user array.

### Assumptions

- **5 parking spots** as specified; **3 time slots per day** (08:00–12:00, 12:00–16:00, 16:00–20:00)
- **Login** uses email (driver1@parking.com, driver2@parking.com)
- **Reservations** use full datetime (e.g. `2025-02-22 08:00:00`) for start_time/end_time
- **Stale checker** runs every 60 seconds; reservations past `end_time` are auto-completed

## Local Development (without Docker)

### Prerequisites

- PHP 8.1+
- MySQL 8
- Redis
- Composer
- Node.js (for frontend)

### Setup

1. Copy environment file:
   ```bash
   cp .env.example .env
   ```

2. Edit `.env` with your database and Redis settings

3. Install backend dependencies:
   ```bash
   composer install
   ```

4. Run migrations:
   ```bash
   php bin/migrate.php
   ```

5. Build frontend (for production-style serving):
   ```bash
   npm run build
   ```

6. Start services (in separate terminals):
   ```bash
   # API + SPA (serves from public/)
   php -S localhost:8080 -t public public/router.php

   # Worker
   php bin/stale-checker.php

   # WebSocket
   php bin/websocket-server.php
   ```

   Or for frontend development with hot reload:
   ```bash
   # Terminal 1: API
   php -S localhost:8080 -t public public/router.php

   # Terminal 2: Frontend dev server (proxies API to 8080)
   npm run dev
   ```
   Then open http://localhost:5173

   When using the PHP server with built frontend, open http://localhost:8080

## Project Structure

```
├── public/                 # Web root (API + built SPA)
│   ├── api.php             # REST API entry point
│   ├── router.php          # Routes API vs SPA
│   ├── index.html          # SPA (built from root)
│   └── assets/             # Frontend build output
├── app/                    # Frontend source (Vanilla JS + Vue)
│   ├── main.js
│   ├── core/, pages/, services/
│   └── style.css
├── index.html              # Frontend entry (source)
├── vite.config.js
├── src/                    # Backend PHP
│   ├── Auth/
│   ├── Config/
│   ├── Controller/
│   ├── Database/
│   ├── Middleware/
│   ├── Repository/
│   ├── Service/
│   └── Constants/
├── bin/
│   ├── migrate.php
│   ├── stale-checker.php
│   ├── websocket-server.php
│   └── entrypoint-api.sh
├── migrations/
├── composer.json
├── package.json
└── docker-compose.yml
```

## Error Handling

HTTP status codes used consistently:

- **200** - Success (GET, PUT)
- **201** - Created (POST /reservations)
- **400** - Bad request (missing/invalid params)
- **401** - Unauthorized (missing/invalid JWT)
- **409** - Conflict (spot already booked - concurrency)
