<?php

namespace Brickhouse\Http\Middleware;

use Brickhouse\Http\HttpMiddleware;
use Brickhouse\Http\HttpMiddlewareDelegate;
use Brickhouse\Http\Request;
use Brickhouse\Http\Response;
use Brickhouse\Log\Log;

readonly class CompressionMiddleware implements HttpMiddleware
{
    public const SUPPORTED = [
        'gzip' => 'encodeGzip',
        'deflate' => 'encodeDeflate',
    ];

    public function __invoke(Request $request, HttpMiddlewareDelegate $next): Response
    {
        $contentEncoding = $request->headers->acceptEncoding();
        $selectedEncoding = $this->selectEncoding($contentEncoding);

        $response = $next($request);

        // We currently don't support compressing chunked responses.
        if ($response->headers->transferEncoding() === 'chunked') {
            return $response;
        }

        if ($selectedEncoding === null) {
            return $response;
        }

        // Read the uncompressed content from the response.
        $content = "";
        while (!$response->body->eof()) {
            $content .= $response->body->read(1024);
        }

        // Get the applicable encoding callback and compress the body content.
        $encodingCallback = self::SUPPORTED[$selectedEncoding];
        $encoded = $this->{$encodingCallback}($content);

        $response->setBody($encoded);
        $response->headers->set('Content-Encoding', $selectedEncoding);

        return $response;
    }

    protected function selectEncoding(?string $acceptedEncodings): ?string
    {
        if ($acceptedEncodings === null) {
            return null;
        }

        $acceptedEncodings = explode(",", $acceptedEncodings);
        $acceptedEncodings = array_map('trim', $acceptedEncodings);

        $weightedEncodings = [];
        foreach ($acceptedEncodings as $acceptedEncoding) {
            $segments = explode(";", $acceptedEncoding);

            $codec = $segments[0];

            $weight = $segments[1] ?? 'q=1.0';
            $weight = (float) substr($weight, 2);

            $weightedEncodings[$codec] = $weight;
        }

        // Sort all the available encodings by their requested weight.
        arsort($weightedEncodings);

        foreach (array_keys($weightedEncodings) as $encoding) {
            if (in_array($encoding, array_keys(self::SUPPORTED))) {
                return $encoding;
            }
        }

        // If no supported encodings were found but `*` is specified, pick the first encoding we support.
        if (in_array('*', array_keys($weightedEncodings))) {
            return array_keys(self::SUPPORTED)[0];
        }

        return null;
    }

    protected function encodeGzip(string $body): string
    {
        if (!($encoded = gzencode($body))) {
            Log::warning("Failed to encode body content with GZip.");
            return $body;
        }

        return $encoded;
    }

    protected function encodeDeflate(string $body): string
    {
        if (!($encoded = gzencode($body, encoding: ZLIB_ENCODING_DEFLATE))) {
            Log::warning("Failed to encode body content with DEFLATE.");
            return $body;
        }

        return $encoded;
    }
}
