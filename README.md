# Helis Settings Manager Bundle

Provides a nice way to define variables and inject them into application parts.

 - Supporting `bool`, `string`, `int`, `float`, `array` as setting values.
 - Multiple providers.
 - User interface.

[![Build Status](https://travis-ci.org/HelisLT/settings-manager-bundle.svg?branch=master)](https://travis-ci.org/HelisLT/settings-manager-bundle)
[![Latest Stable Version](https://poser.pugx.org/helis/settings-manager-bundle/v/stable)](https://packagist.org/packages/helis/settings-manager-bundle)
[![License](https://poser.pugx.org/helis/settings-manager-bundle/license)](https://packagist.org/packages/helis/settings-manager-bundle)

## Jump to

 - [Quick start](#quick-start)
 - [Usage](#usage)
 - [Models](#models)
 - [Setting providers](#setting-providers)
 - [Configuration reference](#configuration-reference)
 - [User interface](#user-interface)
 - [Twig](#twig)
 - [Controller](#controller)

## Quick start

 1. `composer require helis/settings-manager-bundle`

 2. Register bundle to `AppKernel.php` (Symfony3) or `config/bundles.php` (Symfony4)

```php
<?php

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        return [
            new Helis\SettingsManagerBundle\HelisSettingsManagerBundle(),
        ];
    }
}
```

 3. Add an example configuration to `app/config/config.yml` (Symfony3) or `config/packages/settings_manager.yaml` (Symfony4)

```yaml
helis_settings_manager:
    settings:
        - name: foo
          description: 'foo desc'
          type: bool
          data: false
          tags:
              - 'super_switch'

        - name: baz
          description: 'master toggle for awesome new feature'
          type: string
          data: fish
          tags:
              - 'experimental'
              - 'poo'
```

 4. Now, the easiest way to get settings in your services is by using `SettingsRouterAwareTrait`. The service will be automatically injected by [autowire](https://symfony.com/doc/current/service_container/autowiring.html#autowiring-other-methods-e-g-setters). Then just ask for setting:

```php
use Helis\SettingsManagerBundle\Settings\Traits\SettingsRouterAwareTrait;

class MuchAmazingService
{
    use SettingsRouterAwareTrait;

    public function doSmth()
    {
        if ($this->settingsRouter->getBool('foo')) {
            // do it
        }

        // just do it
    }
}
```

## Usage

To get settings into your services, you have a few choices:

 - [SettingsRouter](#settings-router)
 - [Service Tag](#service-tag)

### SettingsRouter

SettingsRouter is pretty straight-forward. It has one main method, called `$settingsRouter->get($settingName, $default = null)`, which returns a setting of any type. If the setting is missing, default value will be returned. Other getters are aliases for `get` but with declared return types and appropriate default values.

| Method name | Default value | Declared return type |
|-------------|---------------|----------------------|
| `getString` | `''`          | `string`             |
| `getBool`   | `false`       | `bool`               |
| `getInt`    | `0`           | `int`                |
| `getFloat`  | `0.0`         | `float`              |
| `getArray`  | `[]`          | `array`              |

### Service Tag

If you dont want to inject `SettingsRouter` or wish for a cleaner service, service tags are here to help. First of all, the service must have a setter, which can be used to inject a setting value. For bool values, the bundle provides the `SwitchableTrait`, which adds `setEnabled` and `isEnabled` methods. Then add a tag on your service with attributes `setting` for setting name and `method` for method name. Example:

```yaml
AppBundle\Service\AmazingService:
    tags:
        - { name: settings_manager.setting_aware, setting: foo, method: setEnabled }
```

## Models

#### `Helis\SettingsManagerBundle\Model\SettingModel`

Base setting model.

| Property      | Type            | Description
| --------------|-----------------|-------------------------------
| $name         | string          | Setting name
| $description  | string          | Setting descrption
| $domain       | DomainModel     | Domain model
| $tags         | Collection[Tag] | Collection of tags
| $type         | Enum[Type]      | Determines setting value type
| $data         | array           | Holds actual value for setting
| $providerName | string          | Internal field to know from which provider this setting is

#### `Helis\SettingsManagerBundle\Model\DomainModel`

Domain is like a group for settings. Setting cannot exist without domain. The default is named `default`, which is also always enabled. Domain can hold only one setting with the same name. Settings with the same names must be in different domains. When a setting is requested, the one from a higher priority domain will be returned.

| Property      | Type                  | Description
| --------------|-----------------------|-------------------------------
| $name         | string                | Domain name
| $priority     | int (default: 0)      | Domain priority
| $enabled      | bool (default: false) | Is domain enabled indication
| $readOnly     | bool (default: false) | is domain only readable indication

#### `Helis\SettingsManagerBundle\Model\Type`

Enum which holds supported types for setting. Values:

 - STRING
 - BOOL
 - INT
 - FLOAT
 - YAML
 - CHOICE

## Setting providers

Settings can be pulled from multiple sources. Currently, the bundle comes with 4 settings providers. They can be configured and prioritized. **If a setting with the same name will come from >1 providers, setting from provider with higher priority will override settings from lower priority providers**.

Settings can be easily mutated in providers using [user interface](#user-interface).

Settings providers:

 - [Simple](#simple-settings-provider)
 - [DoctrineORM](#doctrineorm-settings-provider)
 - [Cookie](#cookie-settings-provider)
 - [AWS SSM](#aws-ssm-settings-provider)

And additional decorating providers:

 - [Phpredis](#phpredis-decorating-settings-provider)
 - [Predis](#predis-decorating-settings-provider)
 - [Cache](#cache-decorating-provider)

### Simple settings provider

`Helis\SettingsManagerBundle\Provider\SimpleSettingsProvider`

This is a provider, which only holds settings collections. Currently, it's being used to hold settings from configuration, but many more can be configured.

To configure additional simple providers, factory is provided because provider can only accept already denormalized objects.

Configuration example:

```yaml
setting_provider_factory.foo:
    class: Helis\SettingsManagerBundle\Provider\Factory\SimpleSettingsProviderFactory
    arguments:
        $serializer: '@settings_manager.serializer'
        $normalizedData:
            -
                - name: foo
                  description: 'foo desc'
                  type: bool
                  domain: { name: default } 
                  data: { value: false }
                  tags: [{ name: 'super_switch' }]
    tags:
        - { name: settings_manager.provider_factory, provider: foo, priority: 10 }
```

### DoctrineORM settings provider

`Helis\SettingsManagerBundle\Provider\DoctrineOrmSettingsProvider`

This is a provider which reads and saves settings using `EntityManagerInterface`.

Required libraries:

 - [doctrine/orm](https://github.com/doctrine/doctrine2)
 - [acelaya/doctrine-enum-type](https://github.com/acelaya/doctrine-enum-type)

 > I am guessing you already have it :open_mouth:  
 `composer require doctrine/orm acelaya/doctrine-enum-type`

Configuration example:

 1. Doctrine configuration

```yaml
# Symfony3, app/config/config.yml
# Symfony4, config/packages/doctrine.yaml
doctrine:
    orm:
        mappings:
            HelisSettingsManagerBundle:
                type: yml
                is_bundle: true
                dir: "Resources/config/doctrine"
                alias: HelisSettingsManagerBundle
                prefix: Helis\SettingsManagerBundle
```

 2. Create setting entity

```php
<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Helis\SettingsManagerBundle\Model\SettingModel;

/**
 * @ORM\Entity()
 * @ORM\Table(name="setting")
 */
class Setting extends SettingModel
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
}
```

 3. Update your doctrine schema.

 4. Register settings provider:

```yaml
Helis\SettingsManagerBundle\Provider\DoctrineOrmSettingsProvider:
    arguments:
        $entityManager: '@doctrine.orm.default_entity_manager'
        $settingsEntityClass: 'App\Entity\Setting'
    tags:
        - { name: settings_manager.provider, provider: orm, priority: 20 }
```

### Cookie settings provider

`Helis\SettingsManagerBundle\Provider\CookieSettingsProvider`

This is a provider, which only enables existing settings by using a cookie. Cookies are encoded, so that they could not be randomly enabled by users.

Required libraries:

 - [paragonie/paseto](https://github.com/paragonie/paseto)

 > `composer require paragonie/paseto`

 `Paseto` is used to encrypt cookies.

 Configuration example:

```yaml
Helis\SettingsManagerBundle\Provider\CookieSettingsProvider:
    arguments:
        $serializer: '@settings_manager.serializer'
    tags:
        - { name: settings_manager.provider, provider: cookie, priority: 30 }
        - { name: kernel.event_subscriber }
```

### Asymmetric Cookie settings provider

`Helis\SettingsManagerBundle\Provider\AsymmetricCookieSettingsProvider`

This is a provider, which only enables existing settings by using a cookie. Cookies are encoded with asymmetric private
and public keys, so that they could not be randomly enabled by users.

Required libraries:

 - [paragonie/paseto](https://github.com/paragonie/paseto)

 > `composer require paragonie/paseto`

 `Paseto` is used to encrypt cookies.

 Configuration example:

```yaml
Helis\SettingsManagerBundle\Provider\AsymmetricCookieSettingsProvider:
    arguments:
        $serializer: '@settings_manager.serializer'
    tags:
        - { name: settings_manager.provider, provider: asymmetric_cookie, priority: 40 }
        - { name: kernel.event_subscriber }
```

### JWT Cookie settings provider

`Helis\SettingsManagerBundle\Provider\JwtCookieSettingsProvider`

This is a provider, which only enables existing settings by using a cookie. Cookies are encoded with asymmetric private
and public keys, so that they could not be randomly enabled by users.

Required libraries:

 - [lcobucci/jwt](https://github.com/lcobucci/jwt)

 > `composer require lcobucci/jwt`

 `JWT` is used to encrypt cookies.

 Configuration example:

```yaml
Helis\SettingsManagerBundle\Provider\JwtCookieSettingsProvider:
    arguments:
        $serializer: '@settings_manager.serializer'
        $publicKeyPath: '%kernel.project_dir%/config/keys/settings_cookie_public.key'
        $privateKeyPath: '%kernel.project_dir%/config/keys/settings_cookie_private.key'
    tags:
        - { name: settings_manager.provider, provider: jwt_cookie, priority: 50 }
        - { name: kernel.event_subscriber }
```

### AWS SSM settings provider

`Helis\SettingsManagerBundle\Provider\AwsSsmSettingsProvider`

This is a provider, which is used only for reading and updating existing ssm parameters as settings.

Required libraries:

 - [aws/aws-sdk-php](https://github.com/aws/aws-sdk-php)

 > `composer require aws/aws-sdk-php`

Configuration example:

```yaml
Helis\SettingsManagerBundle\Provider\AwsSsmSettingsProvider:
    arguments:
        - '@Aws\Ssm\SsmClient'
        - '@settings_manager.serializer'
        - ['amazing_parameter_name']
    tags:
        - { name: settings_manager.provider, provider: aws_ssm }
```

### Phpredis decorating settings provider

`Helis\SettingsManagerBundle\Provider\DecoratingRedisSettingsProvider`

This provider is used to cache other settings providers like [DoctrineORM](#doctrineorm-settings-provider) or [AWS SSM](#aws-ssm-settings-provider). It uses Redis client, not [doctrine/cache providers](https://github.com/doctrine/cache) or [symfony/cache adapters](https://github.com/symfony/cache) because we want to take advantage of redis data structures for simplier invalidation process.

Required extensions:

 - [phpredis](https://github.com/phpredis/phpredis)

 > `pecl install redis-3.1.6`

Configuration example:

```yaml
Helis\SettingsManagerBundle\Provider\DecoratingRedisSettingsProvider:
    decorates: 'Helis\SettingsManagerBundle\Provider\DoctrineOrmSettingsProvider'
    arguments:
        $decoratingProvider: 'Helis\SettingsManagerBundle\Provider\DecoratingRedisSettingsProvider.inner'
        $redis: '@settings.cache.redis' # you need to register your own \Redis client in container
        $serializer: '@settings_manager.serializer'
```

### Predis decorating settings provider

`Helis\SettingsManagerBundle\Provider\DecoratingPredisSettingsProvider`

Same as [phpredis decorating settings provider](#phpredis-decorating-settings-provider) It just replaces the phpredis extension with [predis](https://github.com/nrk/predis).

Required libraries:

 - [predis/predis](https://github.com/nrk/predis)

 > `composer require predis/predis`

### Cache decorating provider

`Helis\SettingsManagerBundle\Provider\DecoratingCacheSettingsProvider`

This provider is used to cache other settings providers that implements `ModificationAwareSettingsProviderInterface`. At the moment supports [phpredis decorating settings provider](#phpredis-decorating-settings-provider) and [predis decorating settings provider](#predis-decorating-settings-provider). It uses Symfony [PHP Files Cache Adapter](https://symfony.com/doc/current/components/cache/adapters/php_files_adapter.html). Single change in decorating provider causes whole cache to be invalidated.
Supports [symfony cache component adapters](https://symfony.com/doc/current/components/cache.html#available-cache-adapters)

Required libraries and extensions:

- [symfony/cache](https://symfony.com/doc/current/components/cache.html)
- [symfony/lock](https://symfony.com/doc/current/components/lock.html)

> `composer require symfony/cache`
> 
> `composer require symfony/lock`

Configuration example:

```yaml
settings_manager.decorating_provider.cache:
    class: 'Helis\SettingsManagerBundle\Provider\DecoratingCacheSettingsProvider'
    decorates: 'Helis\SettingsManagerBundle\Provider\DecoratingRedisSettingsProvider'
    arguments:
        $decoratingProvider: '@settings_manager.decorating_provider.cache.inner'
        $serializer: '@settings_manager.serializer'
        $cache: '@cache.settings'
        $lockFactory: '@symfony_flock_factory'
```

## Configuration reference

```yaml
helis_settings_manager:
    settings:
        -
            name: foo
            description: 'foo desc'
            domain: default # Used for grouping settings.
            type: bool
            data: false
            tags: [super_switch]
    profiler:
        enabled: false
    logger:
        enabled: false
        service_id: null # Psr\Log\LoggerInterface service id
    settings_files:
        # - '%kernel.root_dir%/config/extra_settings.yml'
```

## User interface

User interface can be used to change setting values, enable or disable domains.

 1. Bundled user interface requires [knp-menu-bundle](https://github.com/KnpLabs/KnpMenu), [jsrouting-bundle](https://github.com/FriendsOfSymfony/FOSJsRoutingBundle).

    `composer require symfony/translation symfony/twig-bundle symfony/asset knplabs/knp-menu-bundle friendsofsymfony/jsrouting-bundle`

 2. Include routing file.

```yaml
# Symfony3, app/config/routing.yml 
# Symfony4, config/routes/settings_manager.yaml

settings_manager:
    resource: '@HelisSettingsManagerBundle/Resources/config/routing.yml'
    prefix: /settings
```

That's it. Now go to the `/settings` path and you will see the settings user interface.

## Twig

The Twig extension is also added to get settings in your twig templates. Just like in `SettingsRouter`, first argument is the setting name and the second sets default value.

```twig
{{ setting_get('foo', false) }}
```

## Controller

`Helis\SettingsManagerBundle\Controller\Traits\SettingsControllerTrait`

Adds a method to deny access, unless a setting is enabled. It's using `SettingsRouter`, which, again, will be injected by [autowire](https://symfony.com/doc/current/service_container/autowiring.html#autowiring-other-methods-e-g-setters).

```php
public function indexAction(): Response
{
    $this->denyUnlessEnabled('index_page');
    ...
}
```

## Contribution

New feature branches should be created from the master branch.
