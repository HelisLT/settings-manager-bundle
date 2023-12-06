<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Command;

use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Model\TagModel;
use Helis\SettingsManagerBundle\Settings\SettingsManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'debug:settings',
    description: 'Displays current settings for an application'
)]
class SettingsDebugCommand extends Command
{
    public function __construct(protected SettingsManager $settingsManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL, 'A setting name'),
                new InputOption('domain', null, InputOption::VALUE_REQUIRED, 'Displays settings for a specific domain'),
                new InputOption('domains', null, InputOption::VALUE_NONE, 'Displays all configured domains'),
                new InputOption('tag', null, InputOption::VALUE_REQUIRED, 'Shows all settings with a specific tag'),
            ])
            ->setHelp(
                <<<'EOF'
The <info>%command.name%</info> command displays all available settings:

  <info>php %command.full_name%</info>

Use the <info>--domains</info> option to display all configured domains:

  <info>php %command.full_name% --domains</info>

Display settings for a specific domain by specifying its name with the <info>--domain</info> option:

  <info>php %command.full_name% --domain=default</info>

Find all settings with a specific tag by specifying the tag name with the <info>--tag</info> option:

  <info>php %command.full_name% --tag=foo</info>

EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // // Displays all configured domains
        if ($input->getOption('domains')) {
            $tableHeaders = ['Name', 'Priority', 'Enabled', 'Read only'];
            $tableRows = [];
            foreach ($this->settingsManager->getDomains() as $domain) {
                $tableRows[] = [
                    $domain->getName(),
                    $domain->getPriority(),
                    $this->dataToScalar($domain->isEnabled()),
                    $this->dataToScalar($domain->isReadOnly()),
                ];
            }
            $io->table($tableHeaders, $tableRows);
        }

        // Filter by domain(s)
        $domains = array_keys($this->settingsManager->getDomains());
        if ($input->getOption('domain')) {
            $domains = [$input->getOption('domain')];
        }

        // Displays table of settings
        if (!$input->getOption('domains') && !$input->getArgument('name')) {
            $tableHeaders = ['Name', 'Description', 'Domain', 'Type', 'Data', 'Provider name', 'Tags'];
            $tableRows = [];

            if ($tag = $input->getOption('tag')) {
                // Display settings filtered by tags
                foreach (array_reverse($this->settingsManager->getSettingsByTag(
                    $domains,
                    $tag
                )) as $settingModel) {
                    $tableRows[] = $this->renderSettingsRow($settingModel);
                }
            } else {
                // Display all settings
                foreach ($this->settingsManager->getSettingsByDomain($domains) as $settingModel) {
                    $tableRows[] = $this->renderSettingsRow($settingModel);
                }
            }

            $io->table($tableHeaders, $tableRows);
        }

        // Display details for one setting with argument `name`
        if ($name = $input->getArgument('name')) {
            $io->title(sprintf('Information for Setting "<info>%s</info>"', $name));

            $setting = $this->settingsManager->getSettingsByName($domains, [$name]);
            $setting = array_shift($setting);

            $tableHeaders = ['Option', 'Value'];
            $tableRows = [];

            if ($setting !== null) {
                /* @var SettingModel $setting */
                $tableRows[] = ['Name', $setting->getName()];
                $tableRows[] = ['Description', $setting->getDescription() ?? '-'];
                $tableRows[] = ['Domain', $setting->getDomain()->getName()];
                $tableRows[] = ['Provider Name', $setting->getProviderName() ?? 'config'];
                $tableRows[] = ['Type', $setting->getType()->value];
                $tableRows[] = ['Tags', $this->implodeTags($setting)];
                $tableRows[] = ['Choices', $this->dataToScalar($setting->getChoices())];
                $tableRows[] = ['Data', $this->dataToScalar($setting->getData())];
            }

            $io->table($tableHeaders, $tableRows);
        }

        return 0;
    }

    private function renderSettingsRow(SettingModel $settingModel): array
    {
        return [
            $settingModel->getName(),
            $settingModel->getDescription(),
            $settingModel->getDomain()->getName(),
            $settingModel->getType()->value,
            $this->dataToScalar($settingModel->getData()),
            $settingModel->getProviderName() ?? 'config',
            $this->implodeTags($settingModel),
        ];
    }

    private function dataToScalar($data): float|bool|int|string
    {
        if (is_array($data)) {
            $data = $data === [] ? '-' : var_export($data, true);
        }

        if (is_bool($data)) {
            $data = $data ? 'true' : 'false';
        }

        return is_scalar($data) ? $data : '-';
    }

    private function implodeTags(SettingModel $model): string
    {
        $tags = $model
            ->getTags()
            ->map(fn (TagModel $tagModel) => $tagModel->getName())
            ->toArray();

        return empty($tags) ? '-' : implode("\n", $tags);
    }
}
