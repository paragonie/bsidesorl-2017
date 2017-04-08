<?php

use GuzzleHttp\Psr7\{
    Response,
    ServerRequest
};
use ParagonIE\BsidesOrl2017Talk\{
    AnonymousIPLogger,
    BaseHandler
};
use ParagonIE\BsidesOrl2017Talk\Handlers\{
    Logs,
    StaticPage
};
use ParagonIE\ConstantTime\Binary;

require_once "../vendor/autoload.php";

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->addRoute('GET', '/users', [StaticPage::class, 'index']);

    // {id} must be a number (\d+)
    $r->addRoute('GET', '/user/{id:\d+}', 'get_user_handler');

    $r->addRoute('GET', '/logs', [Logs::class, 'index']);

    $r->addRoute('GET', '/', [StaticPage::class, 'index']);
});


// Fetch method and URI from somewhere
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Strip query string (?foo=bar) and decode URI
if (false !== $pos = \strpos($uri, '?')) {
    $uri = \substr($uri, 0, $pos);
}
$uri = \rawurldecode($uri);

define('BSIDES_ROOT', \dirname(__DIR__));

$logger = new AnonymousIPLogger(
    BSIDES_ROOT . '/data/live/access.log',
    BSIDES_ROOT . '/data/live/log-keys'
);

try {
    $routeInfo = $dispatcher->dispatch($httpMethod, $uri);
    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            echo '404 Not Found';
            exit;
            break;
        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            $allowedMethods = $routeInfo[1];
            echo 'Not allowed';
            exit;
            break;
        case FastRoute\Dispatcher::FOUND:
            list($class, $method) = $routeInfo[1];
            $vars = $routeInfo[2];

            $request = ServerRequest::fromGlobals();
            $handler = new $class($request, $logger);
            if (!($handler instanceof BaseHandler)) {
                throw new TypeError('');
            }

            $response = $handler->$method(...$vars);
            if ($response instanceof Response) {
                $body = $response->getBody();
                $logger->info('200/OK', [
                    'ip' => $_SERVER['REQUEST_URI'],
                    'method' => $_SERVER['REQUEST_METHOD'],
                    'uri' => $_SERVER['REQUEST_URI'],
                    'user-agent' => $_SERVER['HTTP_USER_AGENT'],
                    'request-length' => $request->getHeaderLine('Content-Length'),
                    'response-length' => Binary::safeStrlen($body)
                ]);
                foreach ($response->getHeaders() as $key => $value) {
                    \header($key . ': ' . $value);
                }
                echo $body;
            }
    }
} catch (Throwable $ex) {
    \header("Content-Type: text/plain;charset=UTF-8");
    $logger->error('200/OK', [
        'ip' => $_SERVER['REQUEST_URI'],
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'user-agent' => $_SERVER['HTTP_USER_AGENT']
    ]);

    echo 'An unexpected error has occurred.', PHP_EOL;
}
