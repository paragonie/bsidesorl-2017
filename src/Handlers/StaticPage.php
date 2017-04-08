<?php
declare(strict_types=1);

namespace ParagonIE\BsidesOrl2017Talk\Handlers;

use GuzzleHttp\Psr7\Response;
use ParagonIE\BsidesOrl2017Talk\BaseHandler;

/**
 * Class StaticPage
 * @package ParagonIE\BsidesOrl2017Talk\Handlers
 */
class StaticPage extends BaseHandler
{
    public function index(): Response
    {
        return $this->render('index.twig');
    }


    public function newUser(): Response
    {
        return $this->render('new-user-page.twig');
    }
}
