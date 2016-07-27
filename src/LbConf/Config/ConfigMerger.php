<?php
namespace LbConf\Config;

class ConfigMerger
{
    public function merge($target, $source)
    {
        foreach ($source as $key => $sourceValue) {
            if (array_key_exists($key, $target)) {
                $targetValue = $target[$key];

                if (
                    is_array($targetValue) &&
                    is_array($sourceValue) &&
                    ($this->isAssoc($targetValue) || $this->isAssoc($sourceValue))
                ) {
                    $target[$key] = $this->merge($targetValue, $sourceValue);
                } else {
                    $target[$key] = $sourceValue;
                }
            } else {
                $target[$key] = $sourceValue;
            }
        }

        return $target;
    }

    private function isAssoc(array $value): bool
    {
        return (bool)array_filter(array_keys($value), 'is_string');
    }
}