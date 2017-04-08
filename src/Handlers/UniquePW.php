<?php
declare(strict_types=1);

namespace ParagonIE\BsidesOrl2017Talk\Handlers;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use ParagonIE\BsidesOrl2017Talk\{
    BaseHandler,
    DuplicatePasswords,
    EncryptedSearch
};
use ParagonIE\BsidesOrl2017Talk\Util\HiddenString;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Psr\Log\LoggerInterface;

/**
 * Class UniquePW
 * @package ParagonIE\BsidesOrl2017Talk\Handlers
 */
class UniquePW extends BaseHandler
{
    /**
     * @var EncryptedSearch
     */
    protected $encSearch;

    /**
     * @var DuplicatePasswords
     */
    protected $pwhash;

    public function __construct(ServerRequest $request, LoggerInterface $logger)
    {
        parent::__construct($request, $logger);
        $this->pwhash = new DuplicatePasswords(BSIDES_ROOT . '/data/live/static-salt');
        $this->encSearch = new EncryptedSearch($this->db, BSIDES_ROOT . '/data/live');
    }

    public function index(): Response
    {
        return $this->render('passwords.twig', [
            'users' => $this->getAllUsers()
        ]);
    }

    /**
     * @return array
     */
    protected function getAllUsers(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM people ORDER BY userid ASC');
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param array $post
     * @return bool
     */
    protected function addUser(array $post): bool
    {
        list($hash, $dupe) = $this->pwhash->hashPassword(new HiddenString($post['password']));

        $stmt = $this->db->prepare('INSERT INTO people (username, realname, ssn, ssn_blindindex, passwd, dupecheck) VALUES (?, ?, ?, ?, ?, ?);');
        return $stmt->execute([
            $post['username'] ?? Base64UrlSafe::encode(\random_bytes(33)),
            $post['realname'] ?? '',
            $this->encSearch->encrypt(new HiddenString($post['ssn'])),
            $this->encSearch->getBlindIndex(new HiddenString($post['ssn'])),
            $hash,
            $dupe
        ]);
    }
}
