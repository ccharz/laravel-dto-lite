# Laravel Data Transfer Object Lite

This is a basic implementation of the data transfer object (DTO) concept. The idea is to describe input and output of data in one simple basic php class file. It is meant to replace FormRequests and Resources and can also be used to automatically generate typescript definitions.

This package is similar to the [Laravel Data](https://spatie.be/docs/laravel-data) Package from Spatie. The main difference it contains no reflection class magic and only provides the basic functionalities.

## Installation

```bash
composer require ccharz/laravel-dto-lite
```

## Usage


### Example DTO
```php
enum ContactType : string {
    case PERSON = 'person';
    case COMPANY = 'company';
}

readonly class ContactData extends DataTransferObject {
    public function __construct(
        public string $name,
        public string $email,
        public ContactType $type,
    ) {}
}
```

### Casts

Casts for attributes can be defined similar to eloquent casts. It is also possible to define casts to an array

```php
enum ContactType : string {
    case PERSON = 'person';
    case COMPANY = 'company';
}

readonly class AddressData extends DataTransferObject
{
    public function __construct(
        public ?string $country = null,
        public ?string $zip = null,
        public ?string $location = null,
        public ?string $street = null,
        public ?string $streetnumber = null,
        public ?string $stair = null,
        public ?string $top = null,
    ) {
    }
}

readonly class ContactData extends DataTransferObject {
    public function __construct(
        /** @var AddressData[] $addresses */
        public array $addresses,
        public DateTime $birthday,
        public ContactType $type,
    ) {}

    public static function casts(): array
    {
        return [
            'addresses' => AddressData::class . '[]',
            'birthday' => 'datetime',
            'type' =>  ContactType::class,
        ];
    }
}

```

### Validation

If a rules method exists, validation is automatically performed when creating a DTO from a request manually or by automatic dependency injection.


```php
use Illuminate\Http\Request;

public static function rules(?Request $request = null): ?array
{
    return ['prename' => 'min:2'];
}
```

You can also inject the rules from casts in your rules:


```php
use Illuminate\Http\Request;

public static function rules(?Request $request = null): ?array
{
    return [
        ...parent::castRules(),
    ];
}
```


### Automatic Injection

With the help if laravels dependency injection, the dto can be used in a controller method function and is automatically filled with the **validated** input data from the request.
```php
public function store(ContactData $contactData): RedirectResponse
{
    Contact::create([
        'name' => $contactData->name,
        'email' => $contactData->email,
    ]);

    return redirect()->back();
}
```

### Eloquent Castable

DataTransferObjects can be used as a cast in eloquent models

```php
class Contact extends Model
{
    protected $casts = [
        'address' => AddressData::class,
    ];
}
```

If the column is nullable, you have to append the cast parameter "nullable":

```php
class Contact extends Model
{
    protected $casts = [
        'address' => AddressData::class . ':nullable',
    ];
}
```

Casting arrays of Data Transfer Objects:

```php
use Ccharz\DtoLite\AsDataTransferObjectCollection;

/**
 * Get the attributes that should be cast.
 *
 * @return array<string, string>
 */
protected function casts(): array
{
    return [
        'addresses' => AsDataTransferObjectCollection::of(AddressData::class),
    ];
}
```

### Response

Data Transfer Objects are automatically converted to an response if returned from a controller

```php
class ContactController extends Controller {
    public function show(Contact $contact): ContactData
    {
        return ContactData::make($contact);
    }
}
```

You can also return a [resource collection](https://laravel.com/docs/11.x/eloquent-resources#resource-collections) of data transfer objects

```php
class ContactController extends Controller {
    public function index()
    {
        return ContactData::resourceCollection(Contact::paginate());
    }
}
```

### Map to DTO Array

An array can automatically be mapped to an array of the DTO:

```php
$addresses = [
    [
        'country' => 'AT',
        'zip' => '8010',
    ],
    [
        'country' => 'AT',
        'zip' => '8010',
    ]
];

AddressData::mapToDtoArray($addresses);
```


## Typescript Definitions

You can use https://github.com/spatie/laravel-typescript-transformer to automatically generate typescript definitions for your Data Transfer Objects and Enums.

```php
namespace App\Data;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
readonly class AddressData extends DataTransferObject
{
    public function __construct(
        public readonly ?string $country = null,
        public readonly ?string $zip = null,
        public readonly ?string $location = null,
        public readonly ?string $street = null,
        public readonly ?string $streetnumber = null,
        public readonly ?string $stair = null,
        public readonly ?string $top = null,
    ) {
    }
}
```

generates to the following typescript definition:

```js
declare namespace App.Data {
    export type AddressData = {
        country: string | null;
        zip: string | null;
        location: string | null;
        street: string | null;
        streetnumber: string | null;
        stair: string | null;
        top: string | null;
    };
}
```

## Artisan Command

To create a new data transfer object, use the make:dto Artisan command:

```bash
php artisan make:dto Address
```
