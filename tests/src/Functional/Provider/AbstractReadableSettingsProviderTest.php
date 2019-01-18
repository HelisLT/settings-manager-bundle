<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Tests\Functional\Provider;

use App\Entity\Setting;
use App\Entity\Tag;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Model\Type;
use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Liip\FunctionalTestBundle\Test\WebTestCase;

abstract class AbstractReadableSettingsProviderTest extends WebTestCase
{
    /**
     * @var SettingsProviderInterface
     */
    protected $provider;

    protected function setUp()
    {
        parent::setUp();

        $this->provider = $this->createProvider();
    }

    abstract protected function createProvider(): SettingsProviderInterface;

    protected function getSettingFixtures(): array
    {
        $tag1 = new Tag();
        $tag1->setName('fixture');

        $domain1 = new DomainModel();
        $domain1->setName('default');

        $domain2 = new DomainModel();
        $domain2->setName('sea');
        $domain2->setEnabled(true);

        $domain3 = new DomainModel();
        $domain3->setName('apples');
        $domain3->setEnabled(false);

        $setting0 = new Setting();
        $setting0
            ->setName('bazinga')
            ->setDescription('fixture bool baz setting')
            ->setType(Type::BOOL())
            ->setDomain($domain1)
            ->setData(false)
            ->addTag($tag1);

        $setting1 = new Setting();
        $setting1
            ->setName('foo')
            ->setDescription('fixture bool foo setting')
            ->setType(Type::BOOL())
            ->setDomain($domain1)
            ->setData(true)
            ->addTag($tag1);

        $setting2 = new Setting();
        $setting2
            ->setName('tuna')
            ->setDescription('fixture string tuna setting')
            ->setType(Type::STRING())
            ->setDomain($domain2)
            ->setData('fishing')
            ->addTag($tag1);

        $setting3 = new Setting();
        $setting3
            ->setName('banana')
            ->setDescription('fixture int banana setting')
            ->setType(Type::INT())
            ->setDomain($domain3)
            ->setData(10);

        $setting4 = new Setting();
        $setting4
            ->setName('kiwi')
            ->setDescription('fixture float kiwi setting')
            ->setType(Type::FLOAT())
            ->setDomain($domain3)
            ->setData(1.2);

        return [
            $setting0,
            $setting1,
            $setting2,
            $setting3,
            $setting4,
        ];
    }

    public function dataProviderTestGetSettings(): array
    {
        return [
            [
                ['default'],
                [
                    ['bazinga', 'default', 'bool', false],
                    ['foo', 'default', 'bool', true],
                ],
            ],
            [
                ['default', 'apples'],
                [
                    ['banana', 'apples', 'int', 10],
                    ['bazinga', 'default', 'bool', false],
                    ['foo', 'default', 'bool', true],
                    ['kiwi', 'apples', 'float', 1.2],
                ],
            ],
            [
                ['default', 'apples', 'sea'],
                [
                    ['banana', 'apples', 'int', 10],
                    ['bazinga', 'default', 'bool', false],
                    ['foo', 'default', 'bool', true],
                    ['kiwi', 'apples', 'float', 1.2],
                    ['tuna', 'sea', 'string', 'fishing'],
                ],
            ]
        ];
    }

    /**
     * @dataProvider dataProviderTestGetSettings
     */
    public function testGetSettings(array $domainNames, array $expectedSettingsMap)
    {
        $settings = $this->provider->getSettings($domainNames);

        $map =  array_map(function (SettingModel $model) {
            return [
                $model->getName(),
                $model->getDomain()->getName(),
                $model->getType()->getValue(),
                $model->getData(),
            ];
        }, $settings);

        usort($map, function ($a, $b) {
            return $a[0] <=> $b[0];
        });

        usort($expectedSettingsMap, function ($a, $b) {
            return $a[0] <=> $b[0];
        });

        $this->assertEquals($expectedSettingsMap, $map);
    }

    public function dataProviderTestGetSettingsByName(): array
    {
        return [
            [
                ['default'],
                ['bazinga'],
                [
                    ['bazinga', 'default', 'bool', false],
                ]
            ],
            [
                ['default'],
                ['bazinga', 'foo'],
                [
                    ['bazinga', 'default', 'bool', false],
                    ['foo', 'default', 'bool', true],
                ]
            ],
            [
                ['default', 'sea'],
                ['foo', 'tuna'],
                [
                    ['foo', 'default', 'bool', true],
                    ['tuna', 'sea', 'string', 'fishing'],
                ]
            ],
            [
                ['default', 'sea', 'apples'],
                ['foo', 'tuna', 'kiwi', 'persimon'],
                [
                    ['foo', 'default', 'bool', true],
                    ['tuna', 'sea', 'string', 'fishing'],
                    ['kiwi', 'apples', 'float', 1.2],
                ]
            ],
            [
                ['default', 'sea', 'apples', 'pear'],
                ['foo', 'tuna', 'kiwi'],
                [
                    ['foo', 'default', 'bool', true],
                    ['tuna', 'sea', 'string', 'fishing'],
                    ['kiwi', 'apples', 'float', 1.2],
                ]
            ],
        ];
    }

    /**
     * @dataProvider dataProviderTestGetSettingsByName
     */
    public function testGetSettingsByName(array $domainNames, array $settingNames, array $expectedSettingsMap)
    {
        $settings = $this->provider->getSettingsByName($domainNames, $settingNames);

        $map =  array_map(function (SettingModel $model) {
            return [
                $model->getName(),
                $model->getDomain()->getName(),
                $model->getType()->getValue(),
                $model->getData(),
            ];
        }, $settings);

        usort($map, function ($a, $b) {
            return $a[0] <=> $b[0];
        });

        usort($expectedSettingsMap, function ($a, $b) {
            return $a[0] <=> $b[0];
        });

        $this->assertEquals($map, $expectedSettingsMap);
    }

    public function testGetDomains()
    {
        $domainNames = array_map(function (DomainModel $model) {
            return $model->getName();
        }, $this->provider->getDomains(false));

        sort($domainNames);

        $this->assertEquals(['apples', 'default', 'sea',], $domainNames);
    }

    public function testGetOnlyEnabledDomains()
    {
        $domainNames = array_map(function (DomainModel $model) {
            return $model->getName();
        }, $this->provider->getDomains(true));

        sort($domainNames);

        $this->assertEquals(['default', 'sea'], $domainNames);
    }
}
