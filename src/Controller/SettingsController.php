<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Controller;

use Helis\SettingsManagerBundle\Event\SettingChangeEvent;
use Helis\SettingsManagerBundle\Form\SettingFormType;
use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\Type;
use Helis\SettingsManagerBundle\Settings\SettingsManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SettingsController extends AbstractController
{
    private $settingsManager;
    private $eventDispatcher;
    private $validator;

    public function __construct(
        SettingsManager $settingsManager,
        EventDispatcherInterface $eventDispatcher,
        ValidatorInterface $validator
    ) {
        $this->settingsManager = $settingsManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->validator = $validator;
    }

    public function indexAction(string $domainName): Response
    {
        $settings = $this->settingsManager->getSettingsByDomain([$domainName]);

        if ($domainName !== DomainModel::DEFAULT_NAME && count($settings) === 0) {
            return $this->redirectToRoute('settings_index');
        }

        return $this->render('@HelisSettingsManager/Settings/index.html.twig', [
            'settings' => $settings,
            'domains' => $this->settingsManager->getDomains(),
            'providers' => $this->settingsManager->getProviders(),
            'activeDomain' => $domainName,
        ]);
    }

    public function quickEditAction(Request $request, string $domainName, string $settingName): Response
    {
        $value = $request->request->get('value');
        if ($value === null) {
            throw new BadRequestHttpException('Missing value field');
        }

        $setting = $this->settingsManager->getSettingsByName([$domainName], [$settingName]);
        $setting = array_shift($setting);
        if ($setting === null) {
            throw $this->createNotFoundException('Setting not found in ' . $domainName . ' domain');
        }

        if ($setting->getType()->equals(Type::BOOL())) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        } else {
            throw new BadRequestHttpException('Quick edit is only allowed for setttings with type bool');
        }

        $setting->setData($value);
        $this->settingsManager->save($setting);

        return new JsonResponse();
    }

    public function editAction(Request $request, string $domainName, string $settingName): Response
    {
        $setting = $this->settingsManager->getSettingsByName([$domainName], [$settingName]);
        $setting = array_shift($setting);

        if ($setting === null) {
            throw $this->createNotFoundException('Setting not found in ' . $domainName . ' domain');
        }

        $this->eventDispatcher->dispatch(new SettingChangeEvent($setting));
        $form = $this->createForm(SettingFormType::class, $setting);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->settingsManager->save($setting);

            return $this->redirectToRoute('settings_index', ['domainName' => $domainName]);
        }

        return $this->render('@HelisSettingsManager/Settings/edit.html.twig', [
            'form' => $form->createView(),
            'settingType' => $setting->getType()->getValue(),
            'domainName' => $domainName,
        ]);
    }

    public function deleteAction(string $domainName, string $settingName): Response
    {
        $setting = $this->settingsManager->getSettingsByName([$domainName], [$settingName]);
        $setting = array_shift($setting);

        if ($setting === null) {
            throw $this->createNotFoundException('Setting not found in ' . $domainName . ' domain');
        }

        $this->settingsManager->delete($setting);

        return new JsonResponse();
    }

    public function duplicateAction(string $domainName, string $settingName, string $toDomainName): Response
    {
        $setting = $this->settingsManager->getSettingsByName([$domainName], [$settingName]);
        $setting = array_shift($setting);

        if ($setting === null) {
            throw $this->createNotFoundException('Setting not found in ' . $domainName . ' domain');
        }

        $setting = clone $setting;
        $setting->setDomain((new DomainModel())->setName($toDomainName));

        $violations = $this->validator->validate($setting, null, ['Default', 'Duplication']);
        if ($violations->count() !== 0) {
            throw new BadRequestHttpException($violations->get(0)->getMessage());
        }

        $this->settingsManager->save($setting);

        return new JsonResponse();
    }
}
