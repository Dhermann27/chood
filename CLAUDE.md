# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This App Is

**Chood** is an internal operations tool for a dog boarding/daycare facility. It integrates with a third-party pet care management system (referred to as "DD" throughout the code) and with Homebase (employee scheduling). The app manages:

- Real-time maps of where dogs are housed (cabin maps, yard maps, groom map, meal map)
- Cabin cleaning status tracking
- Yard rotation scheduling for employees
- Employee break/lunch scheduling
- Dog task management (feeding notes, medications, allergies, yard assignments)
- A deposit finder report tool

## Tech Stack

- **Backend**: Laravel 11 (PHP 8.3)
- **Frontend**: Vue 3 + Inertia.js (no page reloads; Vue pages served via Inertia)
- **Styling**: Tailwind CSS
- **Build**: Vite
- **Icons**: FontAwesome (kit `@awesome.me/kit-ed8e499057`)
- **Telescope**: enabled for local debugging

## Commands

```bash
# Start dev server (both must run concurrently)
php artisan serve
npm run dev

# Build frontend assets
npm run build

# Run migrations
php artisan migrate

# Run the scheduler (production)
php artisan schedule:run

# Initial deployment seed (services, employees, shifts from external APIs)
php artisan app:chood-deploy

# Lint PHP
./vendor/bin/pint

# Run tests
php artisan test
php artisan test --filter=TestName
```

## Architecture

### Data Flow

The app **pulls data from the DD API** via queued jobs rather than storing it as a canonical source of truth. Jobs authenticate with `FetchDataService` (cookie-based auth, cached up to ~2 hours), fetch data, and upsert into the local database.

**Scheduled jobs** (see `routes/console.php`):
- `GoFetchListJob` — every 15 seconds (6am–7:30pm): syncs currently in-house dogs, triggers booking/feeding/medication/allergy sub-jobs
- `GoFetchTimecardsJob` — every 15 seconds: syncs employee timecards
- `SyncDogServicesJob` — every minute: syncs dog service appointments
- `GoFetchServiceListJob` / `MarkCabinsForCleaningJob` / `GoFetchEmployeesJob` / `GoFetchShiftsJob` — daily

### Polling Pattern (Checksum-Based Refresh)

All map/data views poll their API endpoint on a timer. Each response includes an MD5 `checksum` of the data. The frontend sends its current checksum; if unchanged, the backend returns `false` (no data transfer). This avoids unnecessary re-renders. See `DataController` and the Vue pages using `localChecksum`.

### Key Backend Concepts

- **`ChoodTrait`** (`app/Traits/ChoodTrait.php`): shared query logic used by `MapController`, `DataController`, and `TaskController` — `getCabins()`, `getDogs()`, `getDogsByCabin()`, `getGroomingDogsToday()`
- **`FetchDataService`** (`app/Services/FetchDataService.php`): handles DD API authentication and HTTP requests; caches credentials
- **`RotationSettings`** (`app/Services/RotationSettings.php`): a daily cache key storing the active `YardCodes` preset (which yards are open)
- **`YardCodes` enum**: defines yard presets (2–4 yards, with optional midday reduction). Yard IDs are fixed: 1000–1004
- **`HousingServiceCodes` enum**: BRDC/BRDL (boarding), DCFD/DCHD (daycare), INTV (interview)

### Cabin Coordinate System

Cabins have `rho` (row) and `kappa` (column) fields used for positioning in map views. The `getCabins()` method applies hardcoded offsets/overrides for the three row-map views (`first`/`mid`/`last`). Cabin IDs below 1500 are in the "first" section and get special layout treatment.

### Frontend Structure

- `resources/js/Pages/` — Inertia page components (one per route)
- `resources/js/Components/chood/` — app-specific components (`DogCard`, `Map`, `AssignmentModal`, `ServerTime`)
- `resources/js/controlSchemes.js` — `ControlSchemes` enum (NONE/MODAL/SELECT_CABIN) used by interactive map pages
- `resources/js/utils.js` — shared utility functions

### External Integrations

- **DD API** (`config/services.php` `dd` key): all URIs, credentials, and service category arrays come from `.env`. The `sandbox_service_condition` env var switches between `=` (today only) and `<=` (sandbox: show all past services).
- **Homebase API**: employee and shift data pulled via `app/Jobs/Homebase/`
- **Google Calendar**: `GoogleCalendarService` + `google/apiclient`; credentials via `GOOGLE_APPLICATION_CREDENTIALS` env var

### Routes Summary

- `/fullmap`, `/rowmap{first|mid|last}`, `/yardmap{small|large}`, `/mealmap`, `/groommap` — display-only map views
- `/task` — task entry view for staff (cabin cleaning, dog assignments, breaks, lunch)
- `/depositfinder` — report tool requiring DD credentials
- `/api/*` — JSON data endpoints polled by the frontend
- Auth routes via Laravel Breeze (`/login`, `/register`, etc.) — only used for `/dashboard` and `/profile`
