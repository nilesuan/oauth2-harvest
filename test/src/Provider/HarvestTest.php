<?php namespace Nilesuan\OAuth2\Client\Test\Provider;

use Mockery as m;

class HarvestTest extends \PHPUnit_Framework_TestCase
{
    protected $provider;

    protected function setUp()
    {
        $this->provider = new \Nilesuan\OAuth2\Client\Provider\Harvest([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_client_secret',
            'redirectUri' => 'redirect_url',
        ]);
    }

    public function tearDown()
    {
        m::close();
        parent::tearDown();
    }

    public function testAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertArrayHasKey('approval_prompt', $query);
        $this->assertNotNull($this->provider->getState());
    }


    public function testScopes()
    {
        $options = ['scope' => [uniqid(),uniqid()]];

        $url = $this->provider->getAuthorizationUrl($options);

        $this->assertContains(urlencode(implode(',', $options['scope'])), $url);
    }

    public function testGetAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);

        $this->assertEquals('/oauth2/authorize', $uri['path']);
    }

    public function testGetBaseAccessTokenUrl()
    {
        $params = [];

        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);

        $this->assertEquals('/oauth2/token', $uri['path']);
    }

    public function testGetAccessToken()
    {
        $response = m::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn('{"access_token": "mock_access_token","scopes": "account","expires_in": 3600,"refresh_token": "mock_refresh_token","token_type": "bearer"}');
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertLessThanOrEqual(time() + 3600, $token->getExpires());
        $this->assertGreaterThanOrEqual(time(), $token->getExpires());
        $this->assertEquals('mock_refresh_token', $token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }

    public function testUserData()
    {
        $userId = rand(1000,9999);
        $firstName = uniqid();
        $lastName = uniqid();
        $name = $firstName.' '.$lastName;
        $email = uniqid();
        $avatar = uniqid();

        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('{"access_token": "mock_access_token","scopes": "account","expires_in": 3600,"refresh_token": "mock_refresh_token","token_type": "bearer"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);

        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn('{ "company": { "base_uri": "https://api.harvestapp.com", "full_domain": "api.harvestapp.com", "name": "Sample Company", "active": true, "week_start_day": "Monday", "time_format": "hours_minutes", "clock": "12h", "decimal_symbol": ".", "color_scheme": "orange", "modules": { "expenses": true, "invoices": true, "estimates": false, "approval": false }, "thousands_separator": ",", "plan_type": "trial" }, "user": { "timezone": "Pacific Time (US & Canada)", "timezone_identifier": "America/Los_Angeles", "timezone_utc_offset": -25200, "id": '.$userId.', "email": "'.$email.'", "admin": true, "first_name": "'.$firstName.'", "last_name": "'.$lastName.'", "avatar_url": "'.$avatar.'", "project_manager": { "is_project_manager": false, "can_see_rates": true, "can_create_projects": true, "can_create_invoices": true }, "timestamp_timers": false } }');
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(2)
            ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user = $this->provider->getResourceOwner($token);

        $this->assertEquals($userId, $user->getId());
        $this->assertEquals($userId, $user->toArray()['user']['id']);
        $this->assertEquals($name, $user->getName());
        $this->assertEquals($name, $user->toArray()['user']['first_name'].' '.$user->toArray()['user']['last_name']);
        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals($email, $user->toArray()['user']['email']);
        $this->assertEquals($avatar, $user->getAvatar());
        $this->assertEquals($avatar, $user->toArray()['user']['avatar_url']);
    }

    public function testUserDataFails()
    {
        $errorPayloads = [
            '{"error":"mock_error","error_description": "mock_error_description"}',
            '{"error":{"message":"mock_error"},"error_description": "mock_error_description"}',
            '{"foo":"bar"}'
        ];

        $testPayload = function ($payload) {
            $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
            $postResponse->shouldReceive('getBody')->andReturn('{"access_token": "mock_access_token","scopes": "account","expires_in": 3600,"refresh_token": "mock_refresh_token","token_type": "bearer"}');
            $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);

            $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
            $userResponse->shouldReceive('getBody')->andReturn($payload);
            $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
            $userResponse->shouldReceive('getStatusCode')->andReturn(500);

            $client = m::mock('GuzzleHttp\ClientInterface');
            $client->shouldReceive('send')
                ->times(2)
                ->andReturn($postResponse, $userResponse);
            $this->provider->setHttpClient($client);

            $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

            try {
                $user = $this->provider->getResourceOwner($token);
                return false;
            } catch (\Exception $e) {
                $this->assertInstanceOf('\League\OAuth2\Client\Provider\Exception\IdentityProviderException', $e);
            }

            return $payload;
        };

        $this->assertCount(2, array_filter(array_map($testPayload, $errorPayloads)));
    }
}
