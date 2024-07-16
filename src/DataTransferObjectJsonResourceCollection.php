<?php

namespace Ccharz\DtoLite;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DataTransferObjectJsonResourceCollection extends AnonymousResourceCollection
{
    /**
     * @var class-string<DataTransferObject> $dataTransferObjectClass
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
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray(Request $request)
    {
        return $this->collection->map->toDtoArray($request, $this->dataTransferObjectClass)->all();
    }
}
