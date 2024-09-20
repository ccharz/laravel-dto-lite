<?php

namespace Ccharz\DtoLite;

use BackedEnum;
use Carbon\Exceptions\InvalidFormatException;
use Carbon\Month;
use Carbon\WeekDay;
use DateTimeInterface;
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
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * @implements Arrayable<string,mixed>
 */
abstract readonly class DataTransferObject implements Arrayable, Castable, Jsonable, Responsable
{
    /**
     * @param  string[]  $arguments
     * @return CastsAttributes<DataTransferObject|null, array<string,mixed>|Jsonable|null>
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new DataTransferObjectCast(static::class, $arguments); // @phpstan-ignore return.type
    }

    /**
     * @return class-string<AnonymousResourceCollection>
     */
    public static function resourceCollectionClass(): string
    {
        return DataTransferObjectJsonResourceCollection::class;
    }

    /**
     * @return null|array<string,string>
     */
    public static function casts(): ?array
    {
        return null;
    }

    /**
     * @return array<string,mixed>
     */
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
        return json_encode($this->jsonSerialize(), $options, JSON_THROW_ON_ERROR) ?: '{}';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $output = get_object_vars($this);

        foreach ($output as $key => $value) {
            $output[$key] = $this->simplify($value);
        }

        return $output;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArrayWithRequest(Request $request): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<string,array<int,mixed>>  $rules
     * @return array<string,array<int,mixed>>
     */
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

    /**
     * @param  array<string,array<int,mixed>>  $rules
     * @return array<string,array<int,mixed>>
     */
    public static function appendArrayElementRules(array $rules, string $key): array
    {
        $rules[$key] = ['array'];

        return static::appendRules($rules, $key.'.*');
    }

    /**
     * @param  array<string,array<int,mixed>>  $rules
     * @return array<string,array<int,mixed>>
     */
    protected static function applyCastRules(array $rules, string $field, mixed $cast): array
    {
        switch (true) {
            case is_string($cast) && str_ends_with($cast, '[]'):
                $cast = substr($cast, 0, -2);
                $rules[$field][] = 'array';
                $rules = static::applyCastRules($rules, $field.'.*', $cast);
                break;
            case $cast === 'datetime':
                $rules[$field][] = 'date';
                break;
            case is_string($cast) && is_a($cast, DataTransferObject::class, true):
                $rules = $cast::appendRules($rules, $field);

                break;
            case is_string($cast) && is_a($cast, BackedEnum::class, true):
                $rules[$field][] = Rule::enum($cast);

                break;
        }

        return $rules;
    }

    /**
     * @return array<string,array<int,mixed>>
     */
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

    /**
     * @return null|array<string,array<int,mixed>>
     */
    public static function rules(?Request $request = null): ?array
    {
        return static::castRules();
    }

    /**
     * @return array<callable|string>
     */
    public static function afterValidation(?Request $request = null): array
    {
        return [];
    }

    public static function withValidator(Validator $validator, ?Request $request = null): void {}

    /**
     * @return array<string,string>
     */
    public static function attributes(?Request $request = null): array
    {
        return [];
    }

    /**
     * @return array<string,string>
     */
    public static function messages(?Request $request = null): array
    {
        return [];
    }

    /**
     * @param  array<string|int,mixed>  $data
     * @return array<string|int,mixed>
     */
    public static function validate(array $data, ?Request $request = null): array
    {
        $validator = ValidatorFacade::make($data, static::rules($request) ?? [], static::messages($request), static::attributes($request));

        static::withValidator($validator, $request);

        return $validator
            ->after(static::afterValidation($request))
            ->validated();
    }

    /**
     * @param  array<string|int,mixed>  $validated_data
     */
    protected static function makeFromRequestArray(array $validated_data, ?Request $request = null): static
    {
        return static::makeFromArray($validated_data);
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
            return $data instanceof WeekDay || $data instanceof Month || $data instanceof DateTimeInterface || is_numeric($data) || is_string($data)
                ? Carbon::parse($data)
                : null;
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

            if (is_string($data) || is_int($data)) {
                return $cast::tryFrom($data);
            }
        }
        throw new Exception('Unknown cast "'.$cast.'"');
    }

    /**
     * @param  array<mixed>  $data
     *
     * @throws InvalidFormatException
     * @throws Exception
     */
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
        $json = $json !== null && $json !== ''
            ? json_decode($json, true, 512, $options)
            : [];

        return static::makeFromArray(
            is_array($json) ? $json : []
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

    public static function resourceCollection(mixed $resource): AnonymousResourceCollection
    {
        return new (static::resourceCollectionClass())(
            $resource,
            DataTransferObjectJsonResource::class,
            static::class
        );
    }

    /**
     * @template T of string|int
     *
     * @TODO Remove phpstan-ignore
     *
     * @param  iterable<T,mixed>  $array_map
     * @param  T|null  $key
     * @return static[]
     */
    public static function mapToDtoArray(iterable $array_map = [], string|int|null $key = null): array
    {
        if ($key !== null) {
            $array_map = $array_map[$key] ?? []; // @phpstan-ignore offsetAccess.nonOffsetAccessible
        }

        $output = [];

        foreach ($array_map as $array_element) {
            $output[] = static::make($array_element);
        }

        return $output;
    }
}
