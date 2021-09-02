<?php

namespace BM;

use PDO;

$languages = select('SELECT * FROM languages');

function select($sql, $parameters = []): array {
    global $pdo;

    $parameters = (array) $parameters;

    $statement = $pdo->prepare($sql);
    $statement->execute((array) $parameters);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function selectOne($sql, $parameters = []): ?array {
    global $pdo;

    $parameters = (array) $parameters;

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
}

function idArray($data): array {
    $output = [];

    foreach ($data as $row) {
        $output[$row['id']] = $row;
    }

    return $output;
}

function parseLocalizations($localizations, $column): array {
    global $languages;

    $output = [];

    foreach ($localizations as $localization) {
        foreach ($languages as $language) {
            if (($localization['local_language_id'] ?? $localization['language_id']) === $language['id']) {
                $output[$language['identifier']] = $localization[$column];
            }
        }
    }

    ksort($output);

    return $output;
}

function handleOutput($output, $parent = '') {
    $parentDirectory = dirname(__DIR__).'/pokedex/data/json/'.$parent;

    foreach ($output as $key => $value) {
        if ($key === '_') {
            continue;
        }

        if (isset($value['_'])) {
            if (!is_dir($parentDirectory)) {
                mkdir($parentDirectory, null, true);
            }

            file_put_contents($parentDirectory.'/'.$key.'.json', json_encode($value['_'], JSON_PRETTY_PRINT));
        }

        handleOutput($value, $parent === '' ? $key : ($parent.'/'.$key));
    }
}
