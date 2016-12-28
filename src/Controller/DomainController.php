<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Controller;

use Helis\SettingsManagerBundle\Settings\SettingsAccessControl;
use Helis\SettingsManagerBundle\Settings\SettingsManager;
use Helis\SettingsManagerBundle\SettingsManagerActions;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class DomainController extends AbstractController
{
    private $settingsManager;
    private $settingsAccessControl;

    public function __construct(SettingsManager $settingsManager, SettingsAccessControl $settingsAccessControl)
    {
        $this->settingsManager = $settingsManager;
        $this->settingsAccessControl = $settingsAccessControl;
    }

    public function indexAction(): Response
    {
        return $this->render('@HelisSettingsManager/Domain/index.html.twig', [
            'domains' => $this->settingsManager->getDomains(),
            'providers' => $this->settingsManager->getProviders(),
        ]);
    }

    public function quickEditAction(Request $request, string $domainName, string $providerName): Response
    {
        $value = $request->request->get('value');
        if ($value === null) {
            throw new BadRequestHttpException('Missing value field');
        }

        $domains = $this->settingsManager->getDomains($providerName);
        if (!isset($domains[$domainName])) {
            throw $this->createNotFoundException("Domain named {$domainName} not found");
        }

        $domain = $domains[$domainName];

        if (!$this->settingsAccessControl->isGranted(SettingsManagerActions::DOMAIN_QUICK_EDIT, $domain)) {
            throw $this->createAccessDeniedException();
        }

        $domain->setEnabled(filter_var($value, FILTER_VALIDATE_BOOLEAN));
        $this->settingsManager->updateDomain($domain, $providerName);

        return new JsonResponse();
    }

    public function copyAction(string $domainName, string $providerName): Response
    {
        if (!$this->settingsAccessControl->isGranted(
            SettingsManagerActions::DOMAIN_COPY,
            [$domainName, $providerName]
        )) {
            throw $this->createAccessDeniedException();
        }

        $this->settingsManager->copyDomainToProvider($domainName, $providerName);

        return new JsonResponse();
    }

    public function deleteAction(string $domainName, string $providerName = null): Response
    {
        if (!$this->settingsAccessControl->isGranted(
            SettingsManagerActions::DOMAIN_DELETE,
            [$domainName, $providerName]
        )) {
            throw $this->createAccessDeniedException();
        }

        $this->settingsManager->deleteDomain($domainName, $providerName);

        return new JsonResponse();
    }
}
