# UPGRADING

## From v1 to v2

### Requirements

- PHP 8.3+
- Laravel 12 or 13

Support for Laravel 10 and 11 has been dropped.

### Concern / Contracts instead of abstract class

To get rid of the readonly limitations the abstract class solution introduced, the code was converted into a contract and a matching concern:

```php
use Ccharz\DtoLite\Concerns\IsDataTransferObject;
use Ccharz\DtoLite\Contracts\DataTransferObject;

class ContactData implements DataTransferObject {
    use IsDataTransferObject;

    public function __construct(
        public string $name,
        public string $email,
        public ContactType $type,
    ) {}
}
```

### Renamed resourceCollection() to collection()

To keep the API similar to Laravel the method to generate a collection of data transfer objects was renamed to `collection()`

