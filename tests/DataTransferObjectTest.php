<?php

namespace Ccharz\DtoLite\Tests;

use Ccharz\DtoLite\DataTransferObject;
use Ccharz\DtoLite\DataTransferObjectCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
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

            public static function rules(): array
            {
                return ['test' => ['string']];
            }
        };
    }

    private function prepareSimpleModel(): Model
    {
        return new class() extends Model
        {
            protected $guarded = [];
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
        $model = $this->prepareSimpleModel();
        $model->fill(['test' => 'Test1234']);

        $mock = $this->prepareSimpleDtoObject();

        $dto = $mock::make($model);

        $this->assertSame('Test1234', $dto->test);
    }

    public function test_it_is_castable_for_models(): void
    {
        $mock = $this->prepareSimpleDtoObject();
        $model = $this->prepareSimpleModel();

        $cast = new DataTransferObjectCast(get_class($mock), []);

        /* SET */
        $this->assertSame('{"test":"Test1234"}', $cast->set($model, 'data', ['test' => 'Test1234'], []));
        $this->assertNull($cast->set($model, 'data', null, []));

        /* GET */
        $this->assertInstanceOf(get_class($mock), $cast->get($model, 'data', '{"test":"Test1234"}', []));
    }

    public function test_it_castable_getter_throws_on_invalid_values(): void
    {
        $mock = $this->prepareSimpleDtoObject();
        $model = $this->prepareSimpleModel();

        $cast = new DataTransferObjectCast(get_class($mock), []);

        $this->expectExceptionMessage('data is not a string');
        $cast->get($model, 'data', null, []);
    }

    public function test_it_castable_setter_throws_on_invalid_values(): void
    {
        $mock = $this->prepareSimpleDtoObject();
        $model = $this->prepareSimpleModel();

        $cast = new DataTransferObjectCast(get_class($mock), []);

        $this->expectException(InvalidArgumentException::class);
        $cast->set($model, 'data', 'test1234', []);
    }

    public function test_dto_returns_the_correct_cast(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $this->assertInstanceOf(DataTransferObjectCast::class, $mock->castUsing([]));
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

            public static function casts(): ?array
            {
                return ['test' => 'datetime'];
            }
        };

        $dto = $mock::makeFromArray(['test' => '02.02.2024']);

        $this->assertInstanceOf(Carbon::class, $dto->test);

        $dto = $mock::makeFromArray(['test' => null]);

        $this->assertNull($dto->test);

        $this->assertSame(['test' => ['date']], $mock::rules());
    }

    public function test_it_can_cast_enums(): void
    {
        $mock = new class(TestEnum::A) extends DataTransferObject
        {
            public function __construct(public readonly TestEnum $testEnum)
            {
            }

            public static function casts(): ?array
            {
                return ['testEnum' => TestEnum::class];
            }
        };

        $dto = $mock::makeFromArray(['testEnum' => 'B']);

        $this->assertInstanceOf(TestEnum::class, $dto->testEnum);
        $this->assertSame(TestEnum::B, $dto->testEnum);
        $this->assertSame(['testEnum' => 'B'], $dto->toArray());
        $this->assertCount(1, $mock::rules()['testEnum']);
        $this->assertInstanceOf(In::class, $mock::rules()['testEnum'][0]);
        $this->assertSame('in:"A","B"', $mock::rules()['testEnum'][0]->__toString());
    }

    public function test_it_works_with_already_casted_enum(): void
    {
        $mock = new class(TestEnum::A) extends DataTransferObject
        {
            public function __construct(public readonly TestEnum $testEnum)
            {
            }

            public static function casts(): ?array
            {
                return ['testEnum' => TestEnum::class];
            }
        };

        $dto = $mock::makeFromArray(['testEnum' => TestEnum::B]);

        $this->assertSame(TestEnum::B, $dto->testEnum);
    }

    public function test_it_can_cast_to_an_enum_array(): void
    {
        $mock = new class('') extends DataTransferObject
        {
            public static string $className = '';

            public function __construct(public readonly mixed $test_cast)
            {
            }

            public static function casts(): ?array
            {
                return ['test_cast' => TestEnum::class.'[]'];
            }
        };

        $dto = $mock::makeFromArray(['test_cast' => ['A', 'B']]);

        $this->assertIsArray($dto->test_cast);
        $this->assertCount(2, $dto->test_cast);
        $this->assertSame(TestEnum::A, $dto->test_cast[0]);
        $this->assertSame(TestEnum::B, $dto->test_cast[1]);

        $rules = $mock::rules();
        $this->assertArrayHasKey('test_cast', $rules);
        $this->assertArrayHasKey('test_cast.*', $rules);
        $this->assertSame(['array'], $rules['test_cast']);
        $this->assertCount(1, $rules['test_cast.*']);
    }

    public function test_it_can_cast_to_dto(): void
    {
        $mock_a = $this->prepareSimpleDtoObject();

        $mock_b = new class('') extends DataTransferObject
        {
            public static string $className = '';

            public function __construct(public readonly mixed $test_cast)
            {
            }

            public static function casts(): ?array
            {
                return ['test_cast' => self::$className];
            }
        };

        $mock_b::$className = get_class($mock_a);

        $dto = $mock_b::makeFromArray(['test_cast' => ['test' => 'A']]);

        $this->assertInstanceOf(DataTransferObject::class, $dto->test_cast);
        $this->assertSame(['test_cast' => ['test' => 'A']], $dto->toArray());
        $this->assertSame(
            [
                'test_cast' => ['array'],
                'test_cast.test' => ['string'],
            ],
            $mock_b::rules()
        );
    }

    public function test_it_works_with_already_casted_dto(): void
    {
        $mock_a = $this->prepareSimpleDtoObject('');

        $mock_b = new class('') extends DataTransferObject
        {
            public static string $className = '';

            public function __construct(public readonly mixed $test_cast)
            {
            }

            public static function casts(): ?array
            {
                return ['test_cast' => self::$className];
            }
        };

        $mock_b::$className = get_class($mock_a);

        $dto = $mock_b::makeFromArray(['test_cast' => new $mock_a('A')]);

        $this->assertSame(['test_cast' => ['test' => 'A']], $dto->toArray());
    }

    public function test_it_can_cast_to_dto_array(): void
    {
        $mock_a = new class('') extends DataTransferObject
        {
            public function __construct(public readonly string $test)
            {
            }

            public static function rules(): array
            {
                return [
                    'test' => ['min:1'],
                ];
            }
        };

        $mock_b = new class('') extends DataTransferObject
        {
            public static string $className = '';

            public function __construct(public readonly mixed $test_cast)
            {
            }

            public static function casts(): ?array
            {
                return ['test_cast' => self::$className.'[]'];
            }
        };

        $mock_b::$className = get_class($mock_a);

        $dto = $mock_b::makeFromArray(['test_cast' => [['test' => 'A'], ['test' => 'B']]]);

        $this->assertIsArray($dto->test_cast);
        $this->assertCount(2, $dto->test_cast);
        $this->assertInstanceOf(DataTransferObject::class, $dto->test_cast[0]);
        $this->assertInstanceOf(DataTransferObject::class, $dto->test_cast[1]);
        $this->assertSame(
            [
                'test_cast' => [
                    'array',
                ],
                'test_cast.*' => ['array'],
                'test_cast.*.test' => ['min:1'],
            ],
            $dto->rules()
        );
    }

    public function test_it_can_cast_to_empty_dto(): void
    {
        $mock_a = new class('') extends DataTransferObject
        {
            public function __construct(public readonly string $test)
            {
            }
        };

        $mock_b = new class('') extends DataTransferObject
        {
            public static string $className = '';

            public function __construct(public readonly mixed $test_cast)
            {
            }

            public static function casts(): ?array
            {
                return ['test_cast' => self::$className];
            }
        };

        $mock_b::$className = get_class($mock_a);

        $dto = $mock_b::makeFromArray(['test_cast' => null]);

        $this->assertNull($dto->test_cast);
    }

    public function test_it_throws_an_exception_with_an_unknown_cast(): void
    {
        $mock = new class('') extends DataTransferObject
        {
            public function __construct(public readonly mixed $test_cast)
            {
            }

            public static function casts(): ?array
            {
                return ['test_cast' => 'test1234'];
            }
        };

        $this->expectExceptionMessage('Unknown cast "test1234"');

        $mock::makeFromArray(['test_cast' => []]);
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
                return ['test' => ['min:15']];
            }
        };

        $this->assertSame(['child' => ['array'], 'child.test' => ['min:15']], $mock::appendRules([], 'child'));

        $this->assertSame(['child' => ['array'], 'child.*' => ['array'], 'child.*.test' => ['min:15']], $mock::appendArrayElementRules([], 'child'));
    }

    public function test_it_can_map_to_dto_arrays(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $result = $mock::mapToDtoArray([['test' => 'A'], ['test' => 'B']]);

        $this->assertCount(2, $result);
        $this->assertIsObject($result[0]);
        $this->assertIsObject($result[1]);
        $this->assertSame('A', $result[0]->test);
        $this->assertSame('B', $result[1]->test);
    }

    public function test_it_can_map_to_dto_arrays_with_key(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $result = $mock::mapToDtoArray(
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
