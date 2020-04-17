<?php

namespace Gueoth\Support;

use Exception;
use Gueoth\Config;

class ConfigHelper
{
    /**
     * Generate a fresh repo config.
     *
     * @param int   $formatVersion
     * @param bool  $filemode
     * @param bool  $bare
     * @param bool  $logRefUpdates
     * @param bool  $ignoreCase
     * @param bool  $preComposeUnicode
     * @param array $config
     *
     * @return \Gueoth\Config
     */
    public static function repoConfig(
        int $formatVersion = 0,
        bool $filemode = false,
        bool $bare = false,
        bool $logRefUpdates = true,
        bool $ignoreCase = true,
        bool $preComposeUnicode = true,
        array $config = []
    ): Config
    {
        return new Config(array_merge($config, [
            'core.repositoryformatversion' => $formatVersion,
            'core.filemode'                => $filemode,
            'core.bare'                    => $bare,
            'core.logallrefupdates'        => $logRefUpdates,
            'core.ignorecase'              => $ignoreCase,
            'core.precomposeunicode'       => $preComposeUnicode,
        ]));
    }

    public static function parseConfig(string $path): Config
    {
        return new Config(self::flattenArray(parse_ini_file($path, true, INI_SCANNER_TYPED)));
    }

    public static function writeConfig(string $path, Config $config): bool
    {
        try {
            $iterator      = $config->getIterator();
            $data          = [];
            $sectionedData = [];

            foreach ($iterator as $key => $value) {
                if (substr_count($key, '.') > 0) {
                    [$section, $key] = explode('.', $key, 2);
                    $sectionedData[$section][$key] = $value;
                } else {
                    $data[$key] = $value;
                }
            }

            $ini = '';

            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    if (! empty($ini)) {
                        $ini .= PHP_EOL;
                    }

                    $ini .= '[' . $key . ']' . PHP_EOL;

                    foreach ($value as $sectionKey => $sectionValue) {
                        $ini .= $sectionKey . '=' . (is_string($sectionValue) ? '"' . $sectionValue . '"' : $sectionValue) . PHP_EOL;
                    }
                } else {
                    $ini .= $key . '=' . (is_string($value) ? '"' . $value . '"' : $value) . PHP_EOL;
                }
            }

            $ini = '';
            self::buildIni($data, $sectionedData, $ini);

            return file_put_contents($path, $ini);
        } catch (Exception $e) {
            return false;
        }
    }

    private static function flattenArray(array $array, ?string $prefix = null): array
    {
        $flattened = [];

        foreach ($array as $key => $value) {
            $prefixedKey = ($prefix !== null ? $prefix . '.' : '') . $key;

            if (is_array($value)) {
                $flattened[] = self::flattenArray($value, $prefixedKey);
            } else {
                $flattened[] = [$prefixedKey => $value];
            }
        }

        return array_merge(...$flattened);
    }

    private static function buildIni(array $data, array $sectionedData, string &$ini): void
    {
        foreach ($data as $key => $value) {
            $ini .= $key . '=' . (is_string($value) ? '"' . $value . '"' : $value) . PHP_EOL;
        }

        if (! empty($sectionedData)) {
            foreach ($sectionedData as $key => $value) {
                if (! empty($ini)) {
                    $ini .= PHP_EOL;
                }

                $ini .= '[' . $key . ']' . PHP_EOL;
                self::buildIni($value, [], $ini);
            }
        }
    }
}