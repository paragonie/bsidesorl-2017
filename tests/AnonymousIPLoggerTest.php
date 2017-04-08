<?php

use ParagonIE\BsidesOrl2017Talk\AnonymousIPLogger;
use PHPUnit\Framework\TestCase;

class AnonymousIPLoggerTest extends TestCase
{
    /**
     * @var string
     */
    private $file = '';

    /**
     * @var AnonymousIPLogger
     */
    private $logger;

    /**
     * @var string
     */
    private $keyFiles;

    public function setUp()
    {
        $this->file = \dirname(__DIR__) . '/data/test-access.log';
        $this->keyFiles = \dirname(__DIR__) . '/data/log-keys';

        $this->logger = new AnonymousIPLogger($this->file, $this->keyFiles);
    }

    /**
     * Generate a new random key
     */
    protected function forceRotateKey()
    {
        unset($this->logger);
        \unlink(\dirname(__DIR__) . '/data/log-keys/' . \date('Ymd'));
        $this->logger = new AnonymousIPLogger($this->file, $this->keyFiles);
    }

    /**
     *
     */
    public function testToday()
    {
        $this->logger->info('test', ['ip' => '127.0.0.1']);
        $this->logger->info('test', ['ip' => '127.0.0.2']);
        $this->logger->info('test', ['ip' => '127.0.0.1']);

        $this->forceRotateKey();

        $pieces = \explode(PHP_EOL, file_get_contents($this->file));
        $decoded = [
            \json_decode($pieces[0], true),
            \json_decode($pieces[1], true),
            \json_decode($pieces[2], true),
        ];

        $this->assertNotEquals(
            $decoded[0]['context']['ip'],
            $decoded[1]['context']['ip'],
            'Different IPs should result in different hashes'
        );

        $this->assertNotEquals(
            $decoded[0]['context']['ip'],
            $decoded[2]['context']['ip'],
            'The same IP should result in the same hash'
        );

    }

    public function tearDown()
    {
        \unlink(\dirname(__DIR__) . '/data/log-keys/' . \date('Ymd'));
        if (\file_exists($this->file . '.old')) {
            \unlink($this->file . '.old');
        }
        /*
        \rename($this->file, $this->file . '.old');
        */
    }
}
