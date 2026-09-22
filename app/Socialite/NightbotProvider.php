<?php

namespace App\Socialite;

use GuzzleHttp\RequestOptions;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class NightbotProvider extends AbstractProvider
{
    public const IDENTIFIER = 'NIGHTBOT';

    protected $scopes = ['commands'];

    protected $scopeSeparator = ' ';

    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://api.nightbot.tv/oauth2/authorize', $state);
    }

    protected function getTokenUrl()
    {
        return 'https://api.nightbot.tv/oauth2/token';
    }

    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get('https://api.nightbot.tv/1/me', [
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
                'Client-ID' => $this->clientId,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    protected function mapUserToObject(array $data)
    {
        return (new User)->setRaw($data['user']);
    }

    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }
}
