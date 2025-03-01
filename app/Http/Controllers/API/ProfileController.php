<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Update the user's profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'avatar' => 'sometimes|string|max:255',
            'bio' => 'sometimes|string|max:1000',
            'preferred_niches' => 'sometimes|array',
            'content_preferences' => 'sometimes|array',
            'timezone' => 'sometimes|string|max:255',
            'language' => 'sometimes|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update user data
        if ($request->has('name')) {
            $user->name = $request->name;
            $user->save();
        }

        // Get or create profile
        $profile = $user->profile;
        if (!$profile) {
            $profile = new UserProfile(['user_id' => $user->id]);
        }

        // Update profile data
        if ($request->has('avatar')) {
            $profile->avatar = $request->avatar;
        }

        if ($request->has('bio')) {
            $profile->bio = $request->bio;
        }

        if ($request->has('preferred_niches')) {
            $profile->preferred_niches = $request->preferred_niches;
        }

        if ($request->has('content_preferences')) {
            $profile->content_preferences = $request->content_preferences;
        }

        if ($request->has('timezone')) {
            $profile->timezone = $request->timezone;
        }

        if ($request->has('language')) {
            $profile->language = $request->language;
        }

        $profile->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->load('profile'),
        ]);
    }
}
