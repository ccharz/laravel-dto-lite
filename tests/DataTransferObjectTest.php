<?php

namespace Ccharz\DtoLite\Tests;

use Carbon\CarbonImmutable;
use Ccharz\DtoLite\DataTransferObject;
use Ccharz\DtoLite\DataTransferObjectCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use stdClass;

enum TestEnum: string
{
    case A = 'A';
    case B = 'B';
}

readonly class SimpleDtoObject extends DataTransferObject
{
    public function __construct(public readonly string $test) {}

    public static function rules(?Request $request = null): ?array
    {
        return ['test' => ['min:15']];
    }
}

readonly class ComplexValidationDtoObject extends DataTransferObject
{
    public function __construct(public readonly string $test_1, public readonly string $test_2) {}

    public static function rules(?Request $request = null): ?array
    {
        return ['test_2' => ['min:15'], 'test_1' => ['min:15']];
    }

    public static function attributes(?Request $request = null): array
    {
        return ['test_1' => 'TEST_ATTRIBUTE_OVERWRITE'];
    }

    public static function messages(?Request $request = null): array
    {
        return ['test_2.min' => 'TEST_MESSAGE_OVERWRITE'];
    }

    public static function withValidator(Validator $validator, ?Request $request = null): void
    {
        $validator->after(fn (Validator $validator) => $validator->errors()->add('test', 'With Validator Test'));
    }
}

readonly class NullableSimpleDtoObject extends DataTransferObject
{
    public function __construct(public readonly ?string $test = null) {}
}

readonly class SimpleDateDtoObject extends DataTransferObject
{
    public function __construct(public readonly ?Carbon $test) {}

    public static function casts(): ?array
    {
        return ['test' => 'datetime'];
    }
}

readonly class SimpleImmutableDateDtoObject extends DataTransferObject
{
    public function __construct(public readonly ?CarbonImmutable $test) {}

    public static function casts(): ?array
    {
        return ['test' => 'datetime'];
    }
}

readonly class SimpleEnumDtoObject extends DataTransferObject
{
    public function __construct(public readonly TestEnum $testEnum) {}

    public static function casts(): ?array
    {
        return ['testEnum' => TestEnum::class];
    }
}

readonly class SimpleEnumArrayDtoObject extends DataTransferObject
{
    public function __construct(public readonly mixed $test_cast) {}

    public static function casts(): ?array
    {
        return ['test_cast' => TestEnum::class.'[]'];
    }
}

readonly class CastableDtoObject extends DataTransferObject
{
    public function __construct(public readonly mixed $test_cast) {}

    public static function casts(): ?array
    {
        return ['test_cast' => SimpleDtoObject::class];
    }
}

readonly class CastableArrayDtoObject extends DataTransferObject
{
    public function __construct(public readonly mixed $test_cast) {}

    public static function casts(): ?array
    {
        return ['test_cast' => SimpleDtoObject::class.'[]'];
    }
}

readonly class CastableNullableArrayDtoObject extends DataTransferObject
{
    public function __construct(public readonly mixed $test_cast) {}

    public static function casts(): ?array
    {
        return ['test_cast' => '?'.SimpleDtoObject::class.'[]'];
    }
}

readonly class NonCastableAObject extends DataTransferObject
{
    public function __construct(public readonly mixed $test_cast) {}

    public static function casts(): ?array
    {
        return ['test_cast' => 'test1234'];
    }
}

class DataTransferObjectTest extends TestCase
{
    private function prepareSimpleDtoObject(string $value = 'test'): object
    {
        return new SimpleDtoObject($value);
    }

