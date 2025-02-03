<?php

use Brickhouse\Http\HttpClient;
use Brickhouse\Http\HttpStatus;

describe('HTTP Client', function () {
    it('returns HTTP 200 given real API', function () {
        $response = new HttpClient()
            ->acceptsJson()
            ->get('https://httpbin.org/status/200');

        expect($response->status)->toBe(HttpStatus::OK);
    });
})->group('http');
