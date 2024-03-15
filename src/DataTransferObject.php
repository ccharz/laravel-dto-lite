<?php

namespace Ccharz\DtoLite;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

abstract class DataTransferObject implements Arrayable, Jsonable, Responsable
{

    public static function propertyCasts(): ?array
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

    public static function rules(): ?array
    {
        return null;
    }

    public static function appendRules(array $rules, string $key): array
    {
        if ($staticRules = static::rules()) {
            foreach ($staticRules as $field => $value) {
                $rules[$key.'.'.$field] = $value;
            }
        }

        return $rules;
    }

    public static function appendArrayElementRules(array $rules, string $key): array
    {
        $rules[$key] = 'array';
        $rules[$key.'.*'] = 'array';

        return static::appendRules($rules, $key.'.*');
    }

    public static function validate(array $data): array
    {
        if ($rules = static::rules()) {

            return Validator::make($data, $rules)->validated();
        }

        return $data;
    }

    public static function makeFromRequest(Request $request): static
    {
        return static::makeFromArray(
            static::validate($request->all())
        );
    }

    public static function makeFromArray(array $data): static
    {
        if ($casts = static::propertyCasts()) {
            foreach ($casts as $field => $cast) {
                switch (true) {
                    case $cast === 'datetime':
                        $data[$field] = ! empty($data[$field])
                            ? Carbon::parse($data[$field])
                            : null;
                        break;
                    case enum_exists($cast) && method_exists($cast, 'from'):
                        $data[$field] = $cast::from($data[$field]);
                        break;
                }
            }
        }

        /* @phpstan-ignore-next-line */
        return new static(...$data);
    }

    /**
     * @param  array<int,mixed>  $array_map
     */
    public function mapToDtoArray(array $array_map = [], ?string $key = null): array
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
        if (is_null($data) || is_string($data)) {
            return static::makeFromJson($data, JSON_THROW_ON_ERROR);
        }

        if (is_array($data)) {
            return static::makeFromArray($data);
        }

        if ($data instanceof Request) {
            return static::makeFromRequest($data);
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
        return new DataTransferObjectJsonResourceCollection(
            $resource,
            DataTransferObjectJsonResource::class,
            static::class
        );
    }
}