    private function prepareSimpleModel(): Model
    {
        return new class extends Model
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

    public function test_it_can_make_dto_from_empty_json_string(): void
    {
        $dto = NullableSimpleDtoObject::make('');

        $this->assertNull($dto->test);
    }

    public function test_it_can_make_dto_from_request(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $dto = $mock::make((new Request)->merge(['test' => 'TestStringWithOverFifteenCharacters']));

        $this->assertSame('TestStringWithOverFifteenCharacters', $dto->test);
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

        $cast = new DataTransferObjectCast($mock::class, []);

        /* SET */
        $this->assertSame('{"test":"Test1234"}', $cast->set($model, 'data', ['test' => 'Test1234'], []));
        $this->assertNull($cast->set($model, 'data', null, []));

        /* GET */
        $this->assertInstanceOf($mock::class, $cast->get($model, 'data', '{"test":"Test1234"}', []));
    }

    public function test_it_is_castable_with_null_if_nullable(): void
    {
        $mock = $this->prepareSimpleDtoObject();
        $model = $this->prepareSimpleModel();

        $cast = new DataTransferObjectCast($mock::class, ['nullable']);

        /* GET */
        $this->assertSame(null, $cast->get($model, 'data', null, []));
    }

    public function test_it_castable_getter_throws_on_invalid_values(): void
    {
        $mock = $this->prepareSimpleDtoObject();
        $model = $this->prepareSimpleModel();

        $cast = new DataTransferObjectCast($mock::class, []);

        $this->expectExceptionMessage('data is not a string');
        $cast->get($model, 'data', null, []);
    }

    public function test_it_castable_setter_throws_on_invalid_values(): void
    {
        $mock = $this->prepareSimpleDtoObject();
        $model = $this->prepareSimpleModel();

        $cast = new DataTransferObjectCast($mock::class, []);

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
        $mock = $this->prepareSimpleDtoObject('');

        $request = (new Request)->merge(['test' => 'ABC']);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The test field must be at least 15 characters.');
        $mock::makeFromRequest($request);
    }

    public function test_it_can_add_complex_validations(): void
    {
        $mock = new ComplexValidationDtoObject('', '');

        $request = (new Request)->merge(['test_1' => 'ABC', 'test_2' => 'DEF']);

        $exception = null;

        try {
            $mock::makeFromRequest($request);
        } catch (ValidationException $exception) {
            $this->assertContains('With Validator Test', $exception->errors()['test']);
            $this->assertContains('The TEST_ATTRIBUTE_OVERWRITE field must be at least 15 characters.', $exception->errors()['test_1']);
            $this->assertContains('TEST_MESSAGE_OVERWRITE', $exception->errors()['test_2']);
        }

        $this->assertNotNull($exception);
    }

    public function test_it_can_cast_dates(): void
    {
        $mock = new SimpleDateDtoObject(Carbon::parse('01.01.2022'));

        $dto = $mock::makeFromArray(['test' => '02.02.2024']);

        $this->assertInstanceOf(Carbon::class, $dto->test);

        $dto = $mock::makeFromArray(['test' => null]);

        $this->assertNull($dto->test);

        $this->assertSame(['test' => ['date']], $mock::rules());
    }

    public function test_it_can_handle_immutable_dates(): void
    {
        Date::use(CarbonImmutable::class);

        $mock = new SimpleImmutableDateDtoObject(CarbonImmutable::parse('01.01.2022'));

        $dto = $mock::makeFromArray(['test' => '02.02.2024']);

        $this->assertInstanceOf(CarbonImmutable::class, $dto->test);

        $this->assertSame(['test' => '2024-02-02T00:00:00.000000Z'], $dto->toArray());
    }

    public function test_it_can_handle_nullable_casts(): void
    {
        $dto = CastableNullableArrayDtoObject::makeFromArray([
            'test_cast' => null,
        ]);

        $this->assertNull($dto->test_cast);
        $this->assertSame(['nullable', 'array'], $dto::rules()['test_cast']);
    }

    public function test_it_can_cast_enums(): void
    {
        $mock = new SimpleEnumDtoObject(TestEnum::A);

        $dto = $mock::makeFromArray(['testEnum' => 'B']);

        $this->assertInstanceOf(TestEnum::class, $dto->testEnum);
        $this->assertSame(TestEnum::B, $dto->testEnum);
        $this->assertSame(['testEnum' => 'B'], $dto->toArray());
        $this->assertCount(1, $mock::rules()['testEnum']);
        $this->assertInstanceOf(Enum::class, $mock::rules()['testEnum'][0]);
        $this->assertTrue($mock::rules()['testEnum'][0]->passes('testEnum', 'B'));
    }

    public function test_it_works_with_already_casted_enum(): void
    {
        $mock = new SimpleEnumDtoObject(TestEnum::A);

        $dto = $mock::makeFromArray(['testEnum' => TestEnum::B]);

        $this->assertSame(TestEnum::B, $dto->testEnum);
    }

    public function test_it_can_cast_to_an_enum_array(): void
    {
        $mock = new SimpleEnumArrayDtoObject('');

        $dto = $mock::makeFromArray(['test_cast' => [1 => 'B', 0 => 'A']]);

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
        $mock_b = new CastableDtoObject('');

        $dto = $mock_b::makeFromArray(['test_cast' => ['test' => 'A']]);

        $this->assertInstanceOf(DataTransferObject::class, $dto->test_cast);
        $this->assertSame(['test_cast' => ['test' => 'A']], $dto->toArray());
        $this->assertSame(
            [
                'test_cast' => ['array'],
                'test_cast.test' => ['min:15'],
            ],
            $mock_b::rules()
        );
    }

    public function test_it_works_with_already_casted_dto(): void
    {
        $mock_a = $this->prepareSimpleDtoObject('');

        $mock_b = new CastableDtoObject('');

        $dto = $mock_b::makeFromArray(['test_cast' => new $mock_a('A')]);

        $this->assertSame(['test_cast' => ['test' => 'A']], $dto->toArray());
    }

    public function test_it_can_cast_to_dto_array(): void
    {
        $dto = CastableArrayDtoObject::makeFromArray(['test_cast' => [['test' => 'A'], ['test' => 'B']]]);

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
                'test_cast.*.test' => ['min:15'],
            ],
            $dto->rules()
        );
    }

    public function test_it_can_cast_to_an_empty_dto_array(): void
    {
        $dto = CastableArrayDtoObject::makeFromArray(['test_cast' => []]);

        $this->assertIsArray($dto->test_cast);
        $this->assertCount(0, $dto->test_cast);
    }

    public function test_it_can_ignore_cast_rules(): void
    {
        $this->assertSame([], CastableArrayDtoObject::castRules(['test_cast']));
    }

    public function test_it_can_cast_to_empty_dto(): void
    {
        $dto = CastableDtoObject::makeFromArray(['test_cast' => null]);

        $this->assertNull($dto->test_cast);
    }

    public function test_it_throws_an_exception_with_an_unknown_cast(): void
    {
        $mock = new NonCastableAObject('');

        $this->expectExceptionMessage('Unknown cast "test1234"');

        $mock::makeFromArray(['test_cast' => []]);
    }

    public function test_it_can_manipulate_rules(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $this->assertSame(['child' => ['array'], 'child.test' => ['min:15']], $mock::appendRules([], 'child'));

        $this->assertSame(['child' => ['array'], 'child.*' => ['array'], 'child.*.test' => ['min:15']], $mock::appendArrayElementRules([], 'child'));
    }

    public function test_it_can_map_an_array_to_dto_arrays(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $result = $mock::mapToDtoArray([['test' => 'A'], ['test' => 'B']]);

        $this->assertCount(2, $result);
        $this->assertIsObject($result[0]);
        $this->assertIsObject($result[1]);
        $this->assertSame('A', $result[0]->test);
        $this->assertSame('B', $result[1]->test);
    }

    public function test_it_can_map_an_collection_to_dto_arrays(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $result = $mock::mapToDtoArray(collect([['test' => 'A'], ['test' => 'B']]));

        $this->assertCount(2, $result);
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

    public function test_it_can_map_to_dto_arrays_with_missing_key(): void
    {
        $mock = $this->prepareSimpleDtoObject();

        $result = $mock::mapToDtoArray(
            ['data' => [['test' => 'A'], ['test' => 'B']]],
            'test'
        );

        $this->assertSame([], $result);
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

        app(Request::class)->merge(['test' => 'TestStringWithOverFifteenCharacters']);

        $newMock = app($mock::class);

        $this->assertSame($mock::class, $newMock::class);
        $this->assertSame('TestStringWithOverFifteenCharacters', $newMock->test);

        $this->assertSame('TestStringWithOverFifteenCharacters', app($mock::class)->test);
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

    public function test_it_can_generate_a_new_data_transfer_object(): void
    {
        $this->artisan('make:dto Test')->assertSuccessful();

        $filesystem = app(Filesystem::class);

        $this->assertTrue($filesystem->exists(base_path('app/Data/TestData.php')));

        $filesystem->deleteDirectory(base_path('app/Data'));
    }
}
