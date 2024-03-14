<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Controller;

use Helis\SettingsManagerBundle\Form\DomainFormType;
use Helis\SettingsManagerBundle\Settings\SettingsManager;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class DomainController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SettingsManager $settingsManager
    ) {
    }

    public function indexAction(): Response
    {
        return new Response($this->twig->render('@HelisSettingsManager/Domain/index.html.twig', [
            'domains' => $this->settingsManager->getDomains(),
            'providers' => $this->settingsManager->getProviders(),
        ]));
    }

    public function quickEditAction(Request $request, string $domainName, string $providerName): Response
    {
        $value = $request->request->get('value');
        if ($value === null) {
            throw new BadRequestHttpException('Missing value field');
        }

        $domains = $this->settingsManager->getDomains($providerName);
        if (!isset($domains[$domainName])) {
            throw new NotFoundHttpException('Domain named '.$domainName.' not found');
        }

        $domain = $domains[$domainName];

        $domain->setEnabled(filter_var($value, FILTER_VALIDATE_BOOLEAN));
        $this->settingsManager->updateDomain($domain, $providerName);

        return new JsonResponse();
    }

    public function editAction(Request $request, string $domainName, string $providerName): Response
    {
        $domains = $this->settingsManager->getDomains($providerName);

        if (!isset($domains[$domainName])) {
            throw new NotFoundHttpException('Domain '.$domainName.' not found');
        }

        $form = $this->formFactory->create(DomainFormType::class, $domains[$domainName]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->settingsManager->updateDomain($form->getData(), $providerName);

            return new RedirectResponse($this->urlGenerator->generate('settings_domain_index'));
        }

        return new Response($this->twig->render('@HelisSettingsManager/Domain/edit.html.twig', [
            'form' => $form->createView(),
            'domainName' => $domainName,
        ]));
    }

    public function copyAction(string $domainName, string $providerName): Response
    {
        $this->settingsManager->copyDomainToProvider($domainName, $providerName);

        return new JsonResponse();
    }

    public function deleteAction(string $domainName, ?string $providerName = null): Response
    {
        $this->settingsManager->deleteDomain($domainName, $providerName);

        return new JsonResponse();
    }
}
