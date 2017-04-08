<?php
declare(strict_types=1);
namespace ParagonIE\BsidesOrl2017Talk;

use ParagonIE\ConstantTime\Base64UrlSafe;
use PhpParser\Error;
use Psr\Log\{
    LoggerInterface,
    LogLevel
};

/**
 * Class AnonymousIPLogger
 *
 * Weird problem:
 *
 * - You don't want to store the real IP addresses of your users inside your logs.
 * - However, you want to be able to identify traffic patterns.
 *
 * This is a PSR-3 compliant logger interface that demonstrates the idea.
 *
 * @package ParagonIE\BsidesOrl2017Talk
 */
class AnonymousIPLogger implements LoggerInterface
{
    /**
     * @var string
     */
    protected $todaysKey;

    /**
     * @var resource
     */
    protected $file;

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [];
    }

    /**
     * AnonymousIPLogger constructor.
     * @param string $logFile
     * @param string $keyPath
     */
    public function __construct(string $logFile = '', string $keyPath = '/tmp/ip-keys')
    {
        $file = \fopen($logFile, 'ab');
        if (!\is_resource($file)) {
            throw new Error('Could not open file to append data');
        }
        $this->file = $file;

        $today = (new \DateTime())
            ->format('Ymd');

        if (\file_exists($keyPath . '/' . $today)) {
            $todaysKey = \file_get_contents($keyPath . '/' . $today);
            if (!\is_string($todaysKey)) {
                throw new \TypeError();
            }
            $this->todaysKey = $todaysKey;
        } else {
            $key = \random_bytes(32);
            if (\file_put_contents($keyPath . '/' . $today, $key) === false) {
                throw new Error("Cannot save today's key into directory");
            }
            $this->todaysKey = $key;
        }

        // Automatically clean up old keys:
        $twoDaysAgo = (new \DateTime())
            ->sub(new \DateInterval('P02D'))
            ->format('Ymd');
        if (\file_exists($keyPath . '/' . $twoDaysAgo)) {
            // Get the size of the file:
            /**
             * @var int
             */
            $size = \filesize($keyPath . '/' . $twoDaysAgo);
            if (!\is_int($size)) {
                throw new \TypeError();
            }
            // Zero-fill the file:
            \file_put_contents(
                $keyPath . '/' . $twoDaysAgo,
                \str_repeat("\x00", $size)
            );
            // Unlink it:
            \unlink($keyPath . '/' . $twoDaysAgo);
        }

        /*
         * Running this on a cronjob would be cleaner:
         * cd /tmp/ip-keys
         * find . -mtime +2 | xargs "shred -u"
         */
    }

    /**
     * Close the file handle.
     */
    public function __destruct()
    {
        \fclose($this->file);
    }

    /**
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, array $context = array())
    {
        // Replace
        $context['ip'] = Base64UrlSafe::encode(
                \Sodium\crypto_generichash(
                $context['ip'] ?? $_SERVER['REMOTE_ADDR'],
                $this->todaysKey
            )
        );
        if (isset($context['x-forwarded-for']) || \array_key_exists('X-Forwarded-For', $_SERVER)) {
            $context['x-forwarded-for'] = Base64UrlSafe::encode(
                \Sodium\crypto_generichash(
                    $context['x-forwarded-for'] ?? $_SERVER['X-Forwarded-For'],
                    $this->todaysKey
                )
            );
        }

        // Append to file
        \fwrite($this->file,
            \json_encode([
                'datetime' => (new \DateTime('NOW'))->format(\DateTime::ISO8601),
                'level' => $level,
                'message' => $message,
                'context' => $context
            ]) . PHP_EOL
        );
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function alert($message, array $context = array())
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function critical($message, array $context = array())
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug($message, array $context = array())
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function emergency($message, array $context = array())
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error($message, array $context = array())
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info($message, array $context = array())
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function notice($message, array $context = array())
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning($message, array $context = array())
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }
}
