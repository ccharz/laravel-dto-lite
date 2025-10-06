<?php

namespace Ccharz\DtoLite;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class AsDataTransferObjectCollection implements Castable
{
    /**
     * Get the caster class to use when casting from / to this cast target.
     *
     * @template TDataTransferObject of \Ccharz\DtoLite\DataTransferObject
     *
     * @param  array{class-string<TDataTransferObject>}  $arguments
     * @return CastsAttributes<Collection<array-key, TDataTransferObject>, iterable<TDataTransferObject>>
     */
    public static function castUsing(array $arguments)
    {
        return new class($arguments) implements CastsAttributes
        {
            public function __construct(protected array $arguments) {}

            public function get($model, $key, $value, $attributes)
            {
                if (! isset($attributes[$key])) {
                    return;
                }

                $data = Json::decode($attributes[$key]);

                if (! is_array($data) || ! Arr::isList($data)) {
                    return;
                }

                $dataTransferObjectClass = $this->arguments[0];

                return (new Collection($data))->map(fn ($value): DataTransferObject => $dataTransferObjectClass::make($value));
            }

            public function set($model, $key, $value, $attributes)
            {
                $value = $value !== null
                    ? Json::encode((new Collection($value))
                        ->map(fn (DataTransferObject $dataTransferObject): array => $dataTransferObject->jsonSerialize())
                        ->jsonSerialize())
                    : null;

                return [$key => $value];
            }

            public function serialize($model, string $key, $value, array $attributes)
            {
                return (new Collection($value))
                    ->map(fn (DataTransferObject $dataTransferObject): array => $dataTransferObject->jsonSerialize())
                    ->toArray();
            }
        };
    }

    /**
     * Specify the Data Transfer Object for the cast.
     *
     * @param  class-string<DataTransferObject>  $class
     */
    public static function of(string $class): string
    {
        return static::class.':'.$class;
    }
}
