<?php

namespace Brickhouse\Http;

class AcceptBag
{
    /**
     * Gets the header items.
     *
     * @var array<string,AcceptHeaderItem>
     */
    protected array $values = [];

    /**
     * @param array<string,AcceptHeaderItem>    $items
     */
    protected function __construct(
        array $items = []
    ) {
        $this->values = $items;
    }

    /**
     * Parses the value of the `Accept`-header value (e.g. `text/html;q=1.0, text/plain;q=0.8`).
     *
     * @param string    $value
     *
     * @return AcceptBag
     */
    public static function parse(string $value): AcceptBag
    {
        $values = [];
        $formats = array_map('trim', explode(',', $value));

        foreach ($formats as $idx => $format) {
            $item = AcceptHeaderItem::parse($format);
            $item->index = $idx;

            $values[$item->format] = $item;
        }

        uasort($values, function (AcceptHeaderItem $a, AcceptHeaderItem $b): int {
            if ($a->quality === $b->quality) {
                return $a->index > $b->index ? 1 : -1;
            }

            return $a->quality > $b->quality ? -1 : 1;
        });

        return new AcceptBag($values);
    }

    /**
     * Gets all the items in the accept bag.
     *
     * @return array<string,AcceptHeaderItem>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * Gets the first item in the accept bag.
     *
     * @return ?AcceptHeaderItem
     */
    public function first(): ?AcceptHeaderItem
    {
        return reset($this->values) ?: null;
    }

    /**
     * Gets the first instance of the given format, if it exists. Otherwise, `null`.
     *
     * @param string $value
     *
     * @return null|AcceptHeaderItem
     */
    public function get(string $value): null|AcceptHeaderItem
    {
        if (isset($this->values[$value])) {
            return $this->values[$value];
        }

        $group = explode('/', $value, limit: 2)[0];
        if (isset($this->values[$group . '/*'])) {
            return $this->values[$group . '/*'];
        }

        if (isset($this->values['*/*'])) {
            return $this->values['*/*'];
        }

        return $this->values['*'] ?? null;
    }

    /**
     * Gets whether the bag contains the given item.
     *
     * @param string $value
     *
     * @return bool
     */
    public function has(string $value): bool
    {
        return isset($this->values[$value]);
    }
}
