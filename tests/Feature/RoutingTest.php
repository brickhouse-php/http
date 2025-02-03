<?php

use Brickhouse\Http\Router;

describe('HTTP routing', function () {
    it('returns HTTP 404 given no routes', function () {
        $response = $this->get('/');

        $response->assertNotFound();
    });

    it('returns HTTP 200 given matching route', function () {
        Router::get('/', fn() => 'Hello World!');

        $response = $this->get('/');

        $response->assertOk();
    });
})->group('http');
