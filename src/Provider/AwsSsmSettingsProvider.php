<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Aws\Ssm\SsmClient;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Model\Type;
use Helis\SettingsManagerBundle\Provider\Traits\ReadOnlyProviderTrait;
use Symfony\Component\Serializer\SerializerInterface;

class AwsSsmSettingsProvider extends SimpleSettingsProvider
{
    use ReadOnlyProviderTrait;

    private $ssmClient;
    private $serializer;
    private $parameterNames;
    private $fetched;

    public function __construct(SsmClient $ssmClient, SerializerInterface $serializer, array $parameterNames)
    {
        parent::__construct([]);

        $this->ssmClient = $ssmClient;
        $this->serializer = $serializer;
        $this->parameterNames = $parameterNames;
        $this->fetched = false;
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
        $this->ssmClient->putParameter([
            'Name' => $settingModel->getName(),
            'Overwrite' => true,
            'Type' => 'String',
            'Value' => $settingModel->getData(),
        ]);

        return parent::save($settingModel);
    }

    private function fetch(): void
    {
        if ($this->fetched === true) {
            return;
        }

        $result = $this->ssmClient->getParameters(['Names' => $this->parameterNames]);
        foreach ($result->get('Parameters') as $parameter) {
            $setting = $this->serializer->denormalize(
                [
                    'name' => $parameter['Name'],
                    'domain' => [
                        'name' => DomainModel::DEFAULT_NAME,
                        'enabled' => true,
                    ],
                    'type' => Type::STRING,
                    'data' => [
                        'value' => $parameter['Value'],
                    ],
                ],
                SettingModel::class
            );
            $this->settings[] = $setting;
        }

        $this->fetched = true;
    }
}
