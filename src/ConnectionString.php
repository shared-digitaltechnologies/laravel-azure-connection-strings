<?php

namespace Shrd\Laravel\Azure\ConnectionStrings;

use ArrayAccess;
use ArrayIterator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Wrapper around a string map to handle Azure-style connection strings.
 *
 * @implements ArrayAccess<string, string>
 * @implements IteratorAggregate<string, string>
 * @implements Arrayable<string, string>
 */
class ConnectionString implements ArrayAccess, Arrayable, Jsonable, JsonSerializable, IteratorAggregate
{

    /**
     * @param array<string, string> $properties
     */
    private function __construct(protected array $properties)
    {
    }

    /**
     * Creates a new connection string instance from a connection string.
     *
     * @param string $connectionString The connection string to convert.
     * @param string $itemSeparator Separator for key value pairs.
     * @param string $keyVaultSeparator Separator for keys and values.
     * @return static
     */
    public static function fromString(string $connectionString,
                                      string $itemSeparator = ';',
                                      string $keyVaultSeparator = '='): static
    {
        $keyValuePairs = explode($itemSeparator, $connectionString);
        $properties = [];
        foreach ($keyValuePairs as $keyValuePair) {
            if($keyValuePair === '') continue;
            [$key, $value] = explode($keyVaultSeparator, $keyValuePair, 2);
            $properties[$key] = $value;
        }
        return new static($properties);
    }


    public static function fromInstance(self $connectionString): static
    {
        if($connectionString instanceof static) return $connectionString;
        return new static($connectionString->properties);
    }

    /**
     * @param iterable<array-key, string|iterable|self|Arrayable> $input
     * @param string $itemSeparator
     * @param string $keyValueSeparator
     * @return static
     */
    public static function fromIterable(iterable $input,
                                        string $itemSeparator = ';',
                                        string $keyValueSeparator = '='): static
    {
        $properties = [];
        foreach ($input as $key => $value) {
            if(is_string($key)) {
                $properties[$key] = strval($value);
            }
            if(is_int($key)) {
                $child = static::from($value, $itemSeparator, $keyValueSeparator);
                $properties = array_merge($properties, $child->properties);
            }
        }
        return new static(iterator_to_array($properties));
    }

    /**
     * @param string|iterable|ConnectionString|Arrayable $input
     * @param string $itemSeparator
     * @param string $keyValueSeparator
     * @return static
     */
    public static function from(string|iterable|self|Arrayable $input,
                                string $itemSeparator = ';',
                                string $keyValueSeparator = '='): static
    {
        if($input instanceof self) return static::fromInstance($input);
        if(is_string($input)) return self::fromString($input, $itemSeparator, $keyValueSeparator);
        if($input instanceof Arrayable) $input = $input->toArray();
        if(is_iterable($input)) return static::fromIterable($input, $itemSeparator, $keyValueSeparator);

        throw new InvalidArgumentException('Could not convert '.get_debug_type($input).' to '. static::class);
    }

    protected function formatKey(string $input): string
    {
        return Str::studly($input);
    }

    public function get(string $key, string|callable|null $default = null): ?string
    {
        return $this->properties[$this->formatKey($key)] ?? value($default);
    }

    public function set(string $key, ?string $value): static
    {
        if($value === null) {
            unset($this->properties[$this->formatKey($key)]);
        } else {
            $this->properties[$this->formatKey($key)] = $value;
        }
        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->properties[$this->formatKey($key)]);
    }

    public function toString(string $itemSeparator = ';', string $keyValueSeparator = '='): string
    {
        $parts = [];
        foreach ($this->properties as $key => $value) {
            $parts[] = $key.$keyValueSeparator.$value;
        }
        return implode($itemSeparator, $parts);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function __get(string $name): string
    {
        return $this->get($name);
    }

    public function __set(string $name, $value): void
    {
        $this->set($name, $value);
    }

    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    public function __unset(string $name): void
    {
        $this->set($name, null);
    }

    public function toArray(): array
    {
        return $this->properties;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->properties);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): ?string
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->set($offset, null);
    }

    public function toJson($options = 0, string $itemSeparator = ';', string $keyValueSeparator = '='): string
    {
        return json_encode($this->jsonSerialize($itemSeparator, $keyValueSeparator), $options);
    }

    public function jsonSerialize(string $itemSeparator = ';', string $keyValueSeparator = '='): string
    {
        return $this->toString($itemSeparator, $keyValueSeparator);
    }
}
