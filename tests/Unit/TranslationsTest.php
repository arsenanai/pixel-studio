<?php

const STORED_MESSAGES = [
    'Pixelforge was too busy to accept this generation, so it was cancelled.',
    'Pixelforge declined this prompt under its content policy.',
    'The daily Pixelforge generation limit has been reached. It resets at midnight UTC.',
    'Pixelforge could not accept this generation.',
    'Pixelforge did not say why.',
    'The model could not produce an image for this prompt.',
    'The model could not produce an image for this prompt. You were not charged.',
    'Something went wrong on our side while generating. You were not charged; please retry.',
];

/** @return array<string, string> */
function kazakh(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/lang/kk.json'), true, flags: JSON_THROW_ON_ERROR);
}

/** @return list<string> */
function translationKeysUsedInCode(): array
{
    $root = dirname(__DIR__, 2);
    $patterns = [
        'js' => '/(?<![\w.$])\$?tc?\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s',
        'php' => '/__\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s',
    ];
    $files = [
        'js' => sourceFiles("{$root}/resources/js"),
        'php' => [...sourceFiles("{$root}/app"), "{$root}/bootstrap/app.php"],
    ];

    $keys = [];

    foreach ($files as $kind => $paths) {
        foreach ($paths as $path) {
            preg_match_all($patterns[$kind], (string) file_get_contents($path), $matches, PREG_SET_ORDER);

            foreach ($matches as [, $quote, $key]) {
                $key = str_replace(['\\'.$quote, '\\\\'], [$quote, '\\'], $key);

                if ($kind === 'php' && preg_match('/^[a-z_]+\.[A-Za-z_.*]+$/', $key) === 1) {
                    continue;
                }

                $keys[$key] = true;
            }
        }
    }

    return array_keys($keys);
}

/** @return list<string> */
function sourceFiles(string $directory): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (in_array($file->getExtension(), ['php', 'vue', 'ts'], true)) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

/** @return list<string> */
function placeholders(string $text): array
{
    preg_match_all('/:([a-zA-Z]+)/', $text, $matches);
    $names = array_values(array_unique($matches[1]));
    sort($names);

    return $names;
}

it('finds the translation keys used across the app', function () {
    expect(count(translationKeysUsedInCode()))->toBeGreaterThan(150);
});

it('translates every string the app shows into Kazakh', function () {
    $missing = array_values(array_diff([...translationKeysUsedInCode(), ...STORED_MESSAGES], array_keys(kazakh())));

    expect($missing)->toBe([]);
});

it('keeps every placeholder in the Kazakh text', function () {
    $broken = [];

    foreach (kazakh() as $english => $translated) {
        foreach (explode('|', $translated) as $form) {
            if (placeholders($form) !== placeholders($english)) {
                $broken[$english] = $translated;
            }
        }
    }

    expect($broken)->toBe([]);
});

it('only translates validation rules that exist in English', function () {
    $english = require dirname(__DIR__, 2).'/lang/en/validation.php';
    $kazakh = require dirname(__DIR__, 2).'/lang/kk/validation.php';

    expect(array_diff(array_keys($kazakh), array_keys($english)))->toBe([]);
});
