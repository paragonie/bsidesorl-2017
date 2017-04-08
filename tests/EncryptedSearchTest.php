<?php

use ParagonIE\BsidesOrl2017Talk\EncryptedSearch;
use ParagonIE\BsidesOrl2017Talk\Util\HiddenString;
use PHPUnit\Framework\TestCase;

class EncryptedSearchTest extends TestCase
{
    /**
     * @var string
     */
    private $dir = '';

    /**
     * @var PDO
     */
    private $pdo;

    /**
     * @var EncryptedSearch
     */
    private $encSearch;

    /**
     * @var string
     */
    private $randomSSN;

    public function setUp()
    {
        $this->dir = \dirname(__DIR__) . '/data';
        $this->pdo = new \PDO('sqlite:' . \realpath($this->dir) . '/test-anon-ip.sqlite');
        $this->encSearch = new EncryptedSearch($this->pdo, $this->dir, true);

        /* Create table */
        $this->pdo->exec("CREATE TABLE people (
            userid INTEGER PRIMARY KEY ASC,
            username TEXT,
            ssn TEXT,
            ssn_blindindex TEXT,
            realname TEXT
        );");

        $createStmt =  $this->pdo->prepare('INSERT INTO people (username, realname, ssn, ssn_blindindex) VALUES (?, ?, ?, ?);');

        $ssn = new HiddenString('123-45-6789');
        $createStmt->execute([
            'jsmith',
            'Jane Smith',
            $this->encSearch->encrypt($ssn),
            $this->encSearch->getBlindIndex($ssn)
        ]);

        $ssn = new HiddenString('987-65-4321');
        $createStmt->execute([
            'jdoe',
            'John Doe',
            $this->encSearch->encrypt($ssn),
            $this->encSearch->getBlindIndex($ssn)
        ]);

        $this->randomSSN =
            \str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT) .
                '-' .
            \str_pad((string) random_int(0, 99), 3, '0', STR_PAD_LEFT) .
                '-' .
            \str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $ssn = new HiddenString($this->randomSSN);
        $createStmt->execute([
            'anonymous',
            'Anonymous',
            $this->encSearch->encrypt($ssn),
            $this->encSearch->getBlindIndex($ssn)
        ]);
    }

    /**
     *
     */
    public function testExactSearch()
    {
        $janeSmith = $this->encSearch->getUserIDBySSN(new HiddenString('123-45-6789'));
        $this->assertNotNull($janeSmith, 'Jane Smith');

        $johnDoe = $this->encSearch->getUserIDBySSN(new HiddenString('987-65-4321'));
        $this->assertNotNull($johnDoe, 'John Doe');

        $anonymous = $this->encSearch->getUserIDBySSN(new HiddenString($this->randomSSN));
        $this->assertNotNull($anonymous, 'Anonymous');

        $this->assertNull(
            $this->encSearch->getUserIDBySSN(new HiddenString('invalid entry')),
            'Invalid entry should return null'
        );

        $this->assertSame(
            '123-45-6789',
            $this->decryptVerify($janeSmith)->getString()
        );
        $this->assertSame(
            '987-65-4321',
            $this->decryptVerify($johnDoe)->getString()
        );
        $this->assertSame(
            $this->randomSSN,
            $this->decryptVerify($anonymous)->getString()
        );
    }

    /**
     *
     */
    public function decryptVerify(int $userID = 0): HiddenString
    {
        $stmt = $this->pdo->prepare('SELECT * FROM people WHERE userid = ?');
        $stmt->execute([$userID]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return new HiddenString(
            $this->encSearch->decrypt($row['ssn'])
        );
    }

    public function tearDown()
    {
        \unlink(\realpath($this->dir) . '/enc.key');
        \unlink(\realpath($this->dir) . '/index.key');
        \unlink(\realpath($this->dir) . '/test-anon-ip.sqlite');
    }
}
