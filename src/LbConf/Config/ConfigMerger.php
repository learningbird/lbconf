<?php
namespace LbConf\Config;

class ConfigMerger
{
    /**
     * Merges two arrays of configurations.
     *
     * The steps are as follows:
     *     1. Start with the data in the target.
     *     2. If a key exists only in the source, copy it to the target.
     *     3. Otherwise, if either the target or source value is not an array, overwrite the target with the source.
     *     4. Otherwise, if either the target or source array is non-associative, overwrite the target with the source.
     *     5. Otherwise, recursively merge each element in the target and source values.
     *
     * @param $target
     * @param $source
     *
     * @return array
     */
    public function merge($target, $source): array
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