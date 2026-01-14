<?php

namespace Ccharz\DtoLite;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<DataTransferObject|null, array<string,mixed>|Jsonable|null>
 */
class DataTransferObjectCast implements CastsAttributes
{
    /**
     * @param  string[]  $parameters
     */
    public function __construct(
        protected string $class,
        protected array $parameters = [],
    ) {}

    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            if (in_array('nullable', $this->parameters)) {
                return null;
            }

            throw new InvalidArgumentException($key.' is not a string');
        }

        return $this->class::make($value);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            $value = $this->class::make($value);
        }

        if (! $value instanceof Jsonable) {
            throw new InvalidArgumentException(sprintf('Value must be of type [%s], array, or null', $this->class));
        }

        return $value->toJson();
    }
}
