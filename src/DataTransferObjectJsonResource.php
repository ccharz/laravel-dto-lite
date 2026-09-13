<?php

declare(strict_types=1);

namespace Ccharz\DtoLite;

use Ccharz\DtoLite\Contracts\DataTransferObject;
use Ccharz\DtoLite\Contracts\DataTransferObjectJsonResource as DataTransferObjectJsonResourceContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataTransferObjectJsonResource extends JsonResource implements DataTransferObjectJsonResourceContract
{
    /**
     * @param  class-string<DataTransferObject>  $dataTransferObjectClass
     * @return array<int|string,mixed>
     */
    public function toDtoArray(Request $request, string $dataTransferObjectClass): array
    {
        $resource = $this->resource instanceof DataTransferObject
            ? $this->resource
            : $dataTransferObjectClass::make($this->resource);

        return $resource->toArrayWithRequest($request);
    }
}
