<?php
declare(strict_types=1);

namespace ParagonIE\BsidesOrl2017Talk\Handlers;

use GuzzleHttp\Psr7\Response;
use ParagonIE\BsidesOrl2017Talk\BaseHandler;

/**
 * Class Logs
 * @package ParagonIE\BsidesOrl2017Talk\Handlers
 */
class Logs extends BaseHandler
{
    public function index(): Response
    {
        return $this->render('logs.twig', [
            'logs' => $this->readLogFile()
        ]);
    }

    /**
     * @return array
     */
    public function readLogFile(): array
    {
        $logs = [];
        $fp = \fopen(BSIDES_ROOT . '/data/live/access.log', 'rb');
        while ($line = \fgets($fp)) {
            if (\trim($line) === '') {
                continue;
            }
            $json = \json_decode($line, true);
            if (empty($json)) {
                continue;
            }
            $logs []= $json;
        }
        \fclose($fp);
        return $logs;
    }
}
