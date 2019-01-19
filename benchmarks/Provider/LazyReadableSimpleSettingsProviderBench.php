<?php
declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Benchmarks\Provider;

use Helis\SettingsManagerBundle\Benchmarks\Benchmark;
use Helis\SettingsManagerBundle\Provider\LazyReadableSimpleSettingsProvider;
use Helis\SettingsManagerBundle\Serializer\Normalizer\DomainModelNormalizer;
use Helis\SettingsManagerBundle\Serializer\Normalizer\SettingModelNormalizer;
use Helis\SettingsManagerBundle\Serializer\Normalizer\TagModelNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

class LazyReadableSimpleSettingsProviderBench extends Benchmark
{
    /**
     * @var LazyReadableSimpleSettingsProvider
     */
    protected $provider;

    public function setUp(): void
    {
        $normSettingsByDomain = [];
        $normDomains = [];

        for ($i = 0; $i < 150; $i++) {
            $domainName = 'domain_' . $i;
            $domain = [
                'name' => $domainName,
                'enabled' => ($i % 3) !== 0,
                'read_only' => false,
                'priority' => 0,
            ];
            $normDomains[$domainName] = $domain;
        }

        for ($i = 0; $i < 300; $i++) {
            $domainName = 'domain_' . $i % 150;
            $settingName = 'setting_' . $i;

            $normSettingsByDomain[$domainName][$settingName] = [
                'name' => $settingName,
                'description' => 'It\'s not who I am underneath but what I do that defines me.',
                'domain' => $normDomains[$domainName],
                'type' => 'bool',
                'data' => ['value' => $i % 2],
            ];
        }

        $this->provider = new LazyReadableSimpleSettingsProvider(
            new Serializer(
                [
                    new ArrayDenormalizer(),
                    new SettingModelNormalizer(),
                    new DomainModelNormalizer(),
                    new TagModelNormalizer(),
                ],
                [
                    new JsonEncoder()
                ]
            ),
            $normSettingsByDomain,
            $normDomains
        );
    }

    public function provideDomainNames()
    {
        #0: first 50 domains
        $domainNames = [];
        for ($i = 0; $i < 50; $i++) {
            $domainNames[] = 'domain_' . $i;
        }
        yield 'first 50 domains' => ['domainNames' => $domainNames];

        #1: first 100 domains
        for ($i = 50; $i < 100; $i++) {
            $domainNames[] = 'domain_' . $i;
        }
        yield 'first 100 domains' => ['domainNames' => $domainNames];

        #2: from 100 to 150 domains
        $domainNames = [];
        for ($i = 100; $i < 150; $i++) {
            $domainNames[] = 'domain_' . $i;
        }
        yield 'from 100 to 150 domains' => ['domainNames' => $domainNames];

        #3: from 50 to 150 domains
        $domainNames = [];
        for ($i = 50; $i < 150; $i++) {
            $domainNames[] = 'domain_' . $i;
        }
        yield 'from 50 to 150 domains' => ['domainNames' => $domainNames];
    }

    public function provideSettingNames()
    {
        #0: first 50 settings
        $settingNames = [];
        for ($i = 0; $i < 50; $i++) {
            $settingNames[] = 'setting_' . $i;
        }
        yield 'first 50 settings' => ['settingNames' => $settingNames];
    }

    /**
     * @ParamProviders({"provideDomainNames"})
     * @Revs(1000)
     * @Iterations(5)
     * @Assert(stat="mean", value="30")
     */
    public function benchGetSettings(array $params): void
    {
        $this->provider->getSettings($params['domainNames']);
    }

    /**
     * @ParamProviders({"provideDomainNames", "provideSettingNames"})
     * @Revs(500)
     * @Iterations(5)
     * @Assert(stat="mean", value="30")
     */
    public function benchGetSettingsByName(array $params): void
    {
        $this->provider->getSettingsByName($params['domainNames'], $params['settingNames']);
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     * @Assert(stat="mean", value="10")
     */
    public function benchGetDomains(): void
    {
        $this->provider->getDomains();
    }

    /**
     * @Revs(1000)
     * @Iterations(5)
     * @Assert(stat="mean", value="10")
     */
    public function benchGetEnabedDomains(): void
    {
        $this->provider->getDomains(true);
    }
}
