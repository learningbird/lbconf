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
    const ACTION_GET      = 'get';
    const ACTION_SET      = 'set';
    const ACTION_DELETE   = 'del';
    const ACTION_KEYS     = 'keys';
    const ACTION_COMPLETE = 'complete';

    protected function configure()
    {
        $this->setName('config')
            ->addArgument('key', InputArgument::OPTIONAL, 'The config key. Sub-keys are separated by dots: e.g. database.connection.port.')
            ->addArgument('value', InputArgument::OPTIONAL, '(Only used with "set" action.) The config value to set.')
            ->addOption('get', 'g', InputOption::VALUE_NONE, 'Retrieve the configuration for the given key. This is the default when one argument is given.')
            ->addOption('set', 's', InputOption::VALUE_NONE, 'Set the configuration value for the given key. This is the default when two arguments are given.')
            ->addOption('del', 'd', InputOption::VALUE_NONE, 'Remove the configuration value for the given key.')
            ->addOption('keys', 'k', InputOption::VALUE_NONE, 'Retrieve the configuration keys for the given key.')
            ->addOption('complete', null, InputOption::VALUE_NONE, 'Output key completion for the given key. Used for bash completion.')
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

        $key   = $input->getArgument('key');
        $value = $input->getArgument('value');

        $actions = [];
        if ($input->getOption('get')) {
            $actions[] = self::ACTION_GET;
        }
        if ($input->getOption('set')) {
            $actions[] = self::ACTION_SET;
        }
        if ($input->getOption('del')) {
            $actions[] = self::ACTION_DELETE;
        }
        if ($input->getOption('keys')) {
            $actions[] = self::ACTION_KEYS;
        }
        if ($input->getOption('complete')) {
            $actions[] = self::ACTION_COMPLETE;
        }
        if (count($actions) > 1) {
            throw new \InvalidArgumentException('Cannot specify more than one action at a time.');
        }

        if (count($actions) === 0) {
            if ($value !== null) {
                $action = self::ACTION_SET;
            } else {
                $action = self::ACTION_GET;
            }
        } else {
            $action = $actions[0];
        }

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

            case self::ACTION_COMPLETE:
                // Find the keys for everything up to the last period
                $pieces = explode('.', $key);
                $suffix = array_pop($pieces);
                $prefix = implode('.', $pieces);

                $keys = $configManager->keys($prefix ?: null);
                sort($keys); // For keys, it's probably nicer to sort them

                $keys = array_map(function ($key) use ($prefix) {
                    return $prefix ? "$prefix.$key" : $key;
                }, $keys);

                echo implode(" ", $keys);
                die;

            default:
                throw new \InvalidArgumentException("Invalid action: $action");
        }
    }
}
