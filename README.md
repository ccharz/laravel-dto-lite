# Laravel Data Transfer Object Lite

This is a basic implementation of the data transfer object (DTO) concept. The idea is to describe input and output of data in one simple basic php class file. It is meant to replace FormRequests and Resources and can also be used to automatically generate typescript definitions.

This package is similar to the [Laravel Data](https://spatie.be/docs/laravel-data) Package from Spatie. The main difference it contains no reflection class magic and only provides the basic functionalities.

## Usage


### Example DTO
```php
enum ContactType : string {
    case PERSON = 'person';
    case COMPANY = 'company';
}

class ContactData extends DataTransferObject {
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ContactType $type,
    ) {}
}
```

### Validation

If a rules method exists, validation is automatically performed when creating a DTO from a request manually or by automatic dependency injection.


```php
public static function rules(): ?array
{
    return ['prename' => 'min:2'];
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


## Typescript Definitions

You can use https://github.com/spatie/laravel-typescript-transformer to automatically generate typescript definitions for your Data Transfer Objects and Enums.

```php
namespace App\Data;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AddressData extends DataTransferObject
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
