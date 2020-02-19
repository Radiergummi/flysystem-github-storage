GitHub Adapter for Flysystem
============================
This is a GitHub adapter [Flysystem](https://github.com/thephpleague/flysystem). It allows you to use a GitHub 
repository as a storage backend for Flysystem.  
It uses the [Contents API](https://developer.github.com/v3/repos/contents/) of the GitHub API, version 3.

> This adapter was heavily influenced by the marvelous work of [Roy Voetman](https://github.com/RoyVoetman) on his 
> [Gitlab Adapter](https://github.com/RoyVoetman/flysystem-gitlab-storage). Make sure to check it out if you need a 
> Gitlab integration instead.

Installation
------------
```bash
composer require radiergummi/flysystem-github-storage
```

Usage
-----
To use the GitHub storage with Flysystem, you'll need to create a client:
```php
$token = getenv('YOUR_GITHUB_ACCESS_TOKEN');
$repository = getenv('YOUR_GITHUB_REPOSITORY')

$client = new Client($token, $repository);
```

You can then pass that client to the adapter:
```php
use Radiergummi\FlysystemGitHub\GitHubAdapter;$adapter = new GitHubAdapter($client);
```

...and finally, create a Filesystem instance:
```php
use League\Flysystem\Filesystem;$filesystem = new Filesystem($adapter);
```

Check out the [Flysystem documentation](https://flysystem.thephpleague.com/api) for filesystem usage information.

Advanced usage
--------------
The library allows additional constructor arguments for the client:

| Argument                       | Default                  | Description                                                                                           |
|:-------------------------------|:-------------------------|:------------------------------------------------------------------------------------------------------|
| `string $token`                | -                        | Your access token. Must have the `repo` scope.                                                        |
| `string $repository`           | -                        | Name of the repository, including the owner, eg. `acme/repo-name`.                                    |
| `string $branch`               | `master`                 | Name of the branch in your repository.                                                                |
| `string $baseurl`              | `https://api.github.com` | Base URL of the API to use. Pass `null` to use the default.                                           |
| `\Guzzle\Client $guzzleClient` | -                        | Guzzle client to use for the connection. Check the [Custom HTTP Client section](#custom-http-client). |

Custom HTTP Client
------------------
You can optionally pass a custom Guzzle client for the library to use. If you do so, make sure to add the following 
default headers:
 - `authorization`: Should contain your access token, eg. `'authorization' => "token {$token}"`.
 - `content-type`: Should contain the desired API version, eg. `'content-type' => "application/vnd.github.v3+json"`.

Contributions
-------------
Contributions are welcome! The test suite could be expanded and the error handling be improved - alas, the Flysystem 
library does not support a sensible exception system but relies on 90s-style `false` return values. While there's no way
around that, I'm sure one could improve the situation.
