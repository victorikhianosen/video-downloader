<?php

namespace App\Services;

use Google_Client;
use Google_Service_YouTube;
use App\Models\GoogleToken;
use Illuminate\Support\Facades\Log;

class YouTubeService
{
    protected Google_Client $client;

    public function __construct()
    {
        $this->client = new Google_Client();
        $this->client->setClientId(config('services.google.client_id') ?: env('GOOGLE_CLIENT_ID'));
        $this->client->setClientSecret(config('services.google.client_secret') ?: env('GOOGLE_CLIENT_SECRET'));
        $this->client->setRedirectUri(config('services.google.redirect') ?: env('GOOGLE_REDIRECT_URI'));
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        $this->client->setScopes([
            'https://www.googleapis.com/auth/youtube.readonly',
            'https://www.googleapis.com/auth/userinfo.profile',
            'openid',
        ]);
    }

    /**
     * Create OAuth URL to obtain initial consent (open this in browser).
     */
    public function createAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Exchange auth code for tokens.
     */
    public function fetchAccessTokenWithAuthCode(string $code): array
    {
        return $this->client->fetchAccessTokenWithAuthCode($code);
    }

    /**
     * Set token array on client.
     */
    public function setAccessToken(array $token)
    {
        $this->client->setAccessToken($token);
    }

    /**
     * Use API key for public metadata requests.
     */
    public function youtubeUsingApiKey(): Google_Service_YouTube
    {
        $client = new Google_Client();
        $client->setDeveloperKey(env('YOUTUBE_API_KEY'));
        return new Google_Service_YouTube($client);
    }

    /**
     * Build a YouTube service using the single stored OAuth token.
     * If token expired tries to refresh using stored refresh_token.
     */
    public function youtubeUsingStoredToken(): ?Google_Service_YouTube
    {
        $gt = GoogleToken::instance();
        if (!$gt) {
            Log::error('No google_token row exists.');
            return null;
        }

        // Prepare token array
        $token = [
            'access_token' => $gt->access_token,
            'refresh_token' => $gt->refresh_token,
            'expires_in' => $gt->expires_at ? $gt->expires_at->diffInSeconds(now()) : null,
        ];

        $this->setAccessToken(array_filter($token));

        // If expires or invalid, attempt refresh
        if ($this->client->isAccessTokenExpired()) {
            if (!empty($gt->refresh_token)) {
                try {
                    $this->client->fetchAccessTokenWithRefreshToken($gt->refresh_token);
                    $new = $this->client->getAccessToken();

                    // update persisted token values
                    $gt->access_token = $new['access_token'] ?? $gt->access_token;
                    if (isset($new['refresh_token'])) {
                        $gt->refresh_token = $new['refresh_token'];
                    }
                    if (isset($new['expires_in'])) {
                        $gt->expires_at = now()->addSeconds($new['expires_in']);
                    }
                    $gt->save();

                } catch (\Exception $e) {
                    Log::error('Failed to refresh Google token: '.$e->getMessage());
                    return null;
                }
            } else {
                // no refresh token available
                Log::error('Stored google token expired and no refresh_token present.');
                return null;
            }
        }

        return new Google_Service_YouTube($this->client);
    }

    /**
     * Get public video metadata via API key.
     */
    public function getVideoByIdPublic(string $videoId)
    {
        $youtube = $this->youtubeUsingApiKey();
        $parts = 'snippet,contentDetails,status';
        $response = $youtube->videos->listVideos($parts, ['id' => $videoId]);
        if (count($response->getItems()) === 0) {
            return null;
        }
        return $response->getItems()[0];
    }

    /**
     * Get channel ID associated with stored OAuth credentials (the connected account).
     */
    public function getConnectedChannelId(): ?string
    {
        $yt = $this->youtubeUsingStoredToken();
        if (!$yt) return null;

        try {
            $resp = $yt->channels->listChannels('id', ['mine' => true]);
            if (count($resp->getItems()) === 0) return null;
            return $resp->getItems()[0]->getId();
        } catch (\Exception $e) {
            Log::error('Failed to get connected channel id: '.$e->getMessage());
            return null;
        }
    }
}
