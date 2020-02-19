<?php /** @noinspection PhpUndefinedClassInspection, PhpDocRedundantThrowsInspection */

declare(strict_types = 1);

namespace Radiergummi\FlysystemGitHub;

use GuzzleHttp\Client as HttpClient;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function array_map;
use function array_merge;
use function base64_encode;
use function basename;
use function http_build_query;
use function json_decode;
use function json_encode;
use function urldecode;
use function urlencode;

class Client
{
    private const API_VERSION = 'v3';

    private const DEFAULT_BASE_URL = 'https://api.github.com';

    private const FIELD_TARGET = 'target';

    private const FIELD_TYPE = 'type';

    private const PARAM_BRANCH = 'branch';

    private const PARAM_COMMIT_MESSAGE = 'message';

    private const PARAM_CONTENT = 'content';

    private const PARAM_HASH = 'sha';

    private const TYPE_DIRECTORY = 'dir';

    private const TYPE_FILE = 'file';

    private const TYPE_SUBMODULE = 'submodule';

    private const TYPE_SYMLINK = 'symlink';

    /**
     * @var string
     */
    protected $token;

    /**
     * @var string
     */
    protected $repository;

    /**
     * @var string
     */
    protected $branch;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var \GuzzleHttp\Client
     */
    private $httpClient;

    /**
     * Client constructor.
     *
     * @param string                  $token
     * @param string                  $repository
     * @param string                  $branch
     * @param string                  $baseUrl
     * @param \GuzzleHttp\Client|null $guzzleClient
     */
    public function __construct(
        string $token,
        string $repository,
        string $branch = 'master',
        ?string $baseUrl = null,
        ?HttpClient $guzzleClient = null
    ) {
        $this->token = $token;
        $this->repository = $repository;
        $this->branch = $branch;
        $this->baseUrl = $baseUrl ?? self::DEFAULT_BASE_URL;

        if ($guzzleClient) {
            $this->httpClient = $guzzleClient;
        }
    }

    /**
     * @param string $path
     *
     * @return \Radiergummi\FlysystemGitHub\File
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function read(string $path): Entry
    {
        $uri = $this->buildContentsUri($path);
        $response = $this->request('GET', $uri);
        $responseBody = $this->parseResponse($response);

        // For directories, the response will contain an array of file objects instead of a file object,
        // so we can simply default to the directory type
        switch ($responseBody[self::FIELD_TYPE] ?? self::TYPE_DIRECTORY) {
            case self::TYPE_FILE:
                return new File($responseBody);

            case self::TYPE_DIRECTORY:
                return new Directory($responseBody[0]);

            case self::TYPE_SYMLINK:
                return $this->read($responseBody[self::FIELD_TARGET]);

            case self::TYPE_SUBMODULE:
                throw new RuntimeException('File is a git submodule');

            default:
                throw new RuntimeException("File has unknown type '{$responseBody[self::FIELD_TYPE]}'");
        }
    }

    /**
     * @param string $path
     * @param string $contents
     * @param string $commitMessage
     * @param bool   $update
     *
     * @return array
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @see https://developer.github.com/v3/repos/contents/#create-or-update-a-file
     */
    public function upload(string $path, string $contents, string $commitMessage, bool $update = false): array
    {
        $body = [
            self::PARAM_CONTENT => base64_encode($contents),
            self::PARAM_COMMIT_MESSAGE => $commitMessage,
            self::PARAM_BRANCH => $this->branch,
        ];

        // As per the GitHub documentation, on updates the SHA hash of the file contents must be included.
        if ($update) {
            $originalFile = $this->read($path);

            $body[self::PARAM_HASH] = $originalFile->getHash();
        }

        $uri = $this->buildContentsUri($path);
        $response = $this->request('PUT', $uri, $body);

        return $this->parseResponse($response);
    }

