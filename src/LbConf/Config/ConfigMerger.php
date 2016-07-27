<?php
namespace LbConf\Config;

class ConfigMerger
{
    public function merge($target, $source)
    {
        foreach ($source as $key => $sourceValue) {
            if (Properties::has($target, $key)) {
                $targetValue = Properties::get($target, $key);

                if (
                    (is_object($targetValue) || is_array($targetValue)) &&
                    (is_object($sourceValue) || is_array($sourceValue)) &&
                    ($this->isAssoc($targetValue) || $this->isAssoc($sourceValue))
                ) {
                    Properties::set($target, $key, $this->merge($targetValue, $sourceValue));
                } else {
                    Properties::set($target, $key, $sourceValue);
                }
            } else {
                Properties::set($target, $key, $sourceValue);
            }
        }

        return $target;
    }

    private function isAssoc($value): bool
    {
        return (bool)array_filter(array_keys((array)$value), 'is_string');
    }
}