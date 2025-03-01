# TrendScript API Testing Guide

This directory contains resources for testing the TrendScript API using Postman.

## Contents

1. `TrendScript_API_Collection.json` - A complete Postman collection with all API endpoints
2. `sample_data.json` - Sample data for testing the API endpoints

## Setup Instructions

### Importing the Collection

1. Open Postman
2. Click on "Import" in the top left corner
3. Select the `TrendScript_API_Collection.json` file
4. The collection will be imported with all endpoints and example requests

### Setting Up Environment Variables

1. Create a new environment in Postman
2. Add the following variables:
   - `base_url`: Set to your local development URL (e.g., `http://localhost:8000`)
   - `auth_token`: This will be automatically populated when you use the login endpoint
   - `tiktok_api_key`: Your RapidAPI key for TikTok Trends API

### Testing the API

The collection is organized into folders based on functionality:

1. **Authentication**
   - Register a new user
   - Login to get an authentication token
   - Get user details
   - Logout

2. **User Profile**
   - Update user profile information

3. **Subscription**
   - Get subscription details
   - Create a new subscription
   - Update an existing subscription
   - Cancel a subscription
   - Get invoices

4. **Content**
   - Generate new content
   - Get all content
   - Get content by ID
   - Update content
   - Delete content

5. **Trends**
   - Get all trending topics
   - Get trends by niche

## Using the Sample Data

The `sample_data.json` file contains example data that you can use for testing:

- **Users**: Sample user credentials for registration and login
- **Profiles**: Sample profile data for updating user profiles
- **Trends**: Sample trending topics data
- **Content Samples**: Sample content data for testing content generation and management

To use this data:

1. Copy the relevant JSON object from the sample data file
2. Paste it into the request body in Postman
3. Modify as needed for your specific test case

## Testing Flow

For a complete testing flow:

1. Register a new user using the Register endpoint
2. Login with the registered user to get an authentication token
3. Update the user's profile
4. Get trending topics
5. Generate content based on a trending topic
6. Manage the generated content (update, delete)
7. Test subscription endpoints (if Stripe is configured)

## Notes

- The authentication token is automatically captured and stored in the environment variable when you use the login endpoint
- Make sure your Laravel backend is running before testing the API
- For subscription endpoints, you'll need to have Stripe configured properly
- Some endpoints may require additional setup (e.g., Cohere API for content generation)
- **TikTok Trends API**: Instead of Google Trends, we're using TikTok Trends API to get trending topics. You'll need to sign up for a RapidAPI key and subscribe to one of the TikTok Trends API providers.

## Setting Up TikTok Trends API

Since Google Trends API keys are not available, we'll use TikTok Trends API instead:

