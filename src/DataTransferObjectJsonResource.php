<?php

namespace Ccharz\DtoLite;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataTransferObjectJsonResource extends JsonResource
{
    public function toDtoArray(Request $request, string $dataTransferObjectClass): array
    {
        $resource = $this->resource instanceof DataTransferObject
            ? $this->resource
            : $dataTransferObjectClass::make($this->resource);

        return $resource->toArrayWithRequest($request);
    }
}
