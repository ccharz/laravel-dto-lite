<?php

namespace Ccharz\DtoLite\Tests;

use Ccharz\DtoLite\DataTransferObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use stdClass;

enum TestEnum: string
{
    case A = 'A';
    case B = 'B';
}

class DataTransferObjectTest extends TestCase
{
    private function prepareSimpleDtoObject(string $value = 'test')
    {
        return new class($value) extends DataTransferObject
        {
            public function __construct(public readonly string $test)
            {
            }
        };
    }

    public function test_it_can_make_dto_from_array(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $dto = $mock::make(['test' => 'Test1234']);

        $this->assertSame('Test1234', $dto->test);
    }

    public function test_it_can_make_dto_from_json_string(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $dto = $mock::make('{"test": "Test1234"}');

        $this->assertSame('Test1234', $dto->test);
    }

    public function test_it_can_make_dto_from_request(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $dto = $mock::make((new Request)->merge(['test' => 'Test1234']));

        $this->assertSame('Test1234', $dto->test);
    }

    public function test_it_can_make_dto_from_eloquent_model(): void
    {
        $model = new class() extends Model
        {
            protected $guarded = [];
        };
        $model->fill(['test' => 'Test1234']);

        $mock = $this->prepareSimpleDtoObject();

        $dto = $mock::make($model);

        $this->assertSame('Test1234', $dto->test);
    }

    public function test_it_can_serialize_to_json(): void
    {
        $mock = $this->prepareSimpleDtoObject('jsondata');

        $this->assertSame('{"test":"jsondata"}', $mock->toJson());
    }

    public function test_it_is_arrayable(): void
    {
        $mock = $this->prepareSimpleDtoObject('arraydata');

        $this->assertSame(['test' => 'arraydata'], $mock->toArray());
    }

    public function test_it_can_validate_data(): void
    {
        $mock = new class('') extends DataTransferObject
        {
            public function __construct(public readonly string $test)
            {
            }

            public static function rules(): ?array
            {
                return ['test' => 'min:15'];
            }
        };

        $request = (new Request())->merge(['test' => 'ABC']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The test field must be at least 15 characters.');
        $mock::makeFromRequest($request);
    }

    public function test_it_can_cast_dates(): void
    {
        $mock = new class(Carbon::parse('01.01.2022')) extends DataTransferObject
        {
            public function __construct(public readonly ?Carbon $test)
            {
            }

            public static function propertyCasts(): ?array
            {
                return ['test' => 'datetime'];
            }
        };

        $dto = $mock::makeFromArray(['test' => '02.02.2024']);

        $this->assertInstanceOf(Carbon::class, $dto->test);

        $dto = $mock::makeFromArray([]);

        $this->assertNull($dto->test);
    }

    public function test_it_can_cast_enums(): void
    {
        $mock = new class(TestEnum::A) extends DataTransferObject
        {
            public function __construct(public readonly TestEnum $testEnum)
            {
            }

            public static function propertyCasts(): ?array
            {
                return ['testEnum' => TestEnum::class];
            }
        };

        $dto = $mock::makeFromArray(['testEnum' => 'B']);

        $this->assertInstanceOf(TestEnum::class, $dto->testEnum);
        $this->assertSame(TestEnum::B, $dto->testEnum);
    }

    public function test_it_can_manipulate_rules(): void
    {
        $mock = new class('') extends DataTransferObject
        {
            public function __construct(public readonly string $test)
            {
            }

            public static function rules(): ?array
            {
                return ['test' => 'min:15'];
            }
        };

        $this->assertSame(['child.test' => 'min:15'], $mock::appendRules([], 'child'));

        $this->assertSame(['child' => 'array', 'child.*' => 'array', 'child.*.test' => 'min:15'], $mock::appendArrayElementRules([], 'child'));
    }

    public function test_it_can_map_to_dto_arrays(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $result = $mock->mapToDtoArray([['test' => 'A'], ['test' => 'B']]);

        $this->assertCount(2, $result);
        $this->assertIsObject($result[0]);
        $this->assertIsObject($result[1]);
        $this->assertSame('A', $result[0]->test);
        $this->assertSame('B', $result[1]->test);
    }

    public function test_it_can_map_to_dto_arrays_with_key(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $result = $mock->mapToDtoArray(
            ['data' => [['test' => 'A'], ['test' => 'B']]],
            'data'
        );

        $this->assertCount(2, $result);
    }

    public function test_it_throws_for_unknown_data(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $this->expectExceptionMessage('Invalid data to make data transfer object');

        $mock::make(new stdClass);
    }

    public function test_resolve_from_request(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        app(Request::class)->merge(['test' => 'ABC']);

        $newMock = app(get_class($mock));

        $this->assertSame(get_class($mock), get_class($newMock));
        $this->assertSame('ABC', $newMock->test);

        $this->assertSame('ABC', app(get_class($mock))->test);
    }

    public function test_to_response_method(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $this->assertInstanceOf(JsonResponse::class, $mock->toResponse(request()));
    }

    public function test_it_can_generate_a_resource(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $this->assertInstanceOf(
            JsonResource::class,
            $mock->resource()
        );
    }

    public function test_it_can_generate_a_resource_collection_from_dto(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $collection = $mock::resourceCollection([$mock]);

        $this->assertSame('{"data":[{"test":"test"}]}', $collection->toResponse(request())->getContent());
    }

    public function test_it_can_generate_a_resource_collection_via_make(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $collection = $mock::resourceCollection([['test' => '1234']]);

        $this->assertSame('{"data":[{"test":"1234"}]}', $collection->toResponse(request())->getContent());
    }
}
