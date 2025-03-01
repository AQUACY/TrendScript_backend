<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Trend;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrendController extends Controller
{
    /**
     * Get all trending topics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $limit = $request->query('limit', 10);
        $sort = $request->query('sort', 'popularity');

        // Get user preferences from profile
        $profile = $user->profile;
        $preferredNiches = $profile ? $profile->preferred_niches : [];

        // Query trends
        $trends = Trend::when(!empty($preferredNiches), function ($query) use ($preferredNiches) {
                return $query->whereIn('niche', $preferredNiches);
            })
            ->when($sort === 'popularity', function ($query) {
                return $query->orderBy('popularity_score', 'desc');
            })
            ->when($sort === 'recent', function ($query) {
                return $query->orderBy('fetched_at', 'desc');
            })
            ->limit($limit)
            ->get();

        // If no trends found, fetch them now
        if ($trends->isEmpty()) {
            $this->fetchTrends();

            // Query again after fetching
            $trends = Trend::when(!empty($preferredNiches), function ($query) use ($preferredNiches) {
                    return $query->whereIn('niche', $preferredNiches);
                })
                ->when($sort === 'popularity', function ($query) {
                    return $query->orderBy('popularity_score', 'desc');
                })
                ->when($sort === 'recent', function ($query) {
                    return $query->orderBy('fetched_at', 'desc');
                })
                ->limit($limit)
                ->get();

            // If still no trends, use mock data
            if ($trends->isEmpty()) {
                $mockTrends = $this->getMockTrends($preferredNiches, $limit, $sort);
                return response()->json($mockTrends);
            }
        }

        return response()->json($trends);
    }

    /**
     * Get trending topics by niche.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $niche
     * @return \Illuminate\Http\Response
     */
    public function byNiche(Request $request, $niche)
    {
        $limit = $request->query('limit', 10);
        $sort = $request->query('sort', 'popularity');

        // Query trends by niche
        $trends = Trend::where('niche', $niche)
            ->when($sort === 'popularity', function ($query) {
                return $query->orderBy('popularity_score', 'desc');
            })
            ->when($sort === 'recent', function ($query) {
                return $query->orderBy('fetched_at', 'desc');
            })
            ->limit($limit)
            ->get();

        // If no trends found, fetch them now
        if ($trends->isEmpty()) {
            $this->fetchTrendsForNiche($niche);

            // Query again after fetching
            $trends = Trend::where('niche', $niche)
                ->when($sort === 'popularity', function ($query) {
                    return $query->orderBy('popularity_score', 'desc');
                })
                ->when($sort === 'recent', function ($query) {
                    return $query->orderBy('fetched_at', 'desc');
                })
                ->limit($limit)
                ->get();

            // If still no trends, use mock data
            if ($trends->isEmpty()) {
                $mockTrends = $this->getMockTrends([$niche], $limit, $sort);
                return response()->json($mockTrends);
            }
        }

        return response()->json($trends);
    }

    /**
     * Fetch trending topics from external APIs.
     * This would typically be called by a scheduled job.
     *
     * @return void
     */
    public function fetchTrends()
    {
        $niches = ['tech', 'gaming', 'motivation', 'business', 'health', 'education'];

        foreach ($niches as $niche) {
            $this->fetchTrendsForNiche($niche);
        }
    }

    /**
     * Fetch trending topics for a specific niche.
     *
     * @param  string  $niche
     * @return void
     */
    private function fetchTrendsForNiche($niche)
    {
        try {
            // YouTube API for trends
            $youtubeTrends = $this->fetchYouTubeTrends($niche);

            // Process and save trends
            if (!empty($youtubeTrends)) {
                $this->processTrends($youtubeTrends, 'youtube', $niche);
                Log::info('Successfully fetched trends for niche: ' . $niche);
            } else {
                // If YouTube API fails, use mock data
                $mockTrends = $this->getMockTrendsForNiche($niche);
                $this->processTrends($mockTrends, 'mock_data', $niche);
                Log::info('Using mock data for niche: ' . $niche);
            }
        } catch (\Exception $e) {
            // Log error
            Log::error('Failed to fetch trends for niche: ' . $niche . '. Error: ' . $e->getMessage());

            // Use mock data as fallback
            try {
                $mockTrends = $this->getMockTrendsForNiche($niche);
                $this->processTrends($mockTrends, 'mock_data', $niche);
                Log::info('Using mock data as fallback for niche: ' . $niche);
            } catch (\Exception $e) {
                Log::error('Failed to use mock data for niche: ' . $niche . '. Error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Fetch trending topics from YouTube.
     *
     * @param  string  $niche
     * @return array
     */
    private function fetchYouTubeTrends($niche)
    {
        $apiKey = env('YOUTUBE_API_KEY');
        $regionCode = 'US';
        $maxResults = 10;
        $categoryId = $this->getYouTubeCategoryId($niche);

        $url = "https://www.googleapis.com/youtube/v3/videos";
        $response = Http::get($url, [
            'part' => 'snippet,statistics',
            'chart' => 'mostPopular',
            'regionCode' => $regionCode,
            'maxResults' => $maxResults,
            'videoCategoryId' => $categoryId,
            'key' => $apiKey
        ]);

        if (!$response->successful()) {
            Log::error('YouTube API error: ' . $response->body());
            return [];
        }

        $data = $response->json();
        $trends = [];

        foreach ($data['items'] ?? [] as $item) {
            $snippet = $item['snippet'] ?? [];
            $statistics = $item['statistics'] ?? [];

            // Calculate popularity score based on views, likes, and comments
            $views = $statistics['viewCount'] ?? 0;
            $likes = $statistics['likeCount'] ?? 0;
            $comments = $statistics['commentCount'] ?? 0;

            // Simple formula: normalize to 0-100 scale based on views primarily
            $popularityScore = min(99, log10($views + 1) * 10);

            // Extract keywords from tags and title
            $keywords = $snippet['tags'] ?? [];
            if (empty($keywords)) {
                // If no tags, extract keywords from title
                $titleWords = explode(' ', strtolower($snippet['title'] ?? ''));
                $keywords = array_filter($titleWords, function($word) {
                    return strlen($word) > 3; // Only words longer than 3 chars
                });
                $keywords = array_slice(array_values($keywords), 0, 5); // Take up to 5 keywords
            }

            $trends[] = [
                'title' => $snippet['title'] ?? 'Untitled',
                'description' => $snippet['description'] ?? '',
                'keywords' => $keywords,
                'popularity_score' => $popularityScore,
                'metadata' => [
                    'video_id' => $item['id'] ?? '',
                    'channel_title' => $snippet['channelTitle'] ?? '',
                    'view_count' => $views,
                    'like_count' => $likes,
                    'comment_count' => $comments
                ]
            ];
        }

        return $trends;
    }

    /**
     * Map niche to YouTube category ID
     *
     * @param string $niche
     * @return string
     */
    private function getYouTubeCategoryId($niche)
    {
        $categoryMap = [
            'tech' => '28', // Science & Technology
            'gaming' => '20', // Gaming
            'motivation' => '22', // People & Blogs
            'business' => '22', // People & Blogs
            'health' => '26', // Howto & Style
            'education' => '27', // Education
            'default' => '0' // Film & Animation
        ];

        return $categoryMap[$niche] ?? $categoryMap['default'];
    }

    /**
     * Process and save trends.
     *
     * @param  array  $trends
     * @param  string  $source
     * @param  string  $niche
     * @return void
     */
    private function processTrends($trends, $source, $niche)
    {
        foreach ($trends as $trendData) {
            // Check if trend already exists
            $existingTrend = Trend::where('title', $trendData['title'])
                ->where('niche', $niche)
                ->first();

            $metadata = $trendData['metadata'] ?? [];

            if ($existingTrend) {
                // Update existing trend
                $existingTrend->update([
                    'description' => $trendData['description'],
                    'related_keywords' => $trendData['keywords'],
                    'popularity_score' => $trendData['popularity_score'],
                    'source' => $source,
                    'metadata' => $metadata,
                    'fetched_at' => now(),
                ]);
            } else {
                // Create new trend
                Trend::create([
                    'title' => $trendData['title'],
                    'description' => $trendData['description'],
                    'niche' => $niche,
                    'related_keywords' => $trendData['keywords'],
                    'popularity_score' => $trendData['popularity_score'],
                    'source' => $source,
                    'metadata' => $metadata,
                    'fetched_at' => now(),
                ]);
            }
        }
    }

    /**
     * Get mock trends for testing when API fails.
     *
     * @param  array  $niches
     * @param  int  $limit
     * @param  string  $sort
     * @return array
     */
    private function getMockTrends($niches = [], $limit = 10, $sort = 'popularity')
    {
        $allMockTrends = [];

        // If niches specified, only get those
        if (!empty($niches)) {
            foreach ($niches as $niche) {
                $allMockTrends = array_merge($allMockTrends, $this->getMockTrendsForNiche($niche));
            }
        } else {
            // Otherwise get all niches
            $defaultNiches = ['tech', 'gaming', 'motivation', 'business', 'health', 'education'];
            foreach ($defaultNiches as $niche) {
                $allMockTrends = array_merge($allMockTrends, $this->getMockTrendsForNiche($niche));
            }
        }

        // Sort the trends
        if ($sort === 'popularity') {
            usort($allMockTrends, function($a, $b) {
                return $b['popularity_score'] <=> $a['popularity_score'];
            });
        } else if ($sort === 'recent') {
            // For mock data, we'll just use the current order as "recent"
        }

        // Limit the results
        return array_slice($allMockTrends, 0, $limit);
    }

    /**
     * Get mock trends for a specific niche.
     *
     * @param  string  $niche
     * @return array
     */
    private function getMockTrendsForNiche($niche)
    {
        $mockTrends = [];

        switch ($niche) {
            case 'tech':
                $mockTrends = [
                    [
                        'title' => 'Latest AI Advancements in 2025',
                        'description' => 'Exploring the cutting-edge developments in artificial intelligence and machine learning.',
                        'keywords' => ['artificial intelligence', 'machine learning', 'neural networks', 'deep learning', 'AI'],
                        'popularity_score' => 95,
                        'metadata' => [
                            'video_id' => 'mock_tech_1',
                            'channel_title' => 'Tech Insights',
                            'view_count' => 1500000,
                            'like_count' => 75000,
                            'comment_count' => 12000
                        ]
                    ],
                    [
                        'title' => 'The Future of Quantum Computing',
                        'description' => 'How quantum computing is revolutionizing data processing and solving complex problems.',
                        'keywords' => ['quantum computing', 'qubits', 'superposition', 'entanglement', 'computing'],
                        'popularity_score' => 88,
                        'metadata' => [
                            'video_id' => 'mock_tech_2',
                            'channel_title' => 'Quantum World',
                            'view_count' => 980000,
                            'like_count' => 62000,
                            'comment_count' => 8500
                        ]
                    ]
                ];
                break;

            case 'gaming':
                $mockTrends = [
                    [
                        'title' => 'Next-Gen Console Comparison',
                        'description' => 'Detailed analysis of the latest gaming consoles and their performance capabilities.',
                        'keywords' => ['gaming', 'console', 'playstation', 'xbox', 'nintendo'],
                        'popularity_score' => 92,
                        'metadata' => [
                            'video_id' => 'mock_gaming_1',
                            'channel_title' => 'Game Review HQ',
                            'view_count' => 1200000,
                            'like_count' => 85000,
                            'comment_count' => 15000
                        ]
                    ],
                    [
                        'title' => 'Top 10 Open World Games of 2025',
                        'description' => 'Exploring the most immersive and expansive open world games released this year.',
                        'keywords' => ['open world', 'gaming', 'rpg', 'adventure', 'sandbox'],
                        'popularity_score' => 87,
                        'metadata' => [
                            'video_id' => 'mock_gaming_2',
                            'channel_title' => 'Gaming Universe',
                            'view_count' => 950000,
                            'like_count' => 72000,
                            'comment_count' => 9800
                        ]
                    ]
                ];
                break;

            case 'motivation':
                $mockTrends = [
                    [
                        'title' => 'Overcoming Adversity: Success Stories',
                        'description' => 'Inspiring stories of individuals who overcame significant challenges to achieve success.',
                        'keywords' => ['motivation', 'success', 'inspiration', 'perseverance', 'achievement'],
                        'popularity_score' => 89,
                        'metadata' => [
                            'video_id' => 'mock_motivation_1',
                            'channel_title' => 'Inspire Daily',
                            'view_count' => 1100000,
                            'like_count' => 92000,
                            'comment_count' => 18000
                        ]
                    ],
                    [
                        'title' => 'Mindfulness Techniques for Productivity',
                        'description' => 'How practicing mindfulness can significantly boost your productivity and focus.',
                        'keywords' => ['mindfulness', 'productivity', 'focus', 'meditation', 'mental health'],
                        'popularity_score' => 84,
                        'metadata' => [
                            'video_id' => 'mock_motivation_2',
                            'channel_title' => 'Mindful Living',
                            'view_count' => 820000,
                            'like_count' => 65000,
                            'comment_count' => 7500
                        ]
                    ]
                ];
                break;

            case 'business':
                $mockTrends = [
                    [
                        'title' => 'Sustainable Business Models for 2025',
                        'description' => 'Exploring eco-friendly and sustainable business practices that are shaping the future.',
                        'keywords' => ['sustainable business', 'eco-friendly', 'green economy', 'corporate responsibility', 'environmental'],
                        'popularity_score' => 86,
                        'metadata' => [
                            'video_id' => 'mock_business_1',
                            'channel_title' => 'Business Forward',
                            'view_count' => 920000,
                            'like_count' => 58000,
                            'comment_count' => 8200
                        ]
                    ],
                    [
                        'title' => 'Remote Work Revolution: The New Normal',
                        'description' => 'How remote work is transforming business operations and employee expectations.',
                        'keywords' => ['remote work', 'work from home', 'digital nomad', 'flexible work', 'business'],
                        'popularity_score' => 90,
                        'metadata' => [
                            'video_id' => 'mock_business_2',
                            'channel_title' => 'Future of Work',
                            'view_count' => 1050000,
                            'like_count' => 78000,
                            'comment_count' => 12500
                        ]
                    ]
                ];
                break;

            case 'health':
                $mockTrends = [
                    [
                        'title' => 'Holistic Approaches to Mental Wellness',
                        'description' => 'Comprehensive strategies for maintaining mental health through integrated approaches.',
                        'keywords' => ['mental health', 'wellness', 'holistic', 'psychology', 'self-care'],
                        'popularity_score' => 93,
                        'metadata' => [
                            'video_id' => 'mock_health_1',
                            'channel_title' => 'Wellness Journey',
                            'view_count' => 1300000,
                            'like_count' => 95000,
                            'comment_count' => 16000
                        ]
                    ],
                    [
                        'title' => 'Nutrition Science: Latest Discoveries',
                        'description' => 'Recent breakthroughs in understanding how nutrition affects overall health and longevity.',
                        'keywords' => ['nutrition', 'diet', 'health', 'food science', 'metabolism'],
                        'popularity_score' => 85,
                        'metadata' => [
                            'video_id' => 'mock_health_2',
                            'channel_title' => 'Nutrition Facts',
                            'view_count' => 880000,
                            'like_count' => 62000,
                            'comment_count' => 9000
                        ]
                    ]
                ];
                break;

            case 'education':
                $mockTrends = [
                    [
                        'title' => 'The Future of Online Learning',
                        'description' => 'How digital platforms are transforming education and creating new learning opportunities.',
                        'keywords' => ['online learning', 'e-learning', 'education', 'digital classroom', 'remote education'],
                        'popularity_score' => 91,
                        'metadata' => [
                            'video_id' => 'mock_education_1',
                            'channel_title' => 'Education Evolution',
                            'view_count' => 1150000,
                            'like_count' => 82000,
                            'comment_count' => 13500
                        ]
                    ],
                    [
                        'title' => 'Personalized Learning: Adapting to Individual Needs',
                        'description' => 'How customized educational approaches are helping students reach their full potential.',
                        'keywords' => ['personalized learning', 'adaptive education', 'individual learning', 'education technology', 'learning styles'],
                        'popularity_score' => 83,
                        'metadata' => [
                            'video_id' => 'mock_education_2',
                            'channel_title' => 'Learning Insights',
                            'view_count' => 780000,
                            'like_count' => 56000,
                            'comment_count' => 7200
                        ]
                    ]
                ];
                break;

            default:
                // Generic trends if niche not recognized
                $mockTrends = [
                    [
                        'title' => 'Emerging Trends in ' . ucfirst($niche),
                        'description' => 'Exploring the latest developments and trends in ' . $niche . '.',
                        'keywords' => [$niche, 'trends', 'innovation', 'development', 'future'],
                        'popularity_score' => 80,
                        'metadata' => [
                            'video_id' => 'mock_generic_1',
                            'channel_title' => 'Trend Watchers',
                            'view_count' => 750000,
                            'like_count' => 45000,
                            'comment_count' => 6000
                        ]
                    ]
                ];
        }

        // Add niche to each trend
        foreach ($mockTrends as &$trend) {
            $trend['niche'] = $niche;
        }

        return $mockTrends;
    }
}