1. Sign up for a RapidAPI account at [RapidAPI](https://rapidapi.com/)
2. Subscribe to one of these TikTok Trends API options:
   - [TikTok Trend API by Apify](https://rapidapi.com/apify/api/tiktok-scraper)
   - [TikTok API by RapidAPI](https://rapidapi.com/rapidapi/api/tiktok25)
3. Get your API key from RapidAPI
4. Add your API key to the Postman environment as `tiktok_api_key`
5. Update your backend to use the TikTok Trends API instead of Google Trends

## Implementing TikTok Trends API in Your Backend

To implement TikTok Trends API in your Laravel backend:

1. Create a service class for TikTok Trends API:
```php
<?php

namespace App\Services\Trends;

use Illuminate\Support\Facades\Http;

class TikTokTrendsApi implements TrendsApiInterface
{
    protected $apiKey;
    protected $baseUrl = 'https://tiktok-scraper.p.rapidapi.com/';

    public function __construct()
    {
        $this->apiKey = env('TIKTOK_RAPIDAPI_KEY', '');
    }

    /**
     * Get all trending topics
     *
     * @param array $options
     * @return array
     */
    public function getAllTrends(array $options = [])
    {
        $limit = $options['limit'] ?? 10;
        $region = $options['region'] ?? 'US';
        
        $response = Http::withHeaders([
            'X-RapidAPI-Key' => $this->apiKey,
            'X-RapidAPI-Host' => 'tiktok-scraper.p.rapidapi.com'
        ])->get($this->baseUrl . 'trending', [
            'region' => $region,
            'limit' => $limit
        ]);
        
        if ($response->successful()) {
            return $this->formatTrendsResponse($response->json());
        }
        
        return [];
    }

    /**
     * Get trending topics for a specific niche
     *
     * @param string $niche
     * @param array $options
     * @return array
     */
    public function getTrendsByNiche(string $niche, array $options = [])
    {
        $limit = $options['limit'] ?? 10;
        $region = $options['region'] ?? 'US';
        
        $response = Http::withHeaders([
            'X-RapidAPI-Key' => $this->apiKey,
            'X-RapidAPI-Host' => 'tiktok-scraper.p.rapidapi.com'
        ])->get($this->baseUrl . 'trending', [
            'region' => $region,
            'limit' => $limit
        ]);
        
        if ($response->successful()) {
            $trends = $this->formatTrendsResponse($response->json());
            
            // Filter by hashtag category/niche
            return array_filter($trends, function($trend) use ($niche) {
                return isset($trend['niche']) && $trend['niche'] === $niche;
            });
        }
        
        return [];
    }

    /**
     * Format the response from TikTok API to match our application's format
     *
     * @param array $response
     * @return array
     */
    protected function formatTrendsResponse(array $response)
    {
        $trends = [];
        $id = 1;
        
        foreach ($response['data'] ?? [] as $item) {
            // Determine niche based on hashtags or description
            $niche = $this->determineNiche($item);
            
            $trend = [
                'id' => $id++,
                'title' => $item['desc'] ?? '',
                'description' => $item['desc'] ?? '',
                'source' => 'tiktok',
                'niche' => $niche,
                'popularity_score' => rand(70, 99), // TikTok doesn't provide this directly
                'related_keywords' => $this->extractHashtags($item),
                'metadata' => [
                    'video_count' => $item['stats']['playCount'] ?? 0,
                    'average_views' => $item['stats']['playCount'] ?? 0,
                ]
            ];
            
            $trends[] = $trend;
        }
        
        return $trends;
    }

    /**
     * Extract hashtags from TikTok video
     *
     * @param array $item
     * @return array
     */
    protected function extractHashtags(array $item)
    {
        $hashtags = [];
        
        if (isset($item['challenges'])) {
            foreach ($item['challenges'] as $challenge) {
                $hashtags[] = $challenge['title'] ?? '';
            }
        }
        
        return array_filter($hashtags);
    }

    /**
     * Determine niche based on video content
     *
     * @param array $item
     * @return string
     */
    protected function determineNiche(array $item)
    {
        $description = strtolower($item['desc'] ?? '');
        $hashtags = $this->extractHashtags($item);
        
        $nicheKeywords = [
            'tech' => ['tech', 'technology', 'coding', 'programming', 'ai', 'artificial intelligence'],
            'fitness' => ['fitness', 'workout', 'gym', 'exercise', 'health'],
            'food' => ['food', 'recipe', 'cooking', 'baking', 'meal'],
            'fashion' => ['fashion', 'style', 'outfit', 'clothing', 'dress'],
            'finance' => ['finance', 'money', 'investing', 'stocks', 'crypto'],
            'lifestyle' => ['lifestyle', 'life', 'daily', 'routine', 'home'],
        ];
        
        foreach ($nicheKeywords as $niche => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($description, $keyword) !== false) {
                    return $niche;
                }
                
                foreach ($hashtags as $hashtag) {
                    if (strpos(strtolower($hashtag), $keyword) !== false) {
                        return $niche;
                    }
                }
            }
        }
        
        return 'general';
    }
}
```

2. Update your service provider to use the TikTok Trends API:
```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Trends\TrendsApiInterface;
use App\Services\Trends\TikTokTrendsApi;

class TrendsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(TrendsApiInterface::class, function ($app) {
            return new TikTokTrendsApi();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
```

3. Add your TikTok RapidAPI key to your `.env` file:
```
TIKTOK_RAPIDAPI_KEY=your_rapidapi_key_here
```

## Troubleshooting

If you encounter issues:

1. Check that your backend server is running
2. Verify that the `base_url` environment variable is set correctly
3. Ensure you're authenticated for protected endpoints
4. Check the Laravel logs for backend errors
5. Verify your TikTok RapidAPI key is valid and has not exceeded usage limits 
