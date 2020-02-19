<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Radiergummi\FlysystemGitHub\Tests;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;
use LogicException;
use PHPUnit\Framework\TestCase;
use Radiergummi\FlysystemGitHub\Client;
use Radiergummi\FlysystemGitHub\GitHubAdapter;

use function bin2hex;
use function fopen;
use function getenv;
use function random_bytes;

class GitHubAdapterTest extends TestCase
{
    /**
     * @var \Radiergummi\FlysystemGitHub\GitHubAdapter
     */
    private $adapter;

    public function test__construct(): void
    {
        $this->assertInstanceOf(GitHubAdapter::class, $this->adapter);
        $this->assertInstanceOf(AbstractAdapter::class, $this->adapter);
    }

    public function testRead(): void
    {
        $readme = $this->adapter->read('README.md');
        $readmeContents = $readme['contents'];

        $this->assertSame(
            "# flysystem-github-storage-test\nA test repository for the flysystem-github-storage adapter\n",
            $readmeContents
        );
    }

    public function testWrite(): void
    {
        $contents = bin2hex(random_bytes(32));
        $newFile = $this->adapter->write('foo.txt', $contents, new Config());

        $this->assertNotFalse($newFile);
        $this->assertSame($contents, $this->adapter->read('foo.txt')['contents']);

        $this->adapter->delete('foo.txt');
    }

    public function testHas(): void
    {
        $this->assertFalse($this->adapter->has('bar.txt'));
        $this->adapter->write('bar.txt', (string)time(), new Config());
        $this->assertTrue($this->adapter->has('bar.txt'));
        $this->adapter->delete('bar.txt');
        $this->assertFalse($this->adapter->has('bar.txt'));
    }

    public function testUpdate(): void
    {
        $contents1 = bin2hex(random_bytes(32));
        $contents2 = bin2hex(random_bytes(32));
        $newFile = $this->adapter->write('baz.txt', $contents1, new Config());

        $this->assertNotFalse($newFile);
        $this->assertSame($contents1, $this->adapter->read('baz.txt')['contents']);

        $updatedFile = $this->adapter->update('baz.txt', $contents2, new Config());

        $this->assertNotFalse($updatedFile);
        $this->assertSame($contents2, $this->adapter->read('baz.txt')['contents']);

        $this->adapter->delete('baz.txt');
    }

    public function testDelete(): void
    {
        $this->adapter->copy('README.md', 'SECOND_README.md');
        $this->assertTrue($this->adapter->has('SECOND_README.md'));
        $this->adapter->delete('SECOND_README.md');
        $this->assertFalse($this->adapter->has('SECOND_README.md'));
    }

    public function testCopy(): void
    {
        $this->assertFalse($this->adapter->has('SECOND_README.md'));
        $this->adapter->copy('README.md', 'SECOND_README.md');
        $this->assertTrue($this->adapter->has('SECOND_README.md'));
        $this->adapter->delete('SECOND_README.md');
    }

    public function testRename(): void
    {
        $this->assertTrue($this->adapter->has('README.md'));
        $this->assertNotTrue($this->adapter->has('DONOTREADME.md'));

        $this->adapter->rename('README.md', 'DONOTREADME.md');

        $this->assertTrue($this->adapter->has('DONOTREADME.md'));
        $this->assertNotTrue($this->adapter->has('README.md'));

        $this->adapter->rename('DONOTREADME.md', 'README.md');
    }

    public function testGetMetadata(): void
    {
        $metadata = $this->adapter->getMetadata('README.md');

        $this->assertSame(91, $metadata['size']);
        $this->assertSame('text/plain', $metadata['mimetype']);
    }

    public function testGetSize(): void
    {
        $this->assertSame(91, $this->adapter->getSize('README.md'));
    }

    public function testGetMimetype(): void
    {
        $this->assertSame('text/plain', $this->adapter->getMimetype('README.md'));
    }

    public function testGetTimestamp(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('GitHub API does not support timestamps.');

        $this->adapter->getTimestamp('foo.txt');
    }

    public function testListContents(): void
    {
        $files = $this->adapter->listContents();

        $this->assertCount(3, $files);
    }

    public function testListContentsRecursively(): void
    {
        $this->adapter->createDir('/xyz', new Config());
        $files = $this->adapter->listContents('', true);

        $this->assertCount(5, $files);

        $this->adapter->deleteDir('/xyz');
    }

    public function testDeleteDir(): void
    {
        $this->assertFalse($this->adapter->has('/foo/.gitkeep'));
        $this->assertFalse($this->adapter->has('/foo/bar/.gitkeep'));
        $this->assertTrue($this->adapter->createDir('/foo', new Config()));
        $this->assertTrue($this->adapter->createDir('/foo/bar', new Config()));
        $this->assertTrue($this->adapter->has('/foo/bar/.gitkeep'));
        $this->adapter->deleteDir('/foo/bar');
        $this->assertFalse($this->adapter->has('/foo/bar/.gitkeep'));
        $this->adapter->deleteDir('/foo');
    }

    public function testCreateDir(): void
    {
        $this->assertFalse($this->adapter->has('/foo/.gitkeep'));
        $this->assertTrue($this->adapter->createDir('/foo', new Config()));
        $this->assertTrue($this->adapter->has('/foo/.gitkeep'));
        $this->adapter->delete('/foo/.gitkeep');
    }

    public function testReadStream(): void
    {
        $stream = $this->adapter->readStream('README.md')['stream'];

        $this->assertIsResource($stream);
    }

    public function testWriteStream(): void
    {
        $stream = fopen(__DIR__ . '/fixtures/test-stream.txt', 'rb+');

        $file = $this->adapter->writeStream('test-stream.txt', $stream, new Config());

        $this->assertSame("foo\nbar\n", $file->getDecodedContents());
        $this->assertSame($file->getName(), 'test-stream.txt');

        $this->adapter->delete('test-stream.txt');
    }

    public function testUpdateStream(): void
    {
        $stream = fopen(__DIR__ . '/fixtures/test-stream.txt', 'rb+');

        $file = $this->adapter->writeStream('test-stream.txt', $stream, new Config());

        $this->assertNotFalse($file);
        $this->assertSame("foo\nbar\n", $file->getDecodedContents());
        $this->assertSame($file->getName(), 'test-stream.txt');

        $updateStream = fopen(__DIR__ . '/fixtures/test-stream-update.txt', 'rb+');

        $file = $this->adapter->updateStream('test-stream.txt', $updateStream, new Config());

        $this->assertSame("foo\nbar\nbaz\n", $file->getDecodedContents());
        $this->assertSame($file->getName(), 'test-stream.txt');

        $this->adapter->delete('test-stream.txt');
    }

    protected function setUp(): void
    {
        $token = getenv('GITHUB_TEST_TOKEN');
        $version = getenv('GITHUB_TEST_REPO');
        $client = new Client($token, $version, 'master', null);
        $this->adapter = new GitHubAdapter($client);
    }
}
