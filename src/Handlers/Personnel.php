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
 * Class Personnel
 * @package ParagonIE\BsidesOrl2017Talk\Handlers
 */
class Personnel extends BaseHandler
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
        return $this->render('personnel.twig', [
            'users' => $this->getAllUsers()
        ]);
    }

    /**
     * @return Response
     * @throws \TypeError
     */
    public function submit(): Response
    {
        if (!empty($_POST['search'])) {
            if (!\is_string($_POST['search'])) {
                throw new \TypeError('Expected string, got ' . \gettype($_POST['search']));
            }
            return $this->render('personnel.twig', [
                'search' => $_POST['search'],
                'users' => $this->searchUsersBySSN(
                    new HiddenString($_POST['search'])
                )
            ]);
        }

        if (!empty($_POST['username']) && !empty($_POST['password']) && !empty($_POST['ssn'])) {
            $this->addUser($_POST);
            if (!empty($_POST['personnelForm'])) {
                switch ($_POST['personnelForm']) {
                    case 'personnel':
                        \header('Location: /personnel');
                        exit;
                    case 'unique-pw':
                        \header('Location: /unique-pw');
                        exit;
                    default:
                        // Nice try
                }
            }
            \header('Location: /personnel'); exit;
        }

        throw new \Exception('Invalid POST');
    }


    /**
     * @param HiddenString $search
     * @return array
     */
    protected function searchUsersBySSN(HiddenString $search): array
    {
        $stmt = $this->db->prepare('SELECT * FROM people WHERE ssn_blindindex = ? ORDER BY userid ASC');
        $stmt->execute([$this->encSearch->getBlindIndex($search)]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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
