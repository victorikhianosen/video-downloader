<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\YouTubeService;
use App\Models\GoogleToken;
use Carbon\Carbon;

class GoogleOAuthController extends Controller
{
    protected YouTubeService $ytService;

    public function __construct(YouTubeService $ytService)
    {
        $this->ytService = $ytService;
    }

    // Redirect to Google consent screen
    public function redirect()
    {
        return redirect()->away($this->ytService->createAuthUrl());
    }

    // Callback from Google - saves tokens into the single google_token row
    public function callback(Request $request)
    {
        $code = $request->get('code');
        if (!$code) {
            return response('No code provided', 400);
        }

        $token = $this->ytService->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            return response('Token error: '.$token['error_description'] ?? $token['error'], 400);
        }

        $accessToken = $token['access_token'] ?? null;
        $refreshToken = $token['refresh_token'] ?? null;
        $expiresIn = $token['expires_in'] ?? null;
        $expiresAt = $expiresIn ? Carbon::now()->addSeconds($expiresIn) : null;

        $gt = GoogleToken::first();
        if (!$gt) {
            $gt = new GoogleToken();
        }

        $gt->access_token = $accessToken;
        // only set refresh_token if provided (Google sometimes returns it only on first consent)
        if ($refreshToken) {
            $gt->refresh_token = $refreshToken;
        }
        $gt->expires_at = $expiresAt;
        $gt->save();

        return response('Google credentials saved. You may close this page.', 200);
    }

    // Optional: revoke/unlink stored tokens
    public function unlink()
    {
        $gt = GoogleToken::first();
        if ($gt) {
            $gt->access_token = null;
            $gt->refresh_token = null;
            $gt->expires_at = null;
            $gt->save();
        }
        return response('Google credentials cleared', 200);
    }
}
