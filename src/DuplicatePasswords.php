<?php
declare(strict_types=1);
namespace ParagonIE\BsidesOrl2017Talk;

use ParagonIE\BsidesOrl2017Talk\Util\HiddenString;
use ParagonIE\ConstantTime\Base64UrlSafe;
use PhpParser\Error;

/**
 * Class DuplicatePasswords
 * @package ParagonIE\BsidesOrl2017Talk
 */
class DuplicatePasswords
{
    /**
     * @var string
     */
    private $staticSalt = '';

    /**
     * We use 4x the memory cost since our salt is static.
     *
     * @const int
     */
    protected const MEMCOST_STATIC_SALT = \Sodium\CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE << 2;


    /**
     * We use an additional pass since our salt is static.
     *
     * @const int
     */
    protected const OPSCOST_STATIC_SALT = \Sodium\CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE + 1;

    /**
     * DuplicatePasswords constructor.
     * @param string $staticSaltFile
     */
    public function __construct(string $staticSaltFile)
    {
        if (\file_exists($staticSaltFile)) {
            $this->staticSalt = \file_get_contents($staticSaltFile);
            if ($this->staticSalt === false) {
                throw new Error('Could not read the static salt file');
            }
        } else {
            $this->staticSalt = \random_bytes(\Sodium\CRYPTO_PWHASH_SALTBYTES);
            if (file_put_contents($staticSaltFile, $this->staticSalt) === false) {
                throw new Error('Could not save a new static salt');
            }
        }
    }

    /**
     * Returns an array:
     *
     * 0 => password hash for validation
     * 1 => duplicate detector
     *
     * @param HiddenString $passwd
     * @return array<int, string>
     */
    public function hashPassword(HiddenString $passwd): array
    {
        $pwHash = \Sodium\crypto_pwhash_str(
            $passwd->getString(),
            \Sodium\CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            \Sodium\CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
        );

        $dupeDetector = $this->getPasswordIdentifier($passwd);

        return [$pwHash, $dupeDetector];
    }

    /**
     * @param HiddenString $passwd
     * @return string
     */
    public function getPasswordIdentifier(HiddenString $passwd): string
    {
        return Base64UrlSafe::encode(
            \Sodium\crypto_pwhash(
                33,
                $passwd->getString(),
                $this->staticSalt,
                self::OPSCOST_STATIC_SALT,
                self::MEMCOST_STATIC_SALT
            )
        );
    }
}
