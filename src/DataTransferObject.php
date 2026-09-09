<?php

declare(strict_types=1);

namespace Ccharz\DtoLite;

use Ccharz\DtoLite\Concerns\IsDataTransferObject;
use Ccharz\DtoLite\Contracts\DataTransferObject as DataTransferObjectContract;

abstract readonly class DataTransferObject implements DataTransferObjectContract
{
    use IsDataTransferObject;
}
