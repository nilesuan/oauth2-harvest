# Harvest Provider for OAuth 2.0 Client

[![Latest Stable Version](https://poser.pugx.org/nilesuan/oauth2-harvest/v/stable)](https://packagist.org/packages/nilesuan/oauth2-harvest)
[![License](https://poser.pugx.org/nilesuan/oauth2-harvest/license)](https://packagist.org/packages/nilesuan/oauth2-harvest)
[![Build Status](https://travis-ci.org/nilesuan/oauth2-harvest.svg?branch=master)](https://travis-ci.org/nilesuan/oauth2-harvest)
[![Code Coverage](https://scrutinizer-ci.com/g/nilesuan/oauth2-harvest/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/nilesuan/oauth2-harvest/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nilesuan/oauth2-harvest/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nilesuan/oauth2-harvest/?branch=master)
[![Total Downloads](https://poser.pugx.org/nilesuan/oauth2-harvest/downloads)](https://packagist.org/packages/nilesuan/oauth2-harvest)

This package provides Harvest's OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

## Installation

To install, use composer:

```
composer require nilesuan/oauth2-harvest
```

## Usage

Usage is the same as The League's OAuth client, using `\Nilesuan\OAuth2\Client\Provider\Harvest` as the provider.

### Authorization Code Flow

```php
$provider = new Nilesuan\OAuth2\Client\Provider\Harvest([
    'clientId'          => '{harvest-client-id}',
    'clientSecret'      => '{harvest-client-secret}',
    'redirectUri'       => 'https://example.com/callback-url'
]);

if (!isset($_GET['code'])) {

    // If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl();
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: '.$authUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} else {

    // Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    // Optional: Now you have a token you can look up a users profile data
    try {

        // We got an access token, let's now get the user's details
        $user = $provider->getResourceOwner($token);

        // Use these details to create a new profile
        printf('Hello %s!', $user->getName());

    } catch (Exception $e) {

        // Failed to get user details
        exit('Oh dear...');
    }

    // Use this to interact with an API on the users behalf
    echo $token->getToken();
}
```

## Testing

``` bash
$ ./vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](https://github.com/nilesuan/oauth2-harvest/blob/master/CONTRIBUTING.md) for details.


## Credits

- [Nile Suan](https://github.com/nilesuan)
- [All Contributors](https://github.com/nilesuan/oauth2-harvest/contributors)


## License

The MIT License (MIT). Please see [License File](https://github.com/nilesuan/oauth2-harvest/blob/master/LICENSE) for more information.
