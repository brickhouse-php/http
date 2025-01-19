<?php

namespace Brickhouse\Http;

class HttpHeaderBag
{
    /**
     * Gets the header contents, keyed by the header name.
     *
     * @var array<string,string|array<int,string>>
     */
    protected array $headers = [];

    protected function __construct()
    {
        //
    }

    /**
     * Undocumented function
     *
     * @return HttpHeaderBag
     */
    public static function empty(): HttpHeaderBag
    {
        return new HttpHeaderBag();
    }

    /**
     * Parses an array of header strings (e.g. `Host: localhost`) into an `HttpHeaderBag`.
     *
     * @param array<int,string>     $headers
     *
     * @return HttpHeaderBag
     */
    public static function parse(array $headers): HttpHeaderBag
    {
        $bag = static::empty();

        foreach ($headers as $headerString) {
            [$header, $value] = explode(":", $headerString, limit: 2);

            $bag->add($header, $value);
        }

        return $bag;
    }

    /**
     * Parses an array of header names and values into an `HttpHeaderBag`.
     *
     * @param array<string,list<string>>    $headers
     *
     * @return HttpHeaderBag
     */
    public static function parseArray(array $headers): HttpHeaderBag
    {
        $bag = static::empty();

        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $bag->add($name, $value);
            }
        }

        return $bag;
    }

    /**
     * Gets all the headers in the header bag.
     *
     * @return array<string,string>
     */
    public function all(): array
    {
        return $this->headers;
    }

    /**
     * Adds a new header to the header bag with the given name and value.
     * If the header already exists, it is appended.
     *
     * @param string $header
     * @param string $value
     *
     * @return void
     */
    public function add(string $header, string $value): void
    {
        [$header, $value] = self::format($header, $value);

        if (isset($this->headers[$header])) {
            $existing = $this->headers[$header];

            $this->headers[$header] = [
                $existing,
                $value,
            ];

            return;
        }

        $this->headers[$header] = $value;
    }

    /**
     * Sets a new header to the header bag with the given name and value.
     *
     * @param string    $header
     * @param string    $value
     * @param bool      $overwrite  If the header already exists, defines whether it should be overwritten.
     *
     * @return void
     */
    public function set(string $header, string $value, bool $overwrite = true): void
    {
        [$header, $value] = self::format($header, $value);

        if (isset($this->headers[$header]) && !$overwrite) {
            return;
        }

        $this->headers[$header] = $value;
    }

    /**
     * Removes a header from the header bag with the given name.
     *
     * @param string $header
     *
     * @return void
     */
    public function remove(string $header): void
    {
        [$header] = self::format($header);

        unset($this->headers[$header]);
    }

    /**
     * Gets the first instance of the given header, if it exists.
     * If multiple of the header exists, returns the first one.
     *
     * @param string $header
     *
     * @return ?string
     */
    public function get(string $header): ?string
    {
        $all = $this->getAll($header);
        if (empty($all)) {
            return null;
        }

        return $all[0];
    }

    /**
     * Gets all instances of the given header, if it exists. Otherwise, returns an empty array.
     *
     * @param string $header
     *
     * @return array<int,string>
     */
    public function getAll(string $header): array
    {
        [$header] = $this->format($header);

        $value = $this->headers[$header] ?? null;
        if (!$value) {
            return [];
        }

        return array_wrap($value);
    }

    /**
     * Gets whether the bag has the given header.
     * If `$value` is specified, also checks whether the header equals that value.
     *
     * @param string    $header
     * @param ?string   $value
     *
     * @return bool
     */
    public function has(string $header, ?string $value = null): bool
    {
        $headerValue = $this->get($header);

        if ($headerValue && $value !== null) {
            [, $value] = $this->format($header, $value);
            return strcasecmp($headerValue, $value) === 0;
        }

        if ($headerValue !== null) {
            return true;
        }

        return false;
    }

    /**
     * Formats the given header and value.
     *
     * @param string $header
     * @param string $value
     *
     * @return array{string,string}
     */
    public static function format(string $header, string $value = ''): array
    {
        return [
            trim(strtolower($header)),
            trim($value),
        ];
    }

    /**
     * Gets the `Accept` header value, if it exists.
     *
     * @return ?string
     */
    public function accept(): ?string
    {
        return $this->get('accept');
    }

    /**
     * Gets the `Accept-Encoding` header value, if it exists.
     *
     * @return ?string
     */
    public function acceptEncoding(): ?string
    {
        return $this->get('accept-encoding');
    }

    /**
     * Gets the `Connection` header value, if it exists.
     *
     * @return ?string
     */
    public function connection(): ?string
    {
        return $this->get('connection');
    }

    /**
     * Gets the `Content-Encoding` header value, if it exists.
     *
     * @return ?string
     */
    public function contentEncoding(): ?string
    {
        return $this->get('content-encoding');
    }

    /**
     * Gets the `Content-Length` header value, if it exists.
     *
     * @return ?int
     */
    public function contentLength(): ?int
    {
        $value = $this->get('content-length');
        if ($value) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Gets the `Content-Type` header value, if it exists.
     *
     * @return ?string
     */
    public function contentType(): ?string
    {
        return $this->get('content-type');
    }

    /**
     * Gets the `User-Agent` header value, if it exists.
     *
     * @return ?string
     */
    public function userAgent(): ?string
    {
        return $this->get('user-agent');
    }

    /**
     * Gets the `Transfer-Encoding` header value, if it exists.
     *
     * @return ?string
     */
    public function transferEncoding(): ?string
    {
        return $this->get('transfer-encoding');
    }

    /**
     * Serializes all the headers in the header bag into their HTTP format.
     *
     * @return string
     */
    public function serialize(): string
    {
        return join(
            "\r\n",
            array_map(
                fn(string $value, string $key) => "{$key}: {$value}",
                array_values($this->headers),
                array_keys($this->headers)
            )
        );
    }
}
