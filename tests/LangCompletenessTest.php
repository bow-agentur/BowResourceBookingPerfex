<?php

namespace BowRP\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Verifies all language keys used in RP views/controllers are defined
 * in the English language file (canonical source for this module).
 */
class LangCompletenessTest extends TestCase
{
    private static array $defined  = [];
    private static array $usedKeys = [];

    public static function setUpBeforeClass(): void
    {
        $lang = [];
        require __DIR__ . '/../language/english/bowresourceplanning_lang.php';
        self::$defined = array_keys($lang);

        $dirs = [
            __DIR__ . '/../views',
            __DIR__ . '/../controllers',
            __DIR__ . '/../helpers',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iter as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $src = file_get_contents($file->getPathname());
                // _l('key') and _l("key")
                preg_match_all("/_l\('([^']+)'\)/", $src, $m1);
                preg_match_all('/_l\("([^"]+)"\)/', $src, $m2);
                foreach (array_merge($m1[1], $m2[1]) as $key) {
                    self::$usedKeys[$key] = $file->getPathname();
                }
            }
        }
    }

    /**
     * @dataProvider usedKeyProvider
     */
    public function test_lang_key_is_defined(string $key, string $file): void
    {
        // Skip Perfex core keys (no prefix specific to this module)
        if (!str_starts_with($key, 'rb_') && !str_starts_with($key, 'bowresourceplanning')) {
            $this->addToAssertionCount(1);
            return;
        }

        $this->assertContains(
            $key,
            self::$defined,
            "Language key '{$key}' used in {$file} is missing from english/bowresourceplanning_lang.php"
        );
    }

    public static function usedKeyProvider(): array
    {
        if (empty(self::$usedKeys)) {
            self::setUpBeforeClass();
        }
        $cases = [];
        foreach (self::$usedKeys as $key => $file) {
            $cases[$key] = [$key, basename($file)];
        }
        return $cases;
    }

    public function test_no_duplicate_keys_in_english(): void
    {
        $src = file_get_contents(__DIR__ . '/../language/english/bowresourceplanning_lang.php');
        preg_match_all('/\$lang\[\'([^\']+)\'\]/', $src, $m);
        $keys = $m[1];
        $unique = array_unique($keys);
        $duplicates = array_diff_key($keys, $unique);

        $this->assertEmpty(
            array_unique($duplicates),
            'Duplicate lang keys: ' . implode(', ', array_unique($duplicates))
        );
    }

    public function test_german_and_english_keys_match(): void
    {
        $lang = [];
        require __DIR__ . '/../language/english/bowresourceplanning_lang.php';
        $en = array_keys($lang);

        $lang = [];
        require __DIR__ . '/../language/german/bowresourceplanning_lang.php';
        $de = array_keys($lang);

        sort($en);
        sort($de);

        $onlyEn = array_diff($en, $de);
        $onlyDe = array_diff($de, $en);

        $this->assertEmpty($onlyEn, 'Keys only in English: ' . implode(', ', $onlyEn));
        $this->assertEmpty($onlyDe, 'Keys only in German: ' . implode(', ', $onlyDe));
    }
}
