<?php
declare(strict_types=1);

namespace ParagonIE\BsidesOrl2017Talk\Handlers;

use GuzzleHttp\Psr7\Response;
use ParagonIE\BsidesOrl2017Talk\BaseHandler;

/**
 * Class StaticPage
 */
class StaticPage extends BaseHandler
{
    public function index(): Response
    {
        return $this->render('index.twig');
    }
}
