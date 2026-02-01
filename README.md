# Cue Sports Africa

A tournament management platform for competitive cue sports (pool, snooker, billiards) in Africa.

## Features

- **Player Management** - Registration, profiles, and Elo-based rating system
- **Tournament System** - Create and manage tournaments with bracket generation
- **Match Tracking** - Result submission, confirmation, and dispute resolution
- **Organizer Tools** - Host tournaments, view analytics, manage earnings and payouts
- **Payments** - Entry fees and organizer subscriptions via Paystack
- **Geographic Hierarchy** - Regional tournaments at country, region, and city levels
- **Admin Dashboard** - Tournament approval, dispute resolution, user management

## Tech Stack

**Backend**
- Laravel 12 (PHP 8.3)
- Laravel Passport (OAuth2 authentication)
- Laravel Horizon (queue management)
- Laravel Pulse (monitoring)
- MySQL/PostgreSQL

**Frontend**
- React 19 with TypeScript
- Inertia.js
- Tailwind CSS 4
- Radix UI components

**Services**
- Cloudinary (image uploads)
- Paystack (payments)
- Pusher (real-time updates)

## Requirements

- PHP 8.2+
- Node.js 18+
- Composer
- MySQL or PostgreSQL

## Installation

```bash
# Clone the repository
git clone https://github.com/tonimnim/cuesports.git
cd cuesports

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed the database
php artisan db:seed

# Start development server
composer dev
```

## Environment Variables

Configure the following in your `.env` file:

- `DB_*` - Database connection
- `MAIL_*` - Email configuration for OTP verification
- `CLOUDINARY_*` - Image upload service
- `PAYSTACK_*` - Payment processing
- `PUSHER_*` - Real-time notifications

## API Documentation

The API is organized into the following modules:

| Endpoint | Description |
|----------|-------------|
| `/api/auth/*` | Authentication (register, login, password reset) |
| `/api/profile/*` | Player profile management |
| `/api/organizer/*` | Organizer registration and management |
| `/api/tournaments/*` | Tournament CRUD and registration |
| `/api/matches/*` | Match results and disputes |
| `/api/payments/*` | Payment processing |
| `/api/subscriptions/*` | Organizer subscription plans |
| `/api/locations/*` | Geographic units (countries, regions, cities) |

## License

Proprietary - All rights reserved.
