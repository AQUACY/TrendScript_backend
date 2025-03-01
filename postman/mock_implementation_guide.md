# Implementing Mock Google Trends API

Since you couldn't get Google Trends API keys, this guide will help you implement a mock version of the Google Trends API in your TrendScript backend.

## Overview

The mock implementation provides the following functionality:
- Getting all trending topics
- Filtering trends by niche
- Getting related keywords for a trend
- Getting metadata for a trend

## Implementation Steps

### 1. Add the Mock Implementation to Your Project

Copy the `mock_trends_api.js` file to your project, for example in a directory like:
```
app/Services/Trends/MockTrendsApi.js
```

### 2. Create a Service Provider

In your Laravel project, create a service provider to bind the mock implementation:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Trends\TrendsApiInterface;
use App\Services\Trends\MockTrendsApi;

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
            return new MockTrendsApi();
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

### 3. Register the Service Provider

Add the service provider to your `config/app.php` file:

```php
'providers' => [
    // Other service providers...
    App\Providers\TrendsServiceProvider::class,
],
```

### 4. Create a PHP Wrapper for the JavaScript Mock

Create a PHP class that will interact with the JavaScript mock using Node.js:

```php
<?php

namespace App\Services\Trends;

class MockTrendsApi implements TrendsApiInterface
{
    /**
     * Get all trending topics
     *
     * @param array $options
     * @return array
     */
    public function getAllTrends(array $options = [])
    {
        // For a simple implementation, you can just return the mock data directly
        return json_decode(file_get_contents(storage_path('app/mock_data/trends.json')), true);
        
        // Alternatively, for a more sophisticated approach, you could use Node.js to run the JavaScript mock
        // $result = shell_exec('node ' . base_path('app/Services/Trends/run_mock.js') . ' getAllTrends ' . json_encode($options));
        // return json_decode($result, true);
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
        $allTrends = $this->getAllTrends();
        return array_filter($allTrends, function($trend) use ($niche) {
            return $trend['niche'] === $niche;
        });
    }

    /**
     * Get related keywords for a specific trend
     *
     * @param int $trendId
     * @return array
     */
    public function getRelatedKeywords(int $trendId)
    {
        $allTrends = $this->getAllTrends();
        foreach ($allTrends as $trend) {
            if ($trend['id'] === $trendId) {
                return $trend['related_keywords'];
            }
        }
        
        return [];
    }

    /**
     * Get trend metadata
     *
     * @param int $trendId
     * @return array
     */
    public function getTrendMetadata(int $trendId)
    {
        $allTrends = $this->getAllTrends();
        foreach ($allTrends as $trend) {
            if ($trend['id'] === $trendId) {
                return $trend['metadata'];
            }
        }
        
        return [];
    }
}
```

### 5. Create a JSON File with Mock Data

Create a file at `storage/app/mock_data/trends.json` with the trend data from `sample_data.json`:

```json
[
  {
    "id": 1,
    "title": "Artificial Intelligence in Healthcare",
    "description": "The growing use of AI in medical diagnostics and treatment planning",
    "source": "google_trends",
    "niche": "tech",
    "popularity_score": 95,
    "related_keywords": ["AI diagnostics", "machine learning healthcare", "medical AI"],
    "metadata": {
      "search_volume": 250000,
      "growth_percentage": 35
    }
  },
  // ... other trends
]
```

### 6. Use the Mock API in Your Controllers

Now you can use dependency injection to use the mock API in your controllers:

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Trends\TrendsApiInterface;

class TrendsController extends Controller
{
    protected $trendsApi;

    public function __construct(TrendsApiInterface $trendsApi)
    {
        $this->trendsApi = $trendsApi;
    }

    public function index(Request $request)
    {
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'popularity');
        
        $trends = $this->trendsApi->getAllTrends([
            'limit' => $limit,
            'sort' => $sort
        ]);
        
        return response()->json($trends);
    }

    public function getByNiche(Request $request, $niche)
    {
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'popularity');
        
        $trends = $this->trendsApi->getTrendsByNiche($niche, [
            'limit' => $limit,
            'sort' => $sort
        ]);
        
        return response()->json($trends);
    }
}
```

## Alternative: Simple Implementation

For a simpler approach, you can skip the JavaScript mock and just use the sample data directly in your PHP code:

1. Copy the trends data from `sample_data.json` to a file in your Laravel project
2. Create a service that reads this file and returns the data
3. Implement filtering and sorting in PHP

This approach is easier to implement but less flexible if you want to add more sophisticated mocking behavior later.

## Testing

Once implemented, you can test the mock API using the Postman collection provided. The endpoints should return the mock data as if it were coming from the real Google Trends API.

## Production Considerations

This mock implementation is intended for development and testing only. In a production environment, you would replace it with the real Google Trends API when you have access to the API keys. 
