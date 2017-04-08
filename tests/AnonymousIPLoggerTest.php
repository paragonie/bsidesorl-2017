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

        $this->forceRotateKey();

        $pieces = \explode(PHP_EOL, file_get_contents($this->file));

        $this->assertNotEquals(
            $pieces[0],
            $pieces[1],
            'Different IPs should result in different hashes'
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