    /**
     * Uploads a file from stream
     *
     * @param string   $path
     * @param resource $resource
     * @param string   $commitMessage
     * @param bool     $update
     *
     * @return array
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function uploadStream(string $path, $resource, string $commitMessage, bool $update = false): array
    {
        if (! is_resource($resource)) {
            throw new InvalidArgumentException(sprintf(
                'Argument must be a valid resource type. %s given.',
                gettype($resource)
            ));
        }

        return $this->upload(
            $path,
            stream_get_contents($resource),
            $commitMessage,
            $update
        );
    }

    /**
     * @param \Radiergummi\FlysystemGitHub\File $file
     * @param string                            $commitMessage
     *
     * @throws \InvalidArgumentException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function delete(File $file, string $commitMessage): void
    {
        $uri = $this->buildContentsUri($file->getPath());

        $this->request('DELETE', $uri, [
            'message' => $commitMessage,
            'sha' => $file->getHash(),
        ]);
    }

    /**
     * @param string $path
     * @param bool   $recursive
     *
     * @return \Radiergummi\FlysystemGitHub\Entry[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function tree(string $path, bool $recursive = false): array
    {
        if ($path === '/') {
            $path = '';
        }

        $uri = ! $recursive
            ? $this->buildContentsUri($path)
            : $this->buildTreeUri($path === '' ? 'master' : $this->read($path)->getHash(), [
                'recursive' => 1,
            ]);

        $response = $this->request('GET', $uri);
        $responseBody = $this->parseResponse($response);

        return array_map(static function ($node): Entry {
            $augmentedNode = array_merge($node, [
                'name' => basename($node['path']),
            ]);

            if ($node['type'] !== 'tree') {
                return new File($augmentedNode);
            }

            return new Directory($augmentedNode);
        }, $responseBody['tree'] ?? []);
    }

    /**
     * Retrieves the HTTP client instance if it already exists, creates a new one otherwise.
     * The client is initialized with the authorization header and version information pre-configured.
     *
     * @return \GuzzleHttp\Client Guzzle client instance
     * @throws \InvalidArgumentException Not happening here
     */
    final protected function getHttpClient(): HttpClient
    {
        if (! $this->httpClient) {
            $version = self::API_VERSION;

            $this->httpClient = new HttpClient([
                'headers' => [
                    'authorization' => "token {$this->token}",
                    'accept' => "application/vnd.github.{$version}+json",
                ],
            ]);
        }

        return $this->httpClient;
    }

    /**
     * Parses a response and returns the extracted body data, if possible.
     * Defaults to the string contents otherwise.
     *
     * @param \Psr\Http\Message\ResponseInterface $response Response instance as returned from the HTTP client
     *
     * @return array|string Array decoded from the response JSON, raw string contents otherwise
     * @throws \RuntimeException If unable to read the response body or an error occurs while reading
     */
    final protected function parseResponse(ResponseInterface $response)
    {
        $contentType = $response->getHeaderLine('content-type');
        $contents = (string)$response->getBody();

        if (strpos($contentType, 'application/json') !== false) {
            return json_decode($contents, true);
        }

        return $contents;
    }

    /**
     * Performs a request against the GitHub API
     *
     * @param string $method Request method
     * @param string $uri    Request URI
     * @param array  $body   Request body
     *
     * @return \Psr\Http\Message\ResponseInterface Response from the GitHub API
     * @throws \InvalidArgumentException
     */
    final protected function request(
        string $method,
        string $uri,
        array $body = []
    ): ResponseInterface {
        $options = $method !== 'GET'
            ? ['body' => json_encode(array_merge(['branch' => $this->branch], $body))]
            : [];

        return $this->getHttpClient()->request($method, $uri, $options);
    }

    /**
     * Builds the request URI for a file
     *
     * @param string $filePath    File path relative to the repository root
     * @param array  $queryParams Optional query parameters
     *
     * @return string Full request URI
     */
    private function buildContentsUri(string $filePath, array $queryParams = []): string
    {
        $filePath = urlencode($filePath);
        $queryString = $this->buildQueryString($queryParams);

        return "{$this->baseUrl}/repos/{$this->repository}/contents/{$filePath}{$queryString}";
    }

    /**
     * Builds the query string and merges the branch
     *
     * @param array $queryParams Query parameters as an associative array
     *
     * @return string|null Full query string with leading delimiter or null if empty
     */
    private function buildQueryString(array $queryParams): ?string
    {
        $queryParams = array_merge(['ref' => $this->branch], $queryParams);
        $queryParams = array_map('urlencode', $queryParams);

        if (isset($queryParams['path'])) {
            $queryParams['path'] = urldecode($queryParams['path']);
        }

        $queryString = http_build_query($queryParams);

        return ! empty($queryParams) ? "?{$queryString}" : null;
    }

    /**
     * Builds the request URI for a tree item
     *
     * @param string $hash        SHA1 hash of the tree item
     * @param array  $queryParams Optional query parameters
     *
     * @return string Full request URI
     */
    private function buildTreeUri(string $hash, array $queryParams = []): string
    {
        $queryString = $this->buildQueryString($queryParams);

        return "{$this->baseUrl}/repos/{$this->repository}/git/trees/{$hash}{$queryString}";
    }
}
