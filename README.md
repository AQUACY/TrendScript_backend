# TrendScript AI

TrendScript AI is a SaaS tool that generates and structures content for content creators based on trending topics using AI. It helps content creators stay relevant and produce high-quality content efficiently.

## Features

- **User Authentication**: Free and premium users, API authentication via Laravel Sanctum.
- **Stripe Subscription System**: Implemented with Laravel Cashier for managing subscriptions. Free users have limited access.
- **AI Content Generation**: Uses Cohere API for generating video scripts with structured scenes.
- **Trending Topics Fetching**: Integrates Google Trends and YouTube API to find trending topics in specific niches (tech tips, motivation, gaming, etc.).
- **Saved Content System**: Users can save generated content. Free users' content is archived after a week, requiring a subscription to restore access.
- **Automated Archiving**: Laravel command that archives content for free users after a week.
- **User Profile Management**: Users can update their details and preferences.
- **Queue System**: Uses Laravel Horizon for handling AI jobs efficiently.
- **SEO & Tag Optimization**: AI-generated content includes SEO-friendly titles, tags, and descriptions.

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/trendscript.git
   cd trendscript
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Copy the environment file:
   ```bash
   cp .env.example .env
   ```

4. Generate application key:
   ```bash
   php artisan key:generate
   ```

5. Configure your database in the `.env` file.

6. Run migrations:
   ```bash
   php artisan migrate
   ```

7. Configure API keys in the `.env` file:
   - Stripe API keys
   - Cohere API key
   - Google Trends API key
   - YouTube API key

8. Start the development server:
   ```bash
   php artisan serve
   ```

## API Documentation

### Authentication

- **POST /api/register**: Register a new user
- **POST /api/login**: Login and get access token
- **POST /api/logout**: Logout (requires authentication)
- **GET /api/user**: Get authenticated user details

### User Profile

- **PUT /api/user**: Update user profile

### Subscription Management

- **GET /api/subscription**: Get subscription details
- **POST /api/subscription/create**: Create a new subscription
- **PUT /api/subscription/update**: Update subscription
- **DELETE /api/subscription/cancel**: Cancel subscription
- **GET /api/subscription/invoices**: Get invoices

### Content Generation

- **POST /api/content/generate**: Generate new content
- **GET /api/content**: Get all user content
- **GET /api/content/{id}**: Get specific content
- **PUT /api/content/{id}**: Update content
- **DELETE /api/content/{id}**: Delete/archive content

### Trending Topics

- **GET /api/trends**: Get trending topics
- **GET /api/trends/{niche}**: Get trending topics by niche

## Scheduled Tasks

- **content:archive-free**: Archives content for free users that is older than a week (runs daily at midnight)
- **Trend Fetching**: Fetches trending topics from Google Trends and YouTube (runs every 4 hours)

## License

This project is licensed under the MIT License - see the LICENSE file for details.
