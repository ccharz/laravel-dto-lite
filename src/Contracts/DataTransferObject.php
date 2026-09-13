<?php

declare(strict_types=1);

namespace Ccharz\DtoLite\Contracts;

use Ccharz\DtoLite\Exceptions\InvalidDataException;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use JsonSerializable;

/**
 * @extends Arrayable<string,mixed>
 */
interface DataTransferObject extends Arrayable, Castable, Jsonable, JsonSerializable, Responsable
{
    /**
     * @throws InvalidDataException
     */
    public static function make(mixed $data): static;

    /**
     * @return array<string,array<int,mixed>>
     */
    public static function rules(?Request $request = null): array;

    /**
     * @return array<string,string>
     */
    public static function casts(): array;

    /**
     * Convert the data transfer object into something JSON serializable.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array;

    /**
     * @return array<string, mixed>
     */
    public function toArrayWithRequest(Request $request): array;

    /**
     * Convert the data transfer object into a json resource
     */
    public function toJsonResource(): DataTransferObjectJsonResource;

    /**
     * Converts data into a data transfer object resource collection
     */
    public static function collection(mixed $resource): AnonymousResourceCollection;

    /**
     * @param  array<string,array<int,mixed>>  $rules
     * @return array<string,array<int,mixed>>
     */
    public static function appendRules(array $rules, string $key, ?Request $request = null): array;

    /**
     * @param  iterable<array-key,mixed>  $items
     * @return static[]
     */
    public static function mapToDtoArray(iterable $items, string|int|null $offset = null): array;

    /**
     * @param  array<string,array<int,mixed>>  $rules
     * @return array<string,array<int,mixed>>
     */
    public static function appendArrayElementRules(array $rules, string $key, ?Request $request = null): array;
}
