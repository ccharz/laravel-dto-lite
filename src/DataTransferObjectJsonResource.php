<?php

declare(strict_types=1);

namespace Ccharz\DtoLite;

use Ccharz\DtoLite\Concerns\IsDataTransferObjectJsonResource;
use Ccharz\DtoLite\Contracts\DataTransferObjectJsonResource as DataTransferObjectJsonResourceContract;
use Illuminate\Http\Resources\Json\JsonResource;

class DataTransferObjectJsonResource extends JsonResource implements DataTransferObjectJsonResourceContract
{
    use IsDataTransferObjectJsonResource;
}
