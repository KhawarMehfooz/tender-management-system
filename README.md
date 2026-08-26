# Tender Management System

## Background

I came across a job posting on Upwork for a company that manages tenders (security and
cleaning services) entirely through Excel sheets, email inboxes, and folders on a network
drive. It holds up until something slips, a bidder-question deadline gets missed, a request
for missing documents lands in the wrong inbox, and a year after losing a contract nobody
remembers who they were up against or why.

The brief caught my attention, so I decided to build it myself as a learning and portfolio
project, not tied to the original client's specific service categories, but generalized into
a configurable-service-category tender management system anyone could adapt.

## What this is

A single-company tender management system meant to be the system of record for the entire
tender lifecycle: intake, team assignment, deadlines, documents, cost calculation and
approvals, bid/no-bid decisions, submission and follow-up, results, and the market/competitor
intelligence that accumulates from them over time.

## Status

Early: foundation stage. Not yet usable end to end.

## Tech stack

- Laravel 13 (PHP 8.4)
- Livewire 4
- Filament 5 (admin/CRUD layer)
- PostgreSQL
- Tailwind CSS 4 + Vite
- Pest (testing)
- Docker Compose (local dev)

## Local development (Docker)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

The app is served at [http://localhost](http://localhost).

Services (see `docker-compose.yml`):

| Service | Purpose |
|---|---|
| `app` | PHP-FPM application container |
| `nginx` | Web server, port `80` |
| `postgres` | Database, port `5432` |
| `queue` | Queue worker (`queue:work`) |
| `scheduler` | Laravel scheduler (`schedule:work`) |

Frontend assets (Tailwind/Vite):

```bash
npm install
npm run dev   # or: npm run build
```

## Contributing

Open source, open for contribution. Issues and pull requests are welcome.

## License

MIT — see [LICENSE](LICENSE).
