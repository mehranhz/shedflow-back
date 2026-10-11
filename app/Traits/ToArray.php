<?php

namespace App\Traits;

use BackedEnum;
use DateTimeInterface;
use ReflectionClass;
use ReflectionProperty;
use UnitEnum;

trait ToArray
{
    /**
     * Return all public, non-static properties of the object as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);
        $result = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            if (!$property->isInitialized($this)) {
                continue;
            }

            $value = $property->getValue($this);

            $result[$property->getName()] = $this->normalizeValue($value);
        }

        return $result;
    }

    /**
     * Recursively normalize a value for array output.
     */
    protected function normalizeValue(mixed $value): mixed
    {
        // Nested DTO using the same trait
        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        // Dates
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        // Enums (PHP 8.1+)
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        // Arrays — normalize each element
        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalizeValue($item), $value);
        }

        return $value;
    }
}
