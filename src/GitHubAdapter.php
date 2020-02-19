<?php /** @noinspection PhpInternalEntityUsedInspection, PhpUndefinedClassInspection */

declare(strict_types = 1);

namespace Radiergummi\FlysystemGitHub;

use GuzzleHttp\Exception\GuzzleException;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Config;
use League\Flysystem\Util\MimeType;
use LogicException;

use function basename;

class GitHubAdapter extends AbstractAdapter
{
    use NotSupportingVisibilityTrait;

    /**
     * @var \Radiergummi\FlysystemGitHub\Client
     */
    protected $client;

    /**
     * GitlabAdapter constructor.
     *
     * @param \Radiergummi\FlysystemGitHub\Client $client
     * @param string                              $prefix
     */
    public function __construct(Client $client, string $prefix = '')
    {
        $this->client = $client;
        $this->setPathPrefix($prefix);
    }

    /**
     * @inheritDoc
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function write($path, $contents, Config $config)
    {
        try {
            $this->client->upload(
                $this->applyPathPrefix($path),
                $contents,
                'Create ' . basename($path)
            );

            return $this->client->read($this->applyPathPrefix($path));
        } catch (GuzzleException $exception) {
            return false;
        }
    }

    /**
     * @inheritDoc
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function writeStream($path, $resource, Config $config)
    {
        try {
            $this->client->uploadStream(
                $this->applyPathPrefix($path),
                $resource,
                'Create ' . basename($path)
            );

            return $this->client->read($this->applyPathPrefix($path));
        } catch (GuzzleException $exception) {
            var_dump($exception->getMessage());

            return false;
        }
    }

    /**
     * @inheritDoc
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function update($path, $contents, Config $config)
    {
        try {
            $this->client->upload(
                $this->applyPathPrefix($path),
                $contents,
                'Update ' . basename($path),
                true
            );

            return $this->client->read($this->applyPathPrefix($path));
        } catch (GuzzleException $exception) {
            return false;
        }
    }

    /**
     * @inheritDoc
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function updateStream($path, $resource, Config $config)
    {
        try {
            $this->client->uploadStream(
                $this->applyPathPrefix($path),
                $resource,
                'Update ' . basename($path),
                true
            );

            return $this->client->read($this->applyPathPrefix($path));
        } catch (GuzzleException $exception) {
            return false;
        }
    }

    /**
     * @inheritDoc
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function rename($path, $newPath): bool
    {
        try {
            $file = $this->client->read($this->applyPathPrefix($path));

            $this->client->upload(
                $this->applyPathPrefix($newPath),
                $file->getDecodedContents(),
                'Create ' . basename($newPath)
            );

            $this->client->delete($file, 'Delete ' . basename($path));

            return true;
        } catch (GuzzleException $exception) {
            return false;
        }
    }

    /**
     * @inheritDoc
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function copy($path, $newPath): bool
    {
        try {
            $file = $this->client->read($this->applyPathPrefix($path));

            $this->client->upload(
                $this->applyPathPrefix($newPath),
                $file->getDecodedContents(),
                'Create ' . basename($path)
            );

            return true;
        } catch (GuzzleException $exception) {
            return false;
        }
    }

    /**
     * @inheritDoc
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function delete($path): bool
    {
        try {
            $file = $this->client->read($this->applyPathPrefix($path));

            $this->client->delete($file, 'Delete ' . basename($path));

            return true;
        } catch (GuzzleException $exception) {
            return false;
        }
    }

    /**
     * @inheritDoc
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function deleteDir($dirname): bool
    {
        $files = $this->listContents($this->applyPathPrefix($dirname));
        $status = true;

        foreach ($files as $file) {
            if ($file->getType() !== 'directory') {
                try {
                    /** @noinspection PhpParamsInspection */
                    $this->client->delete($file, "Delete {$file->getName()}");
                } catch (GuzzleException $exception) {
                    $status = false;
                }
            }
        }

        return $status;
    }

    /**
     * @inheritDoc
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function createDir($dirname, Config $config)
    {
        $path = rtrim($dirname, '/') . '/.gitkeep';
        $response = $this->write($this->applyPathPrefix($path), '', $config);

        return $response !== false;
    }

    /**
     * @inheritDoc
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function has($path): bool
    {
        try {
            $this->client->read($this->applyPathPrefix($path));
        } catch (GuzzleException $exception) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function read($path)
    {
        try {
            $file = $this->client->read($this->applyPathPrefix($path));

            return [
                'type' => 'file',
                'contents' => $file->getDecodedContents(),
                'path' => $file->getPath(),
                'size' => $file->getSize(),
                'hash' => $file->getHash(),
            ];
        } catch (GuzzleException $exception) {
            return false;
        }
    }

    /**
     * @inheritDoc
     * @throws \LogicException
     * @throws \RuntimeException
     */
    public function readStream($path)
    {
        try {
            $file = $this->client->read($this->applyPathPrefix($path));

            return [
                'type' => 'file',
                'stream' => $file->getStream(),
                'path' => $file->getPath(),
                'size' => $file->getSize(),
                'hash' => $file->getHash(),
            ];
        } catch (GuzzleException $exception) {
            return false;
        }
    }

    /**
     * @inheritDoc
     * @return \Radiergummi\FlysystemGitHub\Entry[]
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function listContents($directory = '', $recursive = false): array
    {
        try {
            return $this->client->tree($this->applyPathPrefix($directory), $recursive);
        } catch (GuzzleException $exception) {
            return [];
        }
    }

    /**
     * @inheritDoc
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function getMetadata($path)
    {
        try {
            $file = $this->client->read($this->applyPathPrefix($path));
        } catch (GuzzleException $exception) {
            return false;
        }

        /** @noinspection PhpInternalEntityUsedInspection */
        return [
            'mimetype' => MimeType::detectByFilename($file->getPath()),
            'size' => $file->getSize(),
        ];
    }

    /**
     * @inheritDoc
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function getSize($path): int
    {
        return $this->getMetadata($this->applyPathPrefix($path))['size'] ?? 0;
    }

    /**
     * @inheritDoc
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function getMimetype($path): string
    {
        return $this->getMetadata($this->applyPathPrefix($path))['mimetype'] ?? '';
    }

    /**
     * @inheritDoc
     * @throws \LogicException
     */
    public function getTimestamp($path): void
    {
        throw new LogicException('GitHub API does not support timestamps.');
    }
}
