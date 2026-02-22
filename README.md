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
2. The first request runs `SELECT ... FOR UPDATE` on overlapping reservations for that spot
3. This acquires row-level locks; the second request blocks
4. The first request finds no overlaps, inserts the reservation, commits
5. The second request unblocks, finds the new reservation overlapping, returns **409 Conflict** with a user-friendly message: *"This time slot is no longer available. Another user has just reserved it."*

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
| **Services** | Business logic (AuthService, ReservationService) |
| **Background Worker** | Stale reservation checker, runs independently |
| **WebSocket** | Real-time broadcast, no business logic |

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

### Setup

1. Copy environment file:
   ```bash
   cp BE/.env.example BE/.env
   ```

2. Edit `BE/.env` with your database and Redis settings

3. Install dependencies:
   ```bash
   cd BE && composer install
   ```

4. Run migrations:
   ```bash
   php bin/migrate.php
   ```

5. Start services (in separate terminals):
   ```bash
   # API
   php -S localhost:8080 -t public

   # Worker
   php bin/stale-checker.php

   # WebSocket
   php bin/websocket-server.php
   ```

6. Frontend (from project root):
   ```bash
   cd FE && npm run dev
   ```

## Error Handling

HTTP status codes used consistently:

- **200** - Success (GET, PUT)
- **201** - Created (POST /reservations)
- **400** - Bad request (missing/invalid params)
- **401** - Unauthorized (missing/invalid JWT)
- **409** - Conflict (spot already booked - concurrency)
