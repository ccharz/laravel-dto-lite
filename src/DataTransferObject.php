<?php

namespace Ccharz\DtoLite;

use BackedEnum;
use Exception;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

abstract readonly class DataTransferObject implements Arrayable, Castable, Jsonable, Responsable
{
    public static function castUsing(array $arguments) : CastsAttributes
    {
        return new DataTransferObjectCast(static::class, $arguments);
    }

    /**
     * @return class-string
     */
    public static function resourceCollectionClass(): string
    {
        return DataTransferObjectJsonResourceCollection::class;
    }

    public static function casts(): ?array
    {
        return null;
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }

    protected function simplify(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof Carbon => $value->toJson(),
            $value instanceof DataTransferObject => $value->toArray(),
            is_array($value) => array_map(
                fn ($element): mixed => $this->simplify($element),
                $value
            ),
            default => $value
        };
    }

    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    public function toArray(): array
    {
        $output = get_object_vars($this);

        foreach ($output as $key => $value) {
            $output[$key] = $this->simplify($value);
        }

        return $output;
    }

    public function toArrayWithRequest(Request $request): array
    {
        return $this->toArray();
    }

    public static function appendRules(array $rules, string $key): array
    {
        $staticRules = static::rules();
    
        if ($staticRules !== null && $staticRules !== []) {
            $rules[$key][] = 'array';
            foreach ($staticRules as $field => $value) {
                $rules[$key.'.'.$field] = $value;
            }
        }

        return $rules;
    }

    public static function appendArrayElementRules(array $rules, string $key): array
    {
        $rules[$key] = ['array'];

        return static::appendRules($rules, $key.'.*');
    }

    protected static function applyCastRules(array $rules, string $field, mixed $cast): array
    {
        switch (true) {
            case str_ends_with((string) $cast, '[]'):
                $cast = substr((string) $cast, 0, -2);
                $rules[$field][] = 'array';
                $rules = static::applyCastRules($rules, $field.'.*', $cast);
                break;
            case $cast === 'datetime':
                $rules[$field][] = 'date';
                break;
            case is_a($cast, DataTransferObject::class, true):
                $rules = $cast::appendRules($rules, $field);

                break;
            case is_a($cast, BackedEnum::class, true):
                $rules[$field][] = Rule::enum($cast);

                break;
        }

        return $rules;
    }

    public static function castRules(): array
    {
        $casts = static::casts() ?? [];

        $rules = [];

        foreach ($casts as $field => $cast) {
            $rules[$field] = [];
            $rules = static::applyCastRules($rules, $field, $cast);
        }

        return $rules;
    }

    public static function rules(?Request $request = null): ?array
    {
        return static::castRules();
    }

    public static function afterValidation(?Request $request = null): array
    {
        return [];
    }

    protected static function makeFromRequestArray(array $validated_data, ?Request $request = null): static
    {
        return static::makeFromArray($validated_data);
    }

    public static function validate(array $data, ?Request $request = null): array
    {
        return Validator::make($data, static::rules($request) ?? [])
            ->after(static::afterValidation($request))
            ->validated();
    }

    public static function makeFromRequest(Request $request): static
    {
        return static::makeFromRequestArray(
            static::validate($request->all(), $request),
            $request
        );
    }

    public static function makeFromModel(Model $model): static
    {
        return static::makeFromArray($model->toArray());
    }

    protected static function applyCast(mixed $data, string $cast): mixed
    {
        if (str_ends_with($cast, '[]') && is_array($data)) {
            return array_map(
                fn (mixed $element): mixed => static::applyCast($element, substr($cast, 0, -2)),
                $data
            );
        }
        if ($cast === 'datetime') {
            return empty($data)
                ? null
                : Carbon::parse($data);
        }
        if (is_a($cast, DataTransferObject::class, true)) {
            if ($data instanceof $cast) {
                return $data;
            }
            return ! is_null($data) && is_array($data)
                ? $cast::makeFromArray($data)
                : null;
        }
        if (is_a($cast, BackedEnum::class, true)) {
            if ($data instanceof $cast) {
                return $data;
            }
            return $cast::tryFrom($data);
        }
        throw new Exception('Unknown cast "'.$cast.'"');
    }

    public static function makeFromArray(array $data): static
    {
        if (($casts = static::casts()) !== null && ($casts = static::casts()) !== []) {
            foreach ($casts as $field => $cast) {
                if (array_key_exists($field, $data)) {
                    $data[$field] = static::applyCast($data[$field], $cast);
                }
            }
        }

        /* @phpstan-ignore-next-line */
        return new static(...$data);
    }

    public static function makeFromJson(?string $json, int $options = 0): static
    {
        $json = $json !== null && $json !== '' ? json_decode($json, true, 512, $options) : [];

        return static::makeFromArray(
            $json ?? []
        );
    }

    public static function make(mixed $data): static
    {
        if ($data instanceof Model) {
            return static::makeFromModel($data);
        }

        if ($data instanceof Request) {
            return static::makeFromRequest($data);
        }

        if (is_null($data) || is_string($data)) {
            return static::makeFromJson($data, JSON_THROW_ON_ERROR);
        }

        if (is_array($data)) {
            return static::makeFromArray($data);
        }

        throw new Exception('Invalid data to make data transfer object');
    }

    public function toResponse($request)
    {
        return new JsonResponse($this->toArray());
    }

    public function resource(): JsonResource
    {
        return new DataTransferObjectJsonResource($this);
    }

    public static function resourceCollection($resource): AnonymousResourceCollection
    {
        return new (static::resourceCollectionClass())(
            $resource,
            DataTransferObjectJsonResource::class,
            static::class
        );
    }

    public static function mapToDtoArray(iterable $array_map = [], ?string $key = null): array
    {
        if ($key !== null) {
            $array_map = $array_map[$key] ?? [];
        }

        $output = [];

        foreach ($array_map as $array_element) {
            $output[] = static::make($array_element);
        }

        return $output;
    }
}
