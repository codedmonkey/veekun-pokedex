<?php

$pdo = new PDO('sqlite:'.__DIR__.'/pokedex/data/pokedex.sqlite');

include_once 'library/new.php';

$output = [];

$abilities = BM\idArray(BM\select('SELECT * FROM abilities WHERE is_main_series = "1"'));
$eggGroups = BM\idArray(BM\select('SELECT * FROM egg_groups'));
$items = BM\idArray(BM\select('SELECT * FROM items'));
$moves = BM\idArray(BM\select('SELECT * FROM moves'));
$pokemonMoveMethods = BM\idArray(BM\select('SELECT * FROM pokemon_move_methods'));
$stats = BM\idArray(BM\select('SELECT * FROM stats'));
$types = BM\idArray(BM\select('SELECT * FROM types'));
$versionGroups = BM\idArray(BM\select('SELECT * FROM version_groups'));

foreach ($versionGroups as $versionGroup) {
    $vg = str_replace('-', '', $versionGroup['identifier']);

    $groupVersions = BM\idArray(BM\select('SELECT * FROM versions WHERE version_group_id = ?', $versionGroup['id']));

    if ($vg !== 'emerald') {
        continue;
    }

    $output[$vg] = [
        'info' => ['_' => []],
        'ability-summaries' => ['_' => []],
        'pokedex' => ['_' => []],
        'species' => [],
        'species-summaries' => ['_' => []],
        'types' => ['_' => []],
    ];

    $output[$vg]['info']['_'] = [
        'name' => 'Emerald',
    ];

    foreach ($types as $type) {
        if (strpos($type['id'], '100') !== false) {
            continue;
        }

        if ($type['generation_id'] > $versionGroup['generation_id']) {
            continue;
        }

        $typeNames = BM\select('SELECT * FROM type_names WHERE type_id = ?', [$type['id']]);

        $output[$vg]['types']['_'][$type['identifier']] = [
            'name' => BM\parseLocalizations($typeNames, 'name'),
        ];
    }

    $versionPokedexs = BM\select('SELECT * FROM pokedex_version_groups WHERE version_group_id = ?', $versionGroup['id']);
    $versionPokedexs = array_merge([['pokedex_id' => 1]], $versionPokedexs);

    foreach ($versionPokedexs as $versionPokedex) {
        $pokedex = BM\selectOne('SELECT * FROM pokedexes WHERE id = ?', $versionPokedex['pokedex_id']);
        $pokedexProse = BM\select('SELECT * FROM pokedex_prose WHERE pokedex_id = ?', [$pokedex['id']]);

        $pokedexEntries = BM\select('SELECT * FROM pokemon_dex_numbers WHERE pokedex_id = ? ORDER BY pokedex_number', $pokedex['id']);

        $outputPokedexEntries = [];

        foreach ($pokedexEntries as $pokedexEntry) {
            $species = BM\selectOne('SELECT * FROM pokemon_species WHERE id = ?', $pokedexEntry['species_id']);

            if ($species['generation_id'] > $versionGroup['generation_id']) {
                continue;
            }

            $outputPokedexEntries[$pokedexEntry['pokedex_number']] = $species['identifier'];

            $output[$vg]['pokedex']['_'][$pokedex['identifier']] = [
                'name' => BM\parseLocalizations($pokedexProse, 'name'),
                'description' => BM\parseLocalizations($pokedexProse, 'description'),
                'entries' => $outputPokedexEntries,
            ];
        }
    }

    $pokemonForms = BM\Select('SELECT * FROM pokemon_forms');

    foreach ($pokemonForms as $pokemonForm) {
        $pokemonFormGenerationAssociation = BM\selectOne('SELECT * FROM pokemon_form_generations WHERE pokemon_form_id = ? AND generation_id = ? LIMIT 1', [$pokemonForm['id'], $versionGroup['generation_id']]);

        if (null === $pokemonFormGenerationAssociation) {
            continue;
        }

        if ($pokemonForm['introduced_in_version_group_id'] > $versionGroup['id']) {
            continue;
        }

        $pokemon = BM\selectOne('SELECT * FROM pokemon WHERE id = ?', [$pokemonForm['pokemon_id']]);
        $pokemonSpecies = BM\selectOne('SELECT * FROM pokemon_species WHERE id = ?', [$pokemon['species_id']]);

        if (!($output[$vg]['species'][$pokemonSpecies['identifier']]['_'] ?? false)) {
            $pokemonSpeciesNames = BM\select('SELECT * FROM pokemon_species_names WHERE pokemon_species_id = ?', [$pokemonSpecies['id']]);

            $outputEvolvesFrom = null;

            if ($pokemonSpecies['evolves_from_species_id'] !== null) {
                $evolvesFrom = BM\selectOne('SELECT * FROM pokemon_species WHERE id = ?', [$pokemonSpecies['evolves_from_species_id']]);
                $outputEvolvesFrom = $evolvesFrom['identifier'];
            }

            $outputEvolutionChain = null;

            if ($pokemonSpecies['evolution_chain_id'] !== null) {
                // todo find evolution chain
            }

            $pokemonEggGroups = BM\select('SELECT * FROM pokemon_egg_groups WHERE species_id = ?', [$pokemonSpecies['id']]);

            $outputEggGroups = [];

            foreach ($pokemonEggGroups as $pokemonEggGroup) {
                $outputEggGroups[] = $eggGroups[$pokemonEggGroup['egg_group_id']]['identifier'];
            }

            $outputDexNumbers = [];

            foreach ($versionPokedexs as $versionPokedex) {
                $pokedex = BM\selectOne('SELECT * FROM pokedexes WHERE id = ?', $versionPokedex['pokedex_id']);
                $pokedexEntry = BM\selectOne('SELECT * FROM pokemon_dex_numbers WHERE species_id = ? AND pokedex_id = ?', [$species['id'], $versionPokedex['pokedex_id']]);

                if ($pokedexEntry) {
                    $outputDexNumbers[$pokedex['identifier']] = $pokedexEntry['pokedex_number'];
                }
            }

            $output[$vg]['species'][$pokemonSpecies['identifier']] = ['_' => [
                'name' => BM\parseLocalizations($pokemonSpeciesNames, 'name'),
                'dexNumbers' => $outputDexNumbers,
                'classification' => BM\parseLocalizations($pokemonSpeciesNames, 'genus'),
                'evolvesFrom' => $outputEvolvesFrom,
                'eggGroups' => $outputEggGroups,
                'defaultForm' => null,
                'forms' => [],
            ]];

            $output[$vg]['species-summaries']['_'][$pokemonSpecies['identifier']] = [
                'name' => BM\parseLocalizations($pokemonSpeciesNames, 'name'),
                'defaultType' => null,
            ];
        }

        $pokemonStats = BM\select('SELECT * FROM pokemon_stats WHERE pokemon_id = ?', [$pokemon['id']]);

        $outputStats = [];
        $outputEffortValues = [];

        foreach ($pokemonStats as $pokemonStat) {
            $stat = $stats[$pokemonStat['stat_id']];
            $statId = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $stat['identifier']))));

            $outputStats[$statId] = $pokemonStat['base_stat'];

            if ($pokemonStat['effort'] !== '0') {
                $outputEffortValues[$statId] = $pokemonStat['effort'];
            }
        }

        $pokemonTypes = BM\select('SELECT * FROM pokemon_types WHERE pokemon_id = ?', [$pokemon['id']]);

        $outputTypes = [];

        foreach ($pokemonTypes as $pokemonType) {
            $outputTypes[] = $types[$pokemonType['type_id']]['identifier'];
        }

        $pokemonAbilities = BM\select('SELECT * FROM pokemon_abilities WHERE pokemon_id = ? ORDER BY slot', [$pokemon['id']]);

        $outputAbilities = [];

        foreach ($pokemonAbilities as $pokemonAbility) {
            if ($abilities[$pokemonAbility['ability_id']]['generation_id'] > $versionGroup['generation_id']) {
                continue;
            }

            $outputAbilities[] = [
                'ability' => $abilities[$pokemonAbility['ability_id']]['identifier'],
                'hidden' => $pokemonAbility['is_hidden'] === '1',
            ];
        }

        $pokemonMoves = BM\select('SELECT * FROM pokemon_moves WHERE pokemon_id = ? and version_group_id = ? ORDER BY level, `order`', [$pokemon['id'], $versionGroup['id']]);

        $outputMoves = [];

        if ($pokemonForm['is_battle_only'] !== '1') {
            foreach ($pokemonMoves as $pokemonMove) {
                $move = $moves[$pokemonMove['move_id']];

                $outputMoves[] = [
                    'move' => $move['identifier'],
                    'level' => $pokemonMove['level'] !== '0' ? $pokemonMove['level'] : null,
                    'method' => $pokemonMoveMethods[$pokemonMove['pokemon_move_method_id']]['identifier'],
                ];
            }
        }

        $outputHeldItems = [];

        foreach ($groupVersions as $version) {
            $heldItem = BM\selectOne('SELECT * FROM pokemon_items WHERE pokemon_id = ? AND version_id = ?', [$pokemon['id'], $version['id']]);

            if ($heldItem) {
                $outputHeldItems[] = [
                    'item' => $items[$heldItem['item_id']]['identifier'],
                    'rarity' => $heldItem['rarity'],
                    //'game' => $version['identifier'], // todo only apply for version-specific items
                ];
            }
        }

        $output[$vg]['species'][$pokemonSpecies['identifier']]['_']['forms'][$pokemonForm['identifier']] = [
            'name' => false,
            'type' => $outputTypes,
            'height' => $pokemon['height'],
            'weight' => $pokemon['weight'],
            'isBattleOnly' => $pokemonForm['is_battle_only'] === '1',
            'isMega' => $pokemonForm['is_mega'] === '1',
            'baseStats' => $outputStats,
            'baseExperience' => $pokemon['base_experience'],
            'effortValues' => $outputEffortValues,
            'abilities' => $outputAbilities,
            'heldItems' => $outputHeldItems,
            'moves' => $outputMoves,
        ];

        $pokemonFormNames = BM\select('SELECT * FROM pokemon_form_names WHERE pokemon_form_id = ?', [$pokemonForm['id']]);

        if (count($pokemonFormNames) > 0) {
            // todo translations of form names without a form_name value
            $output[$vg]['species'][$pokemonSpecies['identifier']]['_']['forms'][$pokemonForm['identifier']]['name'] = BM\parseLocalizations($pokemonFormNames, 'form_name');
        }

        if ($pokemonForm['is_default'] === '1' && $pokemon['is_default'] === '1') {
            $output[$vg]['species'][$pokemonSpecies['identifier']]['_']['defaultForm'] = $pokemonForm['identifier'];

            $output[$vg]['species-summaries']['_'][$pokemonSpecies['identifier']]['defaultType'] = $outputTypes;
        }
    }

    foreach ($abilities as $ability) {
        if ($ability['generation_id'] > $versionGroup['generation_id']) {
            continue;
        }

        $abilityNames = BM\select('SELECT * FROM ability_names WHERE ability_id = ?', [$ability['id']]);
        $abilityFlavorTexts = BM\select('SELECT * FROM ability_flavor_text WHERE ability_id = ? AND version_group_id = ?', [$ability['id'], $versionGroup['id']]);

        $output[$vg]['ability-summaries']['_'][$ability['identifier']] = [
            'name' => BM\parseLocalizations($abilityNames, 'name'),
            'flavorText' => BM\parseLocalizations($abilityFlavorTexts, 'flavor_text'),
        ];
    }
}

BM\handleOutput($output);
