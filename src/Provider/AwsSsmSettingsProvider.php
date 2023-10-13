<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Aws\Ssm\SsmClient;
use Helis\SettingsManagerBundle\Exception\ReadOnlyProviderException;
use Helis\SettingsManagerBundle\Exception\UnknownTypeException;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Model\Type;
use Helis\SettingsManagerBundle\Provider\Traits\ReadOnlyProviderTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class AwsSsmSettingsProvider extends SimpleSettingsProvider
{
    use ReadOnlyProviderTrait;

    private const TYPE_MAP = [
        'double' => Type::FLOAT,
        'boolean' => Type::BOOL,
        'array' => Type::YAML,
        'integer' => Type::INT,
        'string' => Type::STRING,
        'choice' => Type::CHOICE,
    ];

    public function __construct(private readonly SsmClient $ssmClient, private readonly DenormalizerInterface $denormalizer, private readonly array $parameterNames)
    {
        parent::__construct([]);
    }

    public function getSettings(array $domainNames): array
    {
        $this->fetch();

        return parent::getSettings($domainNames);
    }

    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        $this->fetch();

        return parent::getSettingsByName($domainNames, $settingNames);
    }

    public function getDomains(bool $onlyEnabled = false): array
    {
        $this->fetch();

        return parent::getDomains($onlyEnabled);
    }

    public function save(SettingModel $settingModel): bool
    {
        if (!in_array($settingModel->getName(), $this->parameterNames)) {
            throw new ReadOnlyProviderException(static::class);
        }

        $this->ssmClient->putParameter([
            'Name' => $settingModel->getName(),
            'Overwrite' => true,
            'Type' => 'String',
            'Value' => json_encode($settingModel->getData()),
        ]);

        return parent::save($settingModel);
    }

    private function fetch(): void
    {
        $result = $this->ssmClient->getParameters(['Names' => $this->parameterNames]);
        foreach ($result->get('Parameters') as $parameter) {
            $value = @json_decode((string)$parameter['Value'], true);
            if ($value === null) {
                $value = $parameter['Value'];
            }

            $setting = $this->denormalizer->denormalize(
                [
                    'name' => $parameter['Name'],
                    'domain' => [
                        'name' => DomainModel::DEFAULT_NAME,
                        'enabled' => true,
                    ],
                    'type' => $this->resolveType($value),
                    'data' => [
                        'value' => $value,
                    ],
                ],
                SettingModel::class
            );
            $this->settings[] = $setting;
        }
    }

    private function resolveType(mixed $value): string
    {
        $type = gettype($value);

        if (isset(self::TYPE_MAP[$type])) {
            return self::TYPE_MAP[$type]->value;
        }

        throw new UnknownTypeException($type);
    }
}
