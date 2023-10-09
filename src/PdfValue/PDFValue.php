<?php

namespace Jeidison\PdfSigner\PdfValue;

use ArrayAccess;
use Exception;
use Stringable;

abstract class PDFValue implements ArrayAccess, Stringable
{
    public function __construct(protected $value)
    {
    }

    public function val()
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return ''.$this->value;
    }

    public function offsetExists($offset): bool
    {
        if (! is_array($this->value)) {
            return false;
        }

        return isset($this->value[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        if (! is_array($this->value)) {
            return false;
        }

        if (! isset($this->value[$offset])) {
            return false;
        }

        return $this->value[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        if (! is_array($this->value)) {
            return;
        }

        $this->value[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        if ((! is_array($this->value)) || (! isset($this->value[$offset]))) {
            throw new Exception('invalid offset');
        }

        unset($this->value[$offset]);
    }

    public function push(mixed $value): bool
    {
        return false;
    }

    public function get_int(): bool
    {
        return false;
    }

    public function get_object_referenced(): mixed
    {
        return false;
    }

    public function get_keys()
    {
        return false;
    }

    public function diff($other)
    {
        if (! is_a($other, static::class)) {
            return false;
        }

        if ($this->value === $other->value) {
            return null;
        }

        return $this->value;
    }

    /**
     * Function that converts standard types into PDFValue* types
     *  - integer, double are translated into PDFValueSimple
     *  - string beginning with /, is translated into PDFValueType
     *  - string without separator (e.g. "\t\n ") are translated into PDFValueSimple
     *  - other strings are translated into PDFValueString
     *  - array is translated into PDFValueList, and its inner elements are also converted.
     *
     * @param mixed $value a standard php object (e.g. string, integer, double, array, etc.)
     * @return PDFValue an object of type PDFValue*, depending on the
     */
    protected static function _convert(mixed $value)
    {
        switch (gettype($value)) {
            case 'integer':
            case 'double':
                $value = new PDFValueSimple($value);
                break;
            case 'string':
                if ($value[0] === '/') {
                    $value = new PDFValueType(substr($value, 1));
                } elseif (preg_match("/\s/ms", $value) === 1) {
                    $value = new PDFValueString($value);
                } else {
                    $value = new PDFValueSimple($value);
                }

                break;
            case 'array':
                if ($value === []) {
                    $value = new PDFValueList();
                } else {
                    $obj = PDFValueObject::fromarray($value);
                    if ($obj !== false) {
                        $value = $obj;
                    } else {
                        $list = [];
                        foreach ($value as $v) {
                            $list[] = self::_convert($v);
                        }

                        $value = new PDFValueList($list);
                    }
                }

                break;
        }

        return $value;
    }
}
