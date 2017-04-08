<?php

use ParagonIE\BsidesOrl2017Talk\DuplicatePasswords;
use ParagonIE\BsidesOrl2017Talk\Util\HiddenString;
use PHPUnit\Framework\TestCase;

class DuplicatePasswordsTest extends TestCase
{
    const GARBAGE = 3;

    /**
     * @var string
     */
    private $dir = '';

    /**
     * @var DuplicatePasswords
     */
    private $dupe;

    /**
     * @var array
     */
    private $data = [];

    public function setUp()
    {
        $this->dir = \dirname(__DIR__) . '/data';
        $this->dupe = new DuplicatePasswords($this->dir . '/test-pw-salt.txt');
    }

    public function testDupes()
    {
        list ($hash, $dupe) = $this->dupe->hashPassword(new HiddenString('correct horse battery staple'));
        $this->data[] = [
            'username' => 'test',
            'hash' => $hash,
            'dupe' => $dupe
        ];

        for ($i = 0; $i < self::GARBAGE; ++$i) {
            $random_pw = \ParagonIE\ConstantTime\Base64UrlSafe::encode(random_bytes(30));
            list ($hash, $dupe) = $this->dupe->hashPassword(new HiddenString($random_pw));
            $this->data[] = [
                'username' => 'test',
                'hash' => $hash,
                'dupe' => $dupe
            ];
        }

        list ($hash, $dupe) = $this->dupe->hashPassword(new HiddenString('correct horse battery staple'));
        $this->data[] = [
            'username' => 'test2',
            'hash' => $hash,
            'dupe' => $dupe
        ];

        // Now let's search
        $found = [];
        foreach ($this->data as $row) {
            if (isset($found[$row['dupe']])) {
                ++$found[$row['dupe']];
            } else {
                $found[$row['dupe']] = 1;
            }
        }
        $this->assertSame(self::GARBAGE + 1, \count($found));
        $copy = $found;
        $this->assertSame(2, \array_shift($copy), 'Original duplicate detection failed');
        for ($i = 0; $i < self::GARBAGE; ++$i) {
            $this->assertSame(1, \array_shift($copy), 'Iteration ' . $i);
        }
    }

    public function tearDown()
    {
        \unlink($this->dir . '/test-pw-salt.txt');
    }
}
