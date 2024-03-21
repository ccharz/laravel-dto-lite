<?php

namespace Ccharz\DtoLite;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\Castable;
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

abstract class DataTransferObject implements Arrayable, Castable, Jsonable, Responsable
{
    protected static string $resourceCollectionClass = DataTransferObjectJsonResourceCollection::class;

    public static function castUsing(array $arguments)
    {
        return new DataTransferObjectCast(static::class, $arguments);
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
                fn ($element) => $this->simplify($element),
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
        if ($staticRules = static::rules()) {
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

    public static function castRules(): array
    {
        $casts = static::casts() ?? [];
        $fields = get_class_vars(static::class);

        $rules = [];

        foreach ($fields as $field => $default) {
            if (! in_array($field, ['resourceCollectionClass'])) {
                $rules[$field] = [];
                if ($cast = isset($casts[$field]) ? $casts[$field] : null) {
                    switch (true) {
                        case substr($cast, -2) === '[]':
                            $dtoClass = substr($cast, 0, -2);
                            $rules = $dtoClass::appendArrayElementRules($rules, $field);
                            break;
                        case is_a($cast, DataTransferObject::class, true):
                            $rules = $cast::appendRules($rules, $field);

                            break;
                        case $cast === 'datetime':
                            $rules[$field][] = 'date';
                            break;
                        case enum_exists($cast) && method_exists($cast, 'from'):
                            $rules[$field][] = 'string';
                            $rules[$field][] = Rule::in(array_column($cast::cases(), 'value'));

                            break;
                    }
                }
            }
        }

        return $rules;
    }

    public static function rules(): ?array
    {
        $rules = static::castRules();

        return $rules;
    }

    public static function validate(array $data): array
    {
        return Validator::make($data, static::rules() ?? [])->validated();
    }

    public static function makeFromRequest(Request $request): static
    {
        return static::makeFromArray(
            static::validate($request->all())
        );
    }

    public static function makeFromModel(Model $model): static
    {
        return static::makeFromArray($model->toArray());
    }

    public static function makeFromArray(array $data): static
    {
        if ($casts = static::casts()) {
            foreach ($casts as $field => $cast) {
                if (array_key_exists($field, $data)) {
                    switch (true) {
                        case substr($cast, -2) === '[]':
                            if (is_array($data[$field])) {
                                $dto = substr($cast, 0, -2);

                                $data[$field] = $dto::mapToDtoArray($data, $field);
                            }

                            break;
                        case $cast === 'datetime':
                            $data[$field] = ! empty($data[$field])
                                ? Carbon::parse($data[$field])
                                : null;
                            break;
                        case is_a($cast, DataTransferObject::class, true):
                            $data[$field] = ! is_null($data[$field]) && is_array($data[$field])
                                ? $cast::makeFromArray($data[$field])
                                : null;
                            break;
                        case enum_exists($cast) && method_exists($cast, 'from'):
                            $data[$field] = $cast::from($data[$field]);
                            break;
                    }
                }
            }
        }

        /* @phpstan-ignore-next-line */
        return new static(...$data);
    }

    /**
     * @param  array<int,mixed>  $array_map
     */
    public static function mapToDtoArray(array $array_map = [], ?string $key = null): array
    {
        if ($key) {
            $array_map = isset($array_map[$key]) ? $array_map[$key] : [];
        }

        return array_map(
            fn (array $array_element) => static::make($array_element),
            $array_map
        );
    }

    public static function makeFromJson(?string $json, int $options = 0): static
    {
        $json = $json ? json_decode($json, true, 512, $options) : [];

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

        throw new \Exception('Invalid data to make data transfer object');
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
        return new static::$resourceCollectionClass(
            $resource,
            DataTransferObjectJsonResource::class,
            static::class
        );
    }
}
