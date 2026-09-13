# UPGRADING

## From v1 to v2

### Requirements

- PHP 8.3+
- Laravel 12 or 13

Support for Laravel 10 and 11 has been dropped.

### Concern / Contracts instead of abstract class

To get rid of the `readonly` limitations introduced by the abstract class, the code was converted into a contract and a matching concern:

```php
use Ccharz\DtoLite\Concerns\IsDataTransferObject;
use Ccharz\DtoLite\Contracts\DataTransferObject;

readonly class ContactData implements DataTransferObject {
    use IsDataTransferObject;

    public function __construct(
        public string $name,
        public string $email,
        public ContactType $type,
    ) {}
}
```

### Renamed `resourceCollection()` to `collection()`

To keep the API similar to Laravel the method to generate a collection of data transfer objects was renamed to `collection()`.

### `casts()` and `rules()` no longer return `null`

Both now return `array` instead of `?array`.

### Casts moved to the `Casts` namespace

- `Ccharz\DtoLite\DataTransferObjectCast` → `Ccharz\DtoLite\Casts\AsDataTransferObject`
- `Ccharz\DtoLite\AsDataTransferObjectCollection` → `Ccharz\DtoLite\Casts\AsDataTransferObjectCollection`

### `mapToDtoArray()` accepts `iterable`

The first parameter was widened from `ArrayAccess|array` to `iterable`.

### Rules pass request

All rule methods now pass the request down.

### `ksort()` removed

The `ksort()` from the array cast was removed — we added a `normalizeCastArray()` method where you can add the `ksort()` back to keep the current behaviour.

### Cast Exceptions

Casting now throws if an unexpected value occurs.
