<?php

declare(strict_types=1);

namespace Ccharz\DtoLite\Contracts;

use Ccharz\DtoLite\Exceptions\InvalidDataException;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;

/**
 * @extends Arrayable<string,mixed>
 */
interface DataTransferObject extends Arrayable, Castable, Jsonable, Responsable
{
    /**
     * @throws InvalidDataException
     */
    public static function make(mixed $data): static;

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
     * @param  array<string,array<int,mixed>>  $rules
     * @return array<string,array<int,mixed>>
     */
    public static function appendRules(array $rules, string $key): array;
}
