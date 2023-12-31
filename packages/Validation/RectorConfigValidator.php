<?php

declare (strict_types=1);
namespace Rector\Validation;

use Rector\Core\Exception\ShouldNotHappenException;
final class RectorConfigValidator
{
    /**
     * @param string[] $rectorClasses
     */
    public static function ensureNoDuplicatedClasses(array $rectorClasses) : void
    {
        $duplicatedRectorClasses = self::resolveDuplicatedValues($rectorClasses);
        if ($duplicatedRectorClasses === []) {
            return;
        }
        throw new ShouldNotHappenException('Following rules are registered twice: ' . \implode(', ', $duplicatedRectorClasses));
    }
    /**
     * @param mixed[] $skip
     */
    public static function ensureRectorRulesExist(array $skip) : void
    {
        $nonExistingRules = [];
        foreach ($skip as $key => $value) {
            if (self::isRectorClassValue($key) && !\class_exists($key)) {
                $nonExistingRules[] = $key;
                continue;
            }
            if (!self::isRectorClassValue($value)) {
                continue;
            }
            if (\class_exists($value)) {
                continue;
            }
            $nonExistingRules[] = $value;
        }
        if ($nonExistingRules === []) {
            return;
        }
        $nonExistingRulesString = '';
        foreach ($nonExistingRules as $nonExistingRule) {
            $nonExistingRulesString .= ' * ' . $nonExistingRule . \PHP_EOL;
        }
        throw new ShouldNotHappenException('These rules from "$rectorConfig->skip()" does not exist - remove them or fix their names:' . \PHP_EOL . $nonExistingRulesString);
    }
    /**
     * @param mixed $value
     */
    private static function isRectorClassValue($value) : bool
    {
        // only validate string
        if (!\is_string($value)) {
            return \false;
        }
        // not regex path
        if (\strpos($value, '*') !== \false) {
            return \false;
        }
        // not if no Rector suffix
        if (\substr_compare($value, 'Rector', -\strlen('Rector')) !== 0) {
            return \false;
        }
        // not directory
        if (\is_dir($value)) {
            return \false;
        }
        // not file
        return !\is_file($value);
    }
    /**
     * @param string[] $values
     * @return string[]
     */
    private static function resolveDuplicatedValues(array $values) : array
    {
        $counted = \array_count_values($values);
        $duplicates = [];
        foreach ($counted as $value => $count) {
            if ($count > 1) {
                $duplicates[] = $value;
            }
        }
        return \array_unique($duplicates);
    }
}
