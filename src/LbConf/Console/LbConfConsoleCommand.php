<?php
namespace LbConf\Console;

use LbConf\Config\ConfigManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LbConfConsoleCommand extends Command
{
    const ACTION_GET    = 'get';
    const ACTION_SET    = 'set';
    const ACTION_DELETE = 'del';
    const ACTION_KEYS   = 'keys';

    protected function configure()
    {
        $this->setName('config')
            ->addArgument('action', InputArgument::REQUIRED, 'The action to perform. One of: get, set, del, keys.')
            ->addArgument('key', InputArgument::OPTIONAL, 'The config key. Sub-keys are separated by dots: e.g. database.connection.port.')
            ->addArgument('value', InputArgument::OPTIONAL, '(Only used with "set" action.) The config value to set.')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Force the type of the value. One of: string, number, boolean.')
            ->addOption('config-file', 'c', InputOption::VALUE_REQUIRED, 'Path to the meta-config file. Defaults to the first of: ./.lbconf, ./.lbconf.php');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $metaConfigFile = $input->getOption('config-file');

        if ($metaConfigFile === null) {
            foreach (['./.lbconf', './.lbconf.php'] as $possibleFile) {
                if (file_exists($possibleFile)) {
                    $metaConfigFile = $possibleFile;
                    break;
                }
            }
            if ($metaConfigFile === null) {
                throw new \InvalidArgumentException("Cannot file meta-config file. Looked for './.lbconf', './.lbconf.php'.");
            }
        }

        $configManager = new ConfigManager();
        $configManager->loadConfig($metaConfigFile);

        $action = $input->getArgument('action');
        $key    = $input->getArgument('key');

        switch ($action) {
            case self::ACTION_GET:
                $value = $configManager->get($key);
                $output->writeln(json_encode($value, JSON_PRETTY_PRINT));
                break;

            case self::ACTION_SET:
                $value = $input->getArgument('value');
                $type  = $input->getOption('type');

                $configManager->set($key, $value, $type);
                $configManager->storeConfig();
                break;

            case self::ACTION_DELETE:
                $configManager->delete($key);
                $configManager->storeConfig();
                break;

            case self::ACTION_KEYS:
                $keys = $configManager->keys($key);
                sort($keys); // For keys, it's probably nicer to sort them
                $output->writeln(json_encode($keys, JSON_PRETTY_PRINT));
                break;

            default:
                throw new \InvalidArgumentException("Invalid action: $action");
        }
    }
}
