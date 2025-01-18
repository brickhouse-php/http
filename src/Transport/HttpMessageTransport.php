<?php

namespace Brickhouse\Http\Transport;

use Amp\Socket\Socket;
use Amp\Pipeline\DisposedException;
use Brickhouse\Http\Exceptions\HttpClientException;
use Brickhouse\Http\HttpHeaderBag;
use Brickhouse\Http\HttpStatus;
use Brickhouse\Http\Server\HttpClientDriver;
use League\Uri\Uri;

abstract class HttpMessageTransport
{
    protected const string HOST_HEADER_REGEX = '/^(?<host>[A-Z\d._\-]+|\[[\d:]+])(?::(?<port>[1-9]\d*))?$/i';

    /**
     * Parse the given raw header string into case-insensitive HTTP headers.
     *
     * @param string    $rawHeaderString
     *
     * @return HttpHeaderBag
     */
    protected function parseRawHeaders(string $rawHeaderString): HttpHeaderBag
    {
        $rawHeaders = explode("\r\n", $rawHeaderString);
        $rawHeaders = array_filter($rawHeaders, fn(string $header) => !empty($header));

        return HttpHeaderBag::parse($rawHeaders);
    }

    /**
     * Parse the given `Host` header value into a `Uri`-instance.
     *
     * @param null|string   $hostValue
     *
     * @return Uri
     */
    protected function parseHostHeader(Socket $socket, null|string $hostValue, string $path): Uri
    {
        if (!$hostValue) {
            throw new HttpClientException(
                $socket,
                "Missing `Host` header",
                HttpStatus::BAD_REQUEST
            );
        }

        if (!\preg_match(self::HOST_HEADER_REGEX, $hostValue, $matches)) {
            throw new HttpClientException(
                $socket,
                "Invalid `Host` header",
                HttpStatus::BAD_REQUEST
            );
        }

        $address = $socket->getLocalAddress();

        $host = $matches['host'];
        $port = isset($matches['port'])
            ? (int) $matches['port']
            : ($address instanceof \Amp\Socket\InternetAddress ? $address->getPort() : null);
        $query = null;

        if (str_starts_with($path, "/") && ($position = strpos($path, "?"))) {
            $query = substr($path, $position + 1);
            $path = substr($path, 0, $position);
        }

        return Uri::fromComponents([
            'scheme' => 'http',
            'host' => $host,
            'port' => $port,
            'path' => $path,
            'query' => $query,
        ]);
    }

    /**
     * Read the rest of the request body from the socket.
     *
     * @return array{0:string,1:int}
     */
    protected function parseMessageBody(Socket $socket, string $buffer, HttpHeaderBag $headers): array
    {
        $contentLength = $headers->getAll('content-length');
        if (sizeof($contentLength) > 1) {
            throw new HttpClientException(
                $socket,
                "Multiple `Content-Length` headers given.",
                HttpStatus::BAD_REQUEST
            );
        }

        if (!empty($contentLength)) {
            $contentLength = ctype_digit($contentLength[0])
                ? (int) $contentLength[0]
                : null;
        } else {
            $contentLength = null;
        }

        $body = $this->readMessageBody($socket, $buffer, $contentLength);

        return [$body, $contentLength];
    }

    /**
     * Read the rest of the request body from the socket.
     *
     * @return string
     */
    protected function readMessageBody(Socket $socket, string $buffer, ?int $contentLength): string
    {
        $bodySize = 0;
        $body = '';

        do {
            $currentBufferSize = strlen($buffer);

            while ($bodySize + $currentBufferSize < min($contentLength, HttpClientDriver::DEFAULT_BODY_SIZE_LIMIT)) {
                if ($currentBufferSize > 0) {
                    $body .= $buffer;

                    $buffer = '';
                    $bodySize += $currentBufferSize;
                }

                $chunk = $socket->read();
                if ($chunk === null) {
                    return $body;
                }

                $buffer .= $chunk;
                $currentBufferSize = strlen($buffer);
            }

            $remaining = min($contentLength, HttpClientDriver::DEFAULT_BODY_SIZE_LIMIT);

            if ($remaining > 0) {
                try {
                    $body .= substr($buffer, 0, $remaining);
                } catch (DisposedException) {
                    // Ignore and continue.
                }

                $buffer = substr($buffer, $remaining);
                $bodySize += $remaining;
            }
        } while ($bodySize < min($contentLength, HttpClientDriver::DEFAULT_BODY_SIZE_LIMIT));

        if ($contentLength > HttpClientDriver::DEFAULT_BODY_SIZE_LIMIT) {
            throw new HttpClientException(
                $socket,
                "Message body too large",
                HttpStatus::PAYLOAD_TOO_LARGE
            );
        }
        return $body;
    }
}
