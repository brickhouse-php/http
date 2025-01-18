<?php

namespace Brickhouse\Http\Transport;

use Amp\ByteStream\ClosedException;
use Amp\Socket\Socket;
use Brickhouse\Http\Exceptions\HttpClientException;
use Brickhouse\Http\HttpStatus;
use Brickhouse\Http\Response;
use Brickhouse\Http\Server\HttpClientDriver;

class HttpResponseTransport extends HttpMessageTransport
{
    /**
     * Deccodes the HTTP response from the given socket and returns it.
     *
     * @param Socket    $socket
     *
     * @return Response
     */
    public function receive(Socket $socket): Response
    {
        $buffer = $socket->read();
        if (!$buffer) {
            throw new HttpClientException($socket, "Cannot read request: socket stream is empty.");
        }

        $buffer = \ltrim($buffer, "\r\n");
        $rawHeaders = "";

        do {
            if ($headerEnd = strpos($buffer, "\r\n\r\n")) {
                $rawHeaders = substr($buffer, 0, $headerEnd + 2);
                $buffer = substr($buffer, $headerEnd + 4);
                break;
            }

            $length = strlen($buffer);
            $maxLength = HttpClientDriver::HEADER_SIZE_LIMIT;

            if ($length > $maxLength) {
                throw new HttpClientException(
                    $socket,
                    "Header size exceeded ({$length} > {$maxLength})",
                    HttpStatus::REQUEST_HEADER_FIELDS_TOO_LARGE
                );
            }

            $chunk = $socket->read();
            if ($chunk === null) {
                throw new ClosedException("Socket stream is closed.");
            }

            $buffer .= $chunk;
        } while (true);

        if (!preg_match("/^(?<start>(HTTP\/(?<version>(\d+(?:\.\d+)?)) (?<code>\d{3}) (?<status>(.+))))/", $rawHeaders, $matches)) {
            throw new HttpClientException(
                $socket,
                "Invalid start line in response",
                HttpStatus::BAD_REQUEST
            );
        }

        [
            'version' => $version,
            'code' => $code,
        ] = $matches;

        $rawHeaders = substr($rawHeaders, strlen($matches['start']));
        $headers = $this->parseRawHeaders($rawHeaders);

        [$body] = $this->parseMessageBody($socket, $buffer, $headers);

        $response = new Response(
            intval($code),
            $headers,
            null,
            $version
        );

        $response->setContent($body);

        return $response;
    }

    /**
     * Encodes the HTTP response and sends over the given socket.
     *
     * @param Response  $response
     * @param Socket    $socket
     *
     * @return void
     */
    public function send(Response $response, Socket $socket): void
    {
        $protocol = $response->protocol();

        $status = $response->status;
        $reason = HttpStatus::getReason($status);

        $statusLine = "HTTP/{$protocol} {$status} {$reason}\r\n";
        $headers = $response->headers->serialize();
        $body = $response->content();

        $socket->write($statusLine);
        $socket->write($headers);

        $socket->write("\r\n\r\n");

        while (($chunk = $body->read()) !== null) {
            if ($chunk === '') {
                continue;
            }

            $socket->write($chunk);
        }

        if (($handler = $response->getUpgradeHandler()) !== null) {
            $handler();
        }
    }
}
