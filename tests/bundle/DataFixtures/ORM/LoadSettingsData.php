<?php
declare(strict_types=1);

namespace App\DataFixtures\ORM;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\Type;
use App\Entity\Setting;
use App\Entity\Tag;

class LoadSettingsData extends Fixture
{
    public const SETTING_NAME_0 = 'bazinga';
    public const SETTING_NAME_1 = 'foo';
    public const SETTING_NAME_2 = 'tuna';

    public const DOMAIN_NAME_1 = DomainModel::DEFAULT_NAME;
    public const DOMAIN_NAME_2 = 'sea';

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        $tag1 = new Tag();
        $tag1->setName('fixture');

        $domain1 = new DomainModel();
        $domain1->setName(self::DOMAIN_NAME_1);

        $domain2 = new DomainModel();
        $domain2->setName(self::DOMAIN_NAME_2);

        $setting0 = new Setting();
        $setting0
            ->setName(self::SETTING_NAME_0)
            ->setDescription('fixture bool baz setting')
            ->setType(Type::BOOL())
            ->setDomain($domain1)
            ->setData(false)
            ->addTag($tag1);

        $manager->persist($tag1);
        $manager->persist($setting0);

        $setting1 = new Setting();
        $setting1
            ->setName(self::SETTING_NAME_1)
            ->setDescription('fixture bool foo setting')
            ->setType(Type::BOOL())
            ->setDomain($domain1)
            ->setData(true)
            ->addTag($tag1);

        $manager->persist($tag1);
        $manager->persist($setting1);

        $setting2 = new Setting();
        $setting2
            ->setName(self::SETTING_NAME_2)
            ->setDescription('fixture bool tuna setting')
            ->setType(Type::STRING())
            ->setDomain($domain2)
            ->setData('fishing')
            ->addTag($tag1);

        $manager->persist($setting2);

        $manager->flush();
    }
}
