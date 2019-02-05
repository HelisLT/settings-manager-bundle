<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Command;

use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Settings\SettingsManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class SettingsDebugCommand.
 */
class SettingsDebugCommand extends Command
{
    protected static $defaultName = 'debug:settings';

    /** @var SettingsManager $settingsManager */
    protected $settingsManager;

    /**
     * SettingsDebugCommand constructor.
     *
     * @param SettingsManager $settingsManager
     */
    public function __construct(SettingsManager $settingsManager)
    {
        parent::__construct();
        $this->settingsManager = $settingsManager;
    }

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this->setDefinition([
            new InputArgument('name', InputArgument::OPTIONAL, 'A setting name'),
            new InputOption('domain', null, InputOption::VALUE_REQUIRED, 'Displays settings for a specific domain'),
            new InputOption('domains', null, InputOption::VALUE_NONE, 'Displays all configured domains'),
            new InputOption('tag', null, InputOption::VALUE_REQUIRED, 'Shows all settings with a specific tag'),
        ])->setDescription('Displays current settings for an application')->setHelp(
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

    /**
     * Executes the current command.
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @see setCode()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        // // Displays all configured domains
        if ($input->getOption('domains')) {
            $tableHeaders = ['Name', 'Priority', 'Enabled', 'Read only'];
            $tableRows = [];
            foreach ($this->settingsManager->getDomains() as $key => $domain) {
                $tableRows[] = [
                    $domain->getName(),
                    $domain->getPriority(),
                    $domain->isEnabled(),
                    $domain->isReadOnly(),
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

            // Display settings filtered by tags
            if ($tag = $input->getOption('tag')) {
                foreach (array_reverse($this->settingsManager->getEnabledSettingsByTag(
                    $domains,
                    $tag
                )) as $settingModel) {
                    $tableRows[] = $this->_renderSettingsRow($settingModel);
                }
            } // Display all settings
            else {
                foreach ($this->settingsManager->getSettingsByDomain($domains) as $settingModel) {
                    $tableRows[] = $this->_renderSettingsRow($settingModel);
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
            $tableRows[] = ['Name', $setting->getName()];
            $tableRows[] = ['Description', $setting->getDescription() ?? '-'];
            $tableRows[] = ['Domain', $setting->getDomain()->getName()];
            $tableRows[] = ['Provider Name', $setting->getProviderName() ?? 'config'];
            $tableRows[] = ['Type', $setting->getType()->getValue()];

            if ($tags = $setting->getTags()) {
                $tagInformation = [];
                foreach ($tags as $tag) {
                    $tagInformation[] = $tag->getName();
                }
                $tagInformation = implode("\n", $tagInformation);
            } else {
                $tagInformation = '-';
            }
            $tableRows[] = ['Tags', $tagInformation];
            $tableRows[] = ['Choices', ($choices = $setting->getChoices()) ? var_export($choices, true) : '-'];
            $tableRows[] = ['Data', $setting->getData()];

            $io->table($tableHeaders, $tableRows);
        }
    }

    /**
     * @param SettingModel $settingModel
     *
     * @return array
     */
    private function _renderSettingsRow(SettingModel $settingModel): array
    {
        if ($tags = $settingModel->getTags()) {
            $tagInformation = [];
            foreach ($tags as $tag) {
                $tagInformation[] = $tag->getName();
            }
            $tagInformation = implode("\n", $tagInformation);
        } else {
            $tagInformation = '-';
        }

        return [
            $settingModel->getName(),
            $settingModel->getDescription(),
            $settingModel->getDomain()->getName(),
            $settingModel->getType()->getValue(),
            $settingModel->getData(),
            $settingModel->getProviderName() ?? 'config',
            $tagInformation,
        ];
    }
}
