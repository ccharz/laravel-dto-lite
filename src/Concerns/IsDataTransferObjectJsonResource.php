<?php

declare(strict_types=1);

namespace Ccharz\DtoLite\Concerns;

use Ccharz\DtoLite\Contracts\DataTransferObject;
use Ccharz\DtoLite\Contracts\DataTransferObjectJsonResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @phpstan-require-implements DataTransferObjectJsonResource
 *
 * @mixin JsonResource
 */
trait IsDataTransferObjectJsonResource
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
