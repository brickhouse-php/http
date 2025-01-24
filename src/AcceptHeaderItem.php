<?php

namespace Brickhouse\Http;

class AcceptHeaderItem
{
    /**
     * Gets the zero-based index of the item.
     *
     * @var int
     */
    public int $index = 0;

    /**
     * Gets the actual format of the item.
     *
     * @var string
     */
    public readonly string $format;

    /**
     * Gets the quality of the item.
     *
     * @var float
     */
    public protected(set) float $quality = 1.0;

    /**
     * Gets the optional attributes of the item.
     *
     * @var array<string,string>
     */
    public protected(set) array $attributes = [];

    /**
     * @param string        $format
     * @param list<string>  $attributes
     */
    protected function __construct(
        string $format,
        array $attributes = [],
    ) {
        $this->format = $format;

        foreach ($attributes as $value) {
            [$key, $value] = array_map('trim', explode('=', $value));

            if ($key === 'q') {
                $this->quality = (float) $value;
            } else {
                $this->attributes[$key] = $value;
            }
        }
    }

    /**
     * Parses the given `Accept`-header item (e.g. `text/plain;q=1.0`) into a `AcceptHeaderItem`-instance.
     *
     * @param string $item
     *
     * @return AcceptHeaderItem
     */
    public static function parse(string $item): AcceptHeaderItem
    {
        $parts = explode(';', $item);
        $format = array_shift($parts);

        return new AcceptHeaderItem($format, $parts);
    }
}
