<?php
namespace LbConf\Config;

use Exceptions\Collection\KeyNotFoundException;
use Exceptions\Data\FormatException;
use Exceptions\IO\Filesystem\FileNotFoundException;

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

    public function loadConfig(string $metaConfigFile)
    {
        $metaConfig = $this->readConfigFile($metaConfigFile);

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
        $readFiles   = isset($metaConfig['read']) ? array_map('realpath', $metaConfig['read']) : [];
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

    public function storeConfig()
    {
        $this->writeConfigFile($this->writeFile, $this->writeData);
    }

    public function getWriteData(): array
    {
        return $this->writeData;
    }

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

    public function keys(string $key = null): array
    {
        $value = $this->get($key);

        if (!is_array($value)) {
            return [];
        }

        return array_keys($value);
    }

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
}