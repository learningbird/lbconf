<?php
namespace LbConf\Config;

use Exceptions\Collection\KeyNotFoundException;
use Exceptions\Data\FormatException;
use Exceptions\IO\Filesystem\FileNotFoundException;

/**
 * Class ConfigManager
 *
 * Manages hierarchical JSON configuration files.
 *
 * Files to read/write are specified in a meta-configuration file that must be passed to ConfigManager::loadConfig().
 * @see loadConfig() for the meta-configuration file structure.
 *
 * Example usage:
 *     $configManager = new ConfigManager();
 *     $configManager->loadConfig('/var/www/.lbconf');
 *
 *     $configManager->keys(); // [ 'database', 'services', 'debug', ... ]
 *
 *     $configManager->get('database.username'); // 'prod-user'
 *
 *     $configManager->set('database.username', 'dev-user')
 *     $configManager->storeConfig();
 *
 * @package LbConf\Config
 */
class ConfigManager
{
    const TYPE_STRING  = 'string';
    const TYPE_NUMBER  = 'number';
    const TYPE_BOOLEAN = 'boolean';

    /**
     * @var ConfigMerger
     */
    protected $configMerger;

    /**
     * @var array
     */
    protected $readData;

    /**
     * @var array
     */
    protected $writeData;

    /**
     * @var string
     */
    protected $writeFile;

    public function __construct()
    {
        $this->configMerger = new ConfigMerger();
    }

    /**
     * Load configuration from a meta-config file.
     * The meta-config file must be in JSON format with the following structure:
     * {
     *     "read": [
     *         "configurations/default.json",
     *         "configurations/env-development.json"
     *     ],
     *     "write": "configurations/override.json"
     * }
     *
     * File paths are relative to the location of the meta-config file.
     *
     * The files in BOTH the "read" and "write" sections are read and merged, and the file in the write section is
     * written to when storeConfig is called.
     *
     * @param string $metaConfigFile
     */
    public function loadConfig(string $metaConfigFile)
    {
        $metaConfig = $this->readConfigFile($metaConfigFile);

        $metaConfig = $this->interpolateEnv($metaConfig);

        if (!isset($metaConfig['write'])) {
            throw new \DomainException('Meta-config must contain "write" key indicating file to write overrides to.');
        }

        if (!is_string($metaConfig['write'])) {
            throw new \DomainException('Meta-config "write" key must be a string.');
        }

        // Files are specified relative to the meta-config directory, so switch there to record the file names
        $cwd = getcwd();
        chdir(dirname($metaConfigFile));

        $writeFile   = realpath($metaConfig['write']);
        $readFiles   = [];

        if (isset($metaConfig['read'])) {
            foreach ($metaConfig['read'] as $readFile) {
                if (!is_file($readFile) || !is_readable($readFile)) {
                    throw new FileNotFoundException("Cannot read JSON file: $readFile");
                }
                $readFiles[] = realpath($readFile);
            }
        }

        $readFiles[] = $writeFile;

        // Switch back to the original working directory
        chdir($cwd);

        $this->readData = [];

        foreach ($readFiles as $file) {
            $data           = $this->readConfigFile($file);
            $this->readData = $this->configMerger->merge($this->readData, $data);
        }

        $this->writeFile = $writeFile;
        $this->writeData = $this->readConfigFile($writeFile);
    }

    /**
     * Write any changes made by set() or delete() to the write file.
     */
    public function storeConfig()
    {
        $this->writeConfigFile($this->writeFile, $this->writeData);
    }

    /**
     * Get data that will be written to the write file.
     * This includes the original contents of the write file, plus any changes made by calls to set() and delete().
     *
     * @return array
     */
    public function getWriteData(): array
    {
        return $this->writeData;
    }

    /**
     * Retrieve the configuration value for the specified key. If $key is not provided, all configuration data will be
     * returned.
     *
     * Example:
     *     $configManager->get('database'); // [ 'host' => 'localhost', 'port' => 3306, 'username' => 'prod-user', ... ]
     *     $configManager->get('database.hostname'); // 'localhost'
     *
     * @param string|null $key
     *
     * @return mixed
     */
    public function get(string $key = null)
    {
        if ($key === null) {
            return $this->readData;
        }

        $pieces  = explode('.', $key);
        $element = $this->readData;

        foreach ($pieces as $piece) {
            if (!array_key_exists($piece, $element)) {
                throw new KeyNotFoundException("Could not find property '$piece'");
            }

            $element =& $element[$piece];
        }

        return $element;
    }

