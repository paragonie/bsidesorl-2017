<?php
declare(strict_types=1);
namespace ParagonIE\BsidesOrl2017Talk;
use ParagonIE\BsidesOrl2017Talk\Util\HiddenString;
use ParagonIE\ConstantTime\{
    Base64UrlSafe,
    Binary
};

/**
 * Class EncryptedSearch
 * @package ParagonIE\BsidesOrl2017Talk
 */
class EncryptedSearch
{
    /**
     * @const int
     */
    const MIN_CIPHERTEXT_LENGTH = \Sodium\CRYPTO_SECRETBOX_NONCEBYTES + \Sodium\CRYPTO_SECRETBOX_MACBYTES;

    /**
     * @var bool
     */
    protected $highEntropy;

    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * @var HiddenString
     */
    protected $encKey;

    /**
     * @var HiddenString
     */
    protected $indexKey;

    /**
     * EncryptedSearch constructor.
     * @param \PDO $db
     * @param string $keyDir
     * @param bool $highEntropy
     */
    public function __construct(\PDO $db, string $keyDir = '', bool $highEntropy = false)
    {
        /*
         * Extra precaution:
         *
         * - Don't use emulated prepared statements; use ACTUAL prepared statements
         * - If an error occurs on PDOStatement::execute(), throw an exception
         *   rather than returning false
         *
         * Secure-by-default is how Paragon Initiative Enterprises rolls.
         */
        $this->pdo = $db;
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        /*
         * Load the encryption key (n.b. it's not hard-codede)
         */
        if (\file_exists($keyDir . '/enc.key')) {
            $encKey = Base64UrlSafe::decode(\file_get_contents($keyDir . '/enc.key'));
        } else {
            $encKey = \random_bytes(\Sodium\CRYPTO_SECRETBOX_KEYBYTES);
            \file_put_contents($keyDir . '/enc.key', Base64UrlSafe::encode($encKey));
        }
        $this->encKey = new HiddenString($encKey);

        /*
         * Load the index key
         */
        if (\file_exists($keyDir . '/index.key')) {
            $indexKey = Base64UrlSafe::decode(\file_get_contents($keyDir . '/index.key'));
        } else {
            $indexKey = \random_bytes(\Sodium\CRYPTO_AUTH_KEYBYTES);
            \file_put_contents($keyDir . '/index.key', Base64UrlSafe::encode($indexKey));
        }
        $this->indexKey = new HiddenString($indexKey);

        /*
         * Is this a high-entropy string?
         *
         *  - YES -> Use HMAC
         *  -  NO -> Use Argon2
         */
        $this->highEntropy = $highEntropy;
    }

    /**
     * @param HiddenString $ssn
     * @param int $userID
     * @return bool
     */
    public function storeSSN(HiddenString $ssn, int $userID): bool
    {
        $this->pdo->beginTransaction();
        $stmt = $this->pdo->prepare('UPDATE people SET ssn = ?, ssn_blindindex = ? WHERE userid = ?');

        $stmt->execute([
            $this->encrypt($ssn),
            $this->getBlindIndex($ssn),
            $userID
        ]);

        return $this->pdo->commit();
    }

    /**
     * @param HiddenString $ssn
     * @return int|null
     */
    public function getUserIDBySSN(HiddenString $ssn): ?int
    {
        $stmt = $this->pdo->prepare('SELECT userid FROM people WHERE ssn_blindindex = ?');
        $stmt->execute([
            $this->getBlindIndex($ssn)
        ]);
        $userID = $stmt->fetchColumn(0);
        if (empty($userID)) {
            return null;
        }
        return (int) $userID;
    }

    /**
     * @param HiddenString $input
     * @return string
     */
    public function getBlindIndex(HiddenString $input): string
    {
        if (!$this->highEntropy) {
            // Reduce to CRYPTO_PWHASH_SALTBYTES
            $salt = \Sodium\crypto_generichash(
                $this->indexKey->getString(),
                '',
                \Sodium\CRYPTO_PWHASH_SALTBYTES
            );
            // Use Argon2
            return Base64UrlSafe::encode(
                \Sodium\crypto_pwhash(
                    33,
                    $input->getString(),
                    $salt,
                    \Sodium\CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                    \Sodium\CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
                )
            );
        }

        // HMAC is sufficient
        return Base64UrlSafe::encode(
            \Sodium\crypto_auth(
                $input->getString(),
                $this->indexKey->getString()
            )
        );
    }

    /**
     * Authenticated encryption
     *
     * @param HiddenString $ssn
     * @return string
     */
    public function encrypt(HiddenString $ssn): string
    {
        $nonce = \random_bytes(\Sodium\CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = \Sodium\crypto_secretbox(
            $ssn->getString(),
            $nonce,
            $this->encKey->getString()
        );
        return Base64UrlSafe::encode($nonce . $ciphertext);
    }

    /**
     * Decrypt a message. Uses Xsalsa20Poly1305.
     *
     * Formally, this provides authenticated encryption. Altering the nonce
     * doesn't give you any useful attacks.
     *
     * @param string $ciphertext
     * @return HiddenString
     * @throws \Exception
     */
    public function decrypt(string $ciphertext): HiddenString
    {
        $decoded = Base64UrlSafe::decode($ciphertext, true);
        if (Binary::safeStrlen($decoded) < self::MIN_CIPHERTEXT_LENGTH) {
            throw new \Error('Ciphertext too short');
        }

        $nonce = Binary::safeSubstr($decoded, 0, \Sodium\CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = \Sodium\crypto_secretbox_open(
            Binary::safeSubstr($decoded, \Sodium\CRYPTO_SECRETBOX_NONCEBYTES),
            $nonce,
            $this->encKey->getString()
        );
        if ($plaintext === false) {
            throw new \Exception('Invalid MAC');
        }
        return new HiddenString($plaintext);
    }
}
