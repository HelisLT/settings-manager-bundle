<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Model\TagModel;
use Helis\SettingsManagerBundle\Provider\Traits\WritableProviderTrait;

class DoctrineOrmSettingsProvider implements SettingsProviderInterface
{
    use WritableProviderTrait;

    protected $entityManager;
    protected $settingsEntityClass;
    protected $tagEntityClass;

    public function __construct(
        EntityManagerInterface $entityManager,
        string $settingsEntityClass,
        string $tagEntityClass = null
    ) {
        $this->entityManager = $entityManager;

        if (!is_subclass_of($settingsEntityClass, SettingModel::class)) {
            throw new \UnexpectedValueException(
                $settingsEntityClass . ' is not part of the model ' . SettingModel::class
            );
        }

        $this->settingsEntityClass = $settingsEntityClass;

        if ($tagEntityClass !== null) {
            if (!is_subclass_of($tagEntityClass, TagModel::class)) {
                throw new \UnexpectedValueException(
                    $tagEntityClass . ' is not part of the model ' . TagModel::class
                );
            }

            $this->tagEntityClass = $tagEntityClass;
        }
    }

    public function getSettings(array $domainNames): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('s')
            ->from($this->settingsEntityClass, 's')
            ->where($qb->expr()->in('s.domain.name', ':domainNames'))
            ->setParameter('domainNames', $domainNames)
            ->setMaxResults(300);

        return $qb->getQuery()->getResult();
    }

    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('s')
            ->from($this->settingsEntityClass, 's')
            ->where($qb->expr()->andX(
                $qb->expr()->in('s.name', ':settingNames'),
                $qb->expr()->in('s.domain.name', ':domainNames')
            ))
            ->setParameter('domainNames', $domainNames)
            ->setParameter('settingNames', $settingNames)
            ->setMaxResults(300);

        return $qb->getQuery()->getResult();
    }

    public function getDomains(bool $onlyEnabled = false): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('DISTINCT s.domain.name AS name')
            ->addSelect('s.domain.priority AS priority')
            ->addSelect('s.domain.enabled AS enabled')
            ->addSelect('s.domain.readOnly AS readOnly')
            ->from($this->settingsEntityClass, 's')
            ->setMaxResults(100);

        if ($onlyEnabled) {
            $qb
                ->andWhere($qb->expr()->eq('s.domain.enabled', ':enabled'))
                ->setParameter('enabled', true);
        }

        return array_map(
            function ($row) {
                $model = new DomainModel();
                $model->setName($row['name']);
                $model->setPriority($row['priority']);
                $model->setEnabled($row['enabled']);
                $model->setReadOnly($row['readOnly']);

                return $model;
            },
            $qb->getQuery()->getArrayResult()
        );
    }

    public function save(SettingModel $settingModel): bool
    {
        if ($this->entityManager->contains($settingModel)) {
            $this->entityManager->persist($settingModel);
            $this->entityManager->flush();

            return true;
        }

        $entity = $this
            ->entityManager
            ->getRepository($this->settingsEntityClass)
            ->findOneBy([
                'name' => $settingModel->getName(),
                'domain.name' => $settingModel->getDomain()->getName(),
            ]);

        if ($entity !== null) {
            $entity->setData($settingModel->getData());
        } else {
            $entity = $settingModel instanceof $this->settingsEntityClass
                ? $settingModel : $this->transformModelToEntity($settingModel);
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return true;
    }

    public function delete(SettingModel $settingModel): bool
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->delete($this->settingsEntityClass, 's')
            ->where($qb->expr()->andX(
                $qb->expr()->eq('s.name', ':sname'),
                $qb->expr()->eq('s.domain.name', ':dname')
            ))->setParameters([
                'sname' => $settingModel->getName(),
                'dname' => $settingModel->getDomain()->getName(),
            ]);

        $success = ((int) $qb->getQuery()->getSingleScalarResult()) > 0;

        if ($success) {
            $this->entityManager->clear($this->settingsEntityClass);
        }

        return $success;
    }

    public function updateDomain(DomainModel $domainModel): bool
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->update($this->settingsEntityClass, 's')
            ->set('s.domain.enabled', ':enabled')
            ->set('s.domain.priority', ':priority')
            ->where($qb->expr()->eq('s.domain.name', ':dname'))
            ->setParameter('enabled', $domainModel->isEnabled())
            ->setParameter('priority', $domainModel->getPriority())
            ->setParameter('dname', $domainModel->getName());

        $success = ((int) $qb->getQuery()->getSingleScalarResult()) > 0;

        if ($success) {
            $this->entityManager->clear($this->settingsEntityClass);
        }

        return $success;
    }

    public function deleteDomain(string $domainName): bool
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->delete($this->settingsEntityClass, 's')
            ->where($qb->expr()->eq('s.domain.name', ':dname'))
            ->setParameter('dname', $domainName);

        $success = ((int) $qb->getQuery()->getSingleScalarResult()) > 0;

        if ($success) {
            $this->entityManager->clear($this->settingsEntityClass);
        }

        return $success;
    }

    protected function transformModelToEntity(SettingModel $model): SettingModel
    {
        // transform setting

        /** @var SettingModel $entity */
        $entity = new $this->settingsEntityClass();
        $entity
            ->setName($model->getName())
            ->setType($model->getType())
            ->setDescription($model->getDescription())
            ->setDataValue($model->getDataValue())
            ->setDomain($model->getDomain());

        // transform tags

        if ($this->tagEntityClass && $model->getTags()->count() > 0) {
            $tagNamesToFetch = $model->getTags()->map(function (TagModel $model) {
                return $model->getName();
            })->toArray();

            /** @var TagModel[] $fetchedTags */
            $fetchedTags = $this
                ->entityManager
                ->getRepository($this->tagEntityClass)
                ->findBy(['name' => $tagNamesToFetch]);
            if (count($fetchedTags) < count($tagNamesToFetch)) {
                $fetchedTagNames = [];
                foreach ($fetchedTags as $fetchedTag) {
                    $fetchedTagNames[] = $fetchedTag->getName();
                }

                foreach (array_diff($tagNamesToFetch, $fetchedTagNames) as $newTagName) {
                    /** @var TagModel $newTag */
                    $newTag = new $this->tagEntityClass();
                    $newTag->setName($newTagName);
                    $fetchedTags[] = $newTag;
                }
            }

            foreach ($fetchedTags as $fetchedTag) {
                $this->entityManager->persist($fetchedTag);
            }
            $entity->setTags(new ArrayCollection($fetchedTags));
        }

        return $entity;
    }
}
