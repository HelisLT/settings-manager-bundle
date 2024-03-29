<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Command;

use Helis\SettingsManagerBundle\Provider\SettingsProviderInterface;
use Helis\SettingsManagerBundle\Settings\ProvidersManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'helis:settings:warm-up-providers',
    description: 'Warm ups settings providers'
)]
class WarmUpProvidersCommand extends Command
{
    public function __construct(private readonly ProvidersManager $providersManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'domains',
                'd',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Domains to copy',
                []
            )
            ->addOption(
                'source-provider',
                's',
                InputOption::VALUE_OPTIONAL,
                'Provider to copy from',
                SettingsProviderInterface::DEFAULT_PROVIDER
            )
            ->addOption(
                'target-providers',
                't',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Providers to copy to',
                []
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Settings providers warm up');

        $this->providersManager->warmUpProviders(
            $input->getOption('source-provider'),
            $input->getOption('target-providers'),
            $input->getOption('domains')
        );

        $io->success('Providers warmed up');

        return 0;
    }
}
