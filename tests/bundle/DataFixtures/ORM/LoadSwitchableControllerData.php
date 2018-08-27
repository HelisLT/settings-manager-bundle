<?php

declare(strict_types=1);

namespace App\DataFixtures\ORM;

use App\Entity\Setting;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\Type;

class LoadSwitchableControllerData extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $domain = new DomainModel();
        $domain->setName('default');

        $setting = new Setting();
        $setting
            ->setName('switchable_controller_enabled')
            ->setDescription('Enables switchable controller')
            ->setType(Type::BOOL())
            ->setDomain($domain)
            ->setData(true);

        $manager->persist($setting);
        $manager->flush();
    }
}