    /**
     * Sets the configuration value for the specified key.
     * The type of $value will be inferred, unless $type is explicitly set.
     *
     * Example:
     *     $configManager->set('database.hostname', 'localhost');
     *     $configManager->set('database.port', '3306'); // Will be cast to int
     *     $configManager->set('database.port', '3306', 'string'); // Will be set as string
     *
     * @param string      $key
     * @param mixed       $value
     * @param string|null $type  If supplied, on of: string, number, boolean.
     */
    public function set(string $key, $value, string $type = null)
    {
        $castValue = $this->cast($value, $type);

        $pieces    = explode('.', $key);
        $lastPiece = array_pop($pieces);

        $element =& $this->writeData;

        foreach ($pieces as $piece) {
            if (!array_key_exists($piece, $element)) {
                $element[$piece] = [];
            }

            $element =& $element[$piece];
        }

        $element[$lastPiece] = $castValue;

        $this->readData = $this->configMerger->merge($this->readData, $this->writeData);
    }

    /**
     * Deletes the configuration value for the specified key.
     * Note that the key must exist in the write data for the deletion to be permitted. There is no way to delete a key
     * that only exists in the read data, the only alternative is to set it to null, or some such value.
     *
     * @param string $key
     */
    public function delete(string $key)
    {
        $pieces    = explode('.', $key);
        $lastPiece = array_pop($pieces);

        $element =& $this->writeData;

        foreach ($pieces as $piece) {
            $element =& $element[$piece];
        }

        if (!array_key_exists($lastPiece, $element)) {
            throw new KeyNotFoundException("Could not find property '$lastPiece'");
        }
        unset($element[$lastPiece]);
    }

    /**
     * Retrieves the keys for the configuration value under the specified key. If $key is not provided, all top-level
     * keys will be returned.
     *
     * @param string|null $key
     *
     * @return array
     */
    public function keys(string $key = null): array
    {
        $value = $this->get($key);

        if (!is_array($value)) {
            return [];
        }

        return array_keys($value);
    }

    /**
     * Cast the given value to the specified type. If type is omitted, it will be inferred based on the value.
     *
     * @param             $value
     * @param string|null $type
     *
     * @return bool|float|int|null|string
     */
    protected function cast($value, string $type = null)
    {
        if ($type === null) {
            return $this->inferType($value);
        }

        switch ($type) {
            case self::TYPE_STRING:
                return (string)$value;
                break;

            case self::TYPE_NUMBER:
                return strpos($value, '.') !== false ? (float)$value : (int)$value;
                break;

            case self::TYPE_BOOLEAN:
                return (bool)$value;
                break;

            default:
                throw new \InvalidArgumentException("Invalid cast type: $type");
        }
    }

    /**
     * Cast the given value to a type inferred based on the value.
     *
     * The strings 'true', 'false' and 'null' will be cast to true, false and null, respectively.
     * Numeric values will be cast to integers or floats, depending on the presence of a decimal.
     * Everything else will be returned as-is.
     *
     * @param $value
     *
     * @return bool|float|int|null|string
     */
    protected function inferType($value)
    {
        if (strcasecmp($value, 'true') === 0) {
            return true;
        }

        if (strcasecmp($value, 'false') === 0) {
            return false;
        }

        if (strcasecmp($value, 'null') === 0) {
            return null;
        }

        if (is_numeric($value)) {
            return $this->cast($value, self::TYPE_NUMBER);
        }

        return $value;
    }

    /**
     * @param string $file
     *
     * @return array
     *
     * @codeCoverageIgnore
     */
    protected function readConfigFile(string $file): array
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new FileNotFoundException("Cannot read JSON file: $file");
        }

        $contents = file_get_contents($file);
        $data     = json_decode($contents, true);

        if ($data === null) {
            switch (json_last_error()) {
                case JSON_ERROR_NONE:
                    $msg = 'No error';
                    break;
                case JSON_ERROR_DEPTH:
                    $msg = 'Maximum stack depth exceeded';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $msg = 'Underflow or the modes mismatch';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $msg = 'Unexpected control character found';
                    break;
                case JSON_ERROR_SYNTAX:
                    $msg = 'Syntax error, malformed JSON';
                    break;
                case JSON_ERROR_UTF8:
                    $msg = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                    break;
                default:
                    $msg = 'Unknown error';
                    break;
            }

            throw new FormatException("Invalid JSON in $file: $msg");
        }

        return $data;
    }

    /**
     * @param string $file
     * @param array  $data
     *
     * @codeCoverageIgnore
     */
    protected function writeConfigFile(string $file, array $data)
    {
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * @param array $data The config data to interpolate
     *
     * @return array The data with environment variables replaced
     */
    protected function interpolateEnv(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->interpolateEnv($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->interpolateEnvString($value);
            }
        }

        return $data;
    }

    /**
     * @param string $value The string to interpolate
     *
     * @return string The interpolated string
     */
    protected function interpolateEnvString(string $value): string
    {
        return preg_replace_callback('/\$(\{[A-Z0-9_]+\}|[A-Z0-9_]+)/i', function (array $matches) {
            $var = $matches[1];
            if ($var[0] === '{') {
                $var = substr($var, 1, -1);
            }

            return (string)getenv($var);
        }, $value);
    }
}