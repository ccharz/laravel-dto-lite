<?php

namespace Ccharz\DtoLite;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DataTransferObjectJsonResourceCollection extends AnonymousResourceCollection
{
    /**
     * @var class-string<\Ccharz\DtoLite\DataTransferObject>
     */
    protected readonly string $dataTransferObjectClass;

    public function __construct(mixed $resource, string $collects, string $dataTransferObjectClass)
    {
        $this->dataTransferObjectClass = $dataTransferObjectClass;

        parent::__construct($resource, $collects);
    }

    /**
     * Transform the resource into a JSON array.
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray(Request $request)
    {
        return $this->collection->map->toDtoArray($request, $this->dataTransferObjectClass)->all();
    }
}
