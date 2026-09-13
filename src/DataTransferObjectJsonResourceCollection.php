<?php

declare(strict_types=1);

namespace Ccharz\DtoLite;

use Ccharz\DtoLite\Contracts\DataTransferObject;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use JsonSerializable;
use Override;

class DataTransferObjectJsonResourceCollection extends AnonymousResourceCollection
{
    /**
     * @param  class-string<DataTransferObject>  $dataTransferObjectClass
     */
    public function __construct(
        mixed $resource,
        string $collects,
        protected readonly string $dataTransferObjectClass)
    {
        parent::__construct($resource, $collects);
    }

    /**
     * Transform the resource into a JSON array.
     *
     * @return array<int|string,mixed>|Arrayable<int|string,mixed>|JsonSerializable
     */
    #[Override]
    public function toArray(Request $request)
    {
        return $this->collection?->map->toDtoArray($request, $this->dataTransferObjectClass)->all() ?? [];
    }
}
