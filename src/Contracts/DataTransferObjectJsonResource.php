<?php

declare(strict_types=1);

namespace Ccharz\DtoLite\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @phpstan-require-extends JsonResource
 */
interface DataTransferObjectJsonResource
{
    /**
     * @param  class-string<DataTransferObject>  $dataTransferObjectClass
     * @return array<int|string,mixed>
     */
    public function toDtoArray(Request $request, string $dataTransferObjectClass): array;
}
