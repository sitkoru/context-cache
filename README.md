# context-cache

Унифицированная абстракция над API Яндекс.Директ и GoogleAdWords с функцией кэширования

Используются библиотеки [sitkoru/yandex-direct-api](https://github.com/sitkoru/yandex-direct-api) и [googleads/googleads-php-lib](https://github.com/googleads/googleads-php-lib).

Реализование кэширование сущностей в MongoDB.

## Установка

```bash
composer require sitkoru/context-cache
```

## Использование

### Подготовка

Необходимо инициировать аннотации. Замените

```php
require __DIR__ . '/vendor/autoload.php';
```

На

```php
$loader = require __DIR__ . '/vendor/autoload.php';
AnnotationRegistry::registerLoader([$loader, 'loadClass']);
```

### Первый вызов

Для примера, получим список кампаний аккаунта в Яндекс.Директ

```php
$cacheProvider = new MongoDbCacheProvider('mongodb://mongodb');
$logger = new Logger('directLogger');
$logger->pushHandler(new ErrorLogHandler());
$contextEntitiesProvider = new ContextEntitiesProvider($cacheProvider, $logger);
$provider = $contextEntitiesProvider->getDirectProvider("ваш токен", "ваш логин");
$campaigns = $provider->campaigns->getAll([]);
```

Тоже самое для Google AdWords

```php
$cacheProvider = new MongoDbCacheProvider('mongodb://mongodb');
$logger = new Logger('adWordsLogger');
$logger->pushHandler(new ErrorLogHandler());
$contextEntitiesProvider = new ContextEntitiesProvider($cacheProvider, $logger);
$provider = $contextEntitiesProvider->getAdWordsProvider("айди клиента", "путь к файлу auth.ini");
$campaigns = $provider->campaigns->getAll([]);
```