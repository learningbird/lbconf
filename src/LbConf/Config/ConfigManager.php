<?php
namespace LbConf\Config;

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
     * @var \stdClass
     */
    protected $readData;

    /**
     * @var \stdClass
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
        $metaConfig = $this->readConfigFile($metaConfigFile, true);

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
            $data = $this->readConfigFile($file);
            $this->readData = $this->configMerger->merge($this->readData, $data);
        }

        $this->writeFile = $writeFile;
        $this->writeData = $this->readConfigFile($writeFile);
    }

    public function storeConfig()
    {
        $this->writeConfigFile($this->writeFile, $this->writeData);
    }

    public function getWriteData()
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
            $element = Properties::get($element, $piece);
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
            if (!Properties::has($element, $piece)) {
                Properties::set($element, $piece, []);
            }

            $element =& Properties::get($element, $piece);
        }

        Properties::set($element, $lastPiece, $castValue);

        $this->readData = $this->configMerger->merge($this->readData, $this->writeData);
    }

    public function delete(string $key)
    {
        $pieces    = explode('.', $key);
        $lastPiece = array_pop($pieces);

        $element =& $this->writeData;

        foreach ($pieces as $piece) {
            $element =& Properties::get($element, $piece);
        }

        Properties::unset($element, $lastPiece);
    }

    public function keys(string $key = null)
    {
        $value = $this->get($key);
        return array_keys((array)$value);
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
     * @codeCoverageIgnore
     */
    protected function readConfigFile(string $file, bool $assoc = false)
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new FileNotFoundException("Cannot read JSON file: $file");
        }

        $contents = file_get_contents($file);
        $data     = json_decode($contents, $assoc);

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
     * @param string          $file
     * @param \stdClass|array $data
     *
     * @codeCoverageIgnore
     */
    protected function writeConfigFile(string $file, $data)
    {
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }
}