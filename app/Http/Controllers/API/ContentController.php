<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Trend;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class ContentController extends Controller
{
    /**
     * Get all contents for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status', 'active');

        $contents = $user->contents()
            ->when($status === 'active', function ($query) {
                return $query->active();
            })
            ->when($status === 'archived', function ($query) {
                return $query->archived();
            })
            ->with('trend')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($contents);
    }

    /**
     * Get a specific content.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $content = $user->contents()->with('trend')->findOrFail($id);

        return response()->json($content);
    }

    /**
     * Generate new content based on a trend.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trend_id' => 'required|exists:trends,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'content_type' => 'required|string|in:video_script,blog_post,social_media',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Check if user is premium or has not exceeded free tier limits
        if (!$user->isPremium()) {
            $contentCount = $user->contents()->where('created_at', '>=', now()->subMonth())->count();
            if ($contentCount >= 3) {
                return response()->json([
                    'message' => 'Free tier limit reached. Please upgrade to premium to generate more content.',
                ], 403);
            }
        }

        // Get the trend
        $trend = Trend::findOrFail($request->trend_id);

        // Generate content using AI
        $generatedContent = $this->generateContentWithAI($trend, $request->content_type, $user);

        // Create content record
        $content = Content::create([
            'user_id' => $user->id,
            'trend_id' => $trend->id,
            'title' => $request->title ?? $generatedContent['title'],
            'description' => $request->description ?? $generatedContent['description'],
            'script_structure' => $generatedContent['script_structure'],
            'seo_data' => $generatedContent['seo_data'],
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Content generated successfully',
            'content' => $content->load('trend'),
        ], 201);
    }

    /**
     * Update a content.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'script_structure' => 'sometimes|array',
            'seo_data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $content = $user->contents()->findOrFail($id);

        // Check if content is archived and user is not premium
        if ($content->status === 'archived' && !$user->isPremium()) {
            return response()->json([
                'message' => 'This content is archived. Please upgrade to premium to edit archived content.',
            ], 403);
        }

        $content->update($request->only([
            'title',
            'description',
            'script_structure',
            'seo_data',
        ]));

        return response()->json([
            'message' => 'Content updated successfully',
            'content' => $content->fresh()->load('trend'),
        ]);
    }

    /**
     * Delete a content.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request, $id)
    {
        $user = $request->user();
        $content = $user->contents()->findOrFail($id);

        // Archive instead of delete for free users
        if ($user->isFree()) {
            $content->archive();

            return response()->json([
                'message' => 'Content archived. Upgrade to premium to restore.',
            ]);
        }

        // Delete for premium users
        $content->delete();

        return response()->json([
            'message' => 'Content deleted successfully',
        ]);
    }

    /**
     * Generate content using AI.
     *
     * @param  \App\Models\Trend  $trend
     * @param  string  $contentType
     * @param  \App\Models\User  $user
     * @return array
     */
    private function generateContentWithAI(Trend $trend, $contentType, $user)
    {
        // Get user preferences from profile
        $profile = $user->profile;
        $preferences = $profile ? $profile->content_preferences : [];

        // Prepare prompt based on content type and trend
        $prompt = $this->preparePrompt($trend, $contentType, $preferences);

        // Call Cohere API (or other AI model)
        try {
            // This is a placeholder for the actual API call
            // In a real implementation, you would use the appropriate API client
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.cohere.api_key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.cohere.ai/v1/generate', [
                'model' => 'command',
                'prompt' => $prompt,
                'max_tokens' => 1000,
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                // Parse the response and structure the content
                $aiResponse = $response->json();

                // This is a simplified example of parsing the AI response
                // In a real implementation, you would need to parse the response properly
                return $this->parseAIResponse($aiResponse, $contentType, $trend);
            } else {
                // If the API call fails, return a default structure
                return $this->getDefaultContent($trend, $contentType);
            }
        } catch (\Exception $e) {
            // If there's an exception, return a default structure
            return $this->getDefaultContent($trend, $contentType);
        }
    }

    /**
     * Prepare prompt for AI based on trend and content type.
     *
     * @param  \App\Models\Trend  $trend
     * @param  string  $contentType
     * @param  array  $preferences
     * @return string
     */
    private function preparePrompt(Trend $trend, $contentType, $preferences)
    {
        $tone = $preferences['tone'] ?? 'professional';
        $style = $preferences['style'] ?? 'informative';

        $prompt = "Create a {$contentType} about {$trend->title}.\n\n";
        $prompt .= "Niche: {$trend->niche}\n";
        $prompt .= "Keywords: " . implode(', ', $trend->keywords ?? []) . "\n";
        $prompt .= "Tone: {$tone}\n";
        $prompt .= "Style: {$style}\n\n";

        if ($contentType === 'video_script') {
            $prompt .= "Create a structured video script with the following sections:\n";
            $prompt .= "1. Introduction (hook the viewer)\n";
            $prompt .= "2. Main points (3-5 key points)\n";
            $prompt .= "3. Conclusion\n";
            $prompt .= "4. Call to action\n\n";
            $prompt .= "Also include SEO-friendly title, description, and tags.\n";
        } elseif ($contentType === 'blog_post') {
            $prompt .= "Create a structured blog post with the following sections:\n";
            $prompt .= "1. Introduction\n";
            $prompt .= "2. Main content (with subheadings)\n";
            $prompt .= "3. Conclusion\n";
            $prompt .= "4. Call to action\n\n";
            $prompt .= "Also include SEO-friendly title, meta description, and keywords.\n";
        } elseif ($contentType === 'social_media') {
            $prompt .= "Create a set of social media posts for different platforms:\n";
            $prompt .= "1. Twitter (280 characters)\n";
            $prompt .= "2. Instagram (caption with hashtags)\n";
            $prompt .= "3. LinkedIn (professional tone)\n";
            $prompt .= "4. Facebook (engaging post)\n\n";
            $prompt .= "Also include hashtags and engagement questions.\n";
        }

        return $prompt;
    }

    /**
     * Parse AI response into structured content.
     *
     * @param  array  $aiResponse
     * @param  string  $contentType
     * @param  \App\Models\Trend  $trend
     * @return array
     */
    private function parseAIResponse($aiResponse, $contentType, $trend)
    {
        // This is a simplified example of parsing the AI response
        // In a real implementation, you would need to parse the response properly

        // Extract the generated text from the AI response
        $generatedText = $aiResponse['generations'][0]['text'] ?? '';

        // Default structure
        $result = [
            'title' => 'Generated Content: ' . $trend->title,
            'description' => 'Content about ' . $trend->title . ' in the ' . $trend->niche . ' niche.',
            'script_structure' => [],
            'seo_data' => [
                'keywords' => $trend->keywords ?? [],
                'meta_description' => 'Learn about ' . $trend->title . ' in this comprehensive content.',
                'tags' => $trend->keywords ?? [],
            ],
        ];

        // Parse based on content type
        if ($contentType === 'video_script') {
            // Simple parsing logic - in a real app, this would be more sophisticated
            $sections = explode("\n\n", $generatedText);

            $result['script_structure'] = [
                'introduction' => $sections[0] ?? 'Introduction to ' . $trend->title,
                'main_points' => [
                    'point_1' => $sections[1] ?? 'Main point 1 about ' . $trend->title,
                    'point_2' => $sections[2] ?? 'Main point 2 about ' . $trend->title,
                    'point_3' => $sections[3] ?? 'Main point 3 about ' . $trend->title,
                ],
                'conclusion' => $sections[4] ?? 'Conclusion about ' . $trend->title,
                'call_to_action' => $sections[5] ?? 'Like and subscribe for more content!',
            ];
        } elseif ($contentType === 'blog_post') {
            // Simple parsing logic for blog post
            $sections = explode("\n\n", $generatedText);

            $result['script_structure'] = [
                'introduction' => $sections[0] ?? 'Introduction to ' . $trend->title,
                'main_content' => [
                    'section_1' => [
                        'heading' => 'Understanding ' . $trend->title,
                        'content' => $sections[1] ?? 'Content about understanding ' . $trend->title,
                    ],
                    'section_2' => [
                        'heading' => 'Key Aspects of ' . $trend->title,
                        'content' => $sections[2] ?? 'Content about key aspects of ' . $trend->title,
                    ],
                    'section_3' => [
                        'heading' => 'Benefits of ' . $trend->title,
                        'content' => $sections[3] ?? 'Content about benefits of ' . $trend->title,
                    ],
                ],
                'conclusion' => $sections[4] ?? 'Conclusion about ' . $trend->title,
                'call_to_action' => $sections[5] ?? 'Share this post if you found it helpful!',
            ];
        } elseif ($contentType === 'social_media') {
            // Simple parsing logic for social media
            $sections = explode("\n\n", $generatedText);

            $result['script_structure'] = [
                'twitter' => $sections[0] ?? 'Tweet about ' . $trend->title,
                'instagram' => [
                    'caption' => $sections[1] ?? 'Instagram caption about ' . $trend->title,
                    'hashtags' => ['#' . str_replace(' ', '', $trend->title), '#' . $trend->niche, '#trending'],
                ],
                'linkedin' => $sections[2] ?? 'LinkedIn post about ' . $trend->title,
                'facebook' => $sections[3] ?? 'Facebook post about ' . $trend->title,
            ];
        }

        return $result;
    }

    /**
     * Get default content structure if AI generation fails.
     *
     * @param  \App\Models\Trend  $trend
     * @param  string  $contentType
     * @return array
     */
    private function getDefaultContent($trend, $contentType)
    {
        $result = [
            'title' => 'Content about ' . $trend->title,
            'description' => 'This content is about ' . $trend->title . ' in the ' . $trend->niche . ' niche.',
            'seo_data' => [
                'keywords' => $trend->keywords ?? [],
                'meta_description' => 'Learn about ' . $trend->title . ' in this comprehensive content.',
                'tags' => $trend->keywords ?? [],
            ],
        ];

        if ($contentType === 'video_script') {
            $result['script_structure'] = [
                'introduction' => 'Introduction to ' . $trend->title,
                'main_points' => [
                    'point_1' => 'Main point 1 about ' . $trend->title,
                    'point_2' => 'Main point 2 about ' . $trend->title,
                    'point_3' => 'Main point 3 about ' . $trend->title,
                ],
                'conclusion' => 'Conclusion about ' . $trend->title,
                'call_to_action' => 'Like and subscribe for more content!',
            ];
        } elseif ($contentType === 'blog_post') {
            $result['script_structure'] = [
                'introduction' => 'Introduction to ' . $trend->title,
                'main_content' => [
                    'section_1' => [
                        'heading' => 'Understanding ' . $trend->title,
                        'content' => 'Content about understanding ' . $trend->title,
                    ],
                    'section_2' => [
                        'heading' => 'Key Aspects of ' . $trend->title,
                        'content' => 'Content about key aspects of ' . $trend->title,
                    ],
                    'section_3' => [
                        'heading' => 'Benefits of ' . $trend->title,
                        'content' => 'Content about benefits of ' . $trend->title,
                    ],
                ],
                'conclusion' => 'Conclusion about ' . $trend->title,
                'call_to_action' => 'Share this post if you found it helpful!',
            ];
        } elseif ($contentType === 'social_media') {
            $result['script_structure'] = [
                'twitter' => 'Tweet about ' . $trend->title,
                'instagram' => [
                    'caption' => 'Instagram caption about ' . $trend->title,
                    'hashtags' => ['#' . str_replace(' ', '', $trend->title), '#' . $trend->niche, '#trending'],
                ],
                'linkedin' => 'LinkedIn post about ' . $trend->title,
                'facebook' => 'Facebook post about ' . $trend->title,
            ];
        }

        return $result;
    }
}
