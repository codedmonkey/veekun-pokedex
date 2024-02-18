<?php

namespace Pokedex;

class Association
{
    use MappingTrait;
    use ParsingTrait;
    use SortingTrait;

    public array $data = [];
    public string $mappingField;
    public array $mappings = [];

    function __construct(
        public string $filename,
        array $mappingCollections,
    ) {
        $this->read();

        $dataColumns = array_keys($this->data[0]);

        foreach ($mappingCollections as $dataColumn => $collection) {
            if (is_int($dataColumn)) {
                $dataColumn = $dataColumns[$dataColumn];
            }

            $this->mappings[$dataColumn] = $collection;
        }

        $this->mappingField = array_key_first($this->mappings);
    }

    public function buildStatChanges(Association $moveMetaStatChanges, Collection $stats): void
    {
        foreach ($this->data as &$row) {
            $row['stat_changes'] = [];

            foreach ($moveMetaStatChanges->data as $associationRow) {
                if ($associationRow['move_id'] === $row['move_id']) {
                    $statIdentifier = $stats->findIdentifier($associationRow['stat_id']);

                    $row['stat_changes'][$statIdentifier] = $associationRow['change'];
                }
            }
        }
    }

    public function mapField(string $field, string $id): array
    {
        $mappings = $this->mappings;
        $idField = array_key_first($mappings);
        array_shift($mappings);

        $result = [];

        foreach ($this->data as $row) {
            if ($row[$idField] !== $id) {
                continue;
            }

            $rowResult = &$result;

            foreach ($mappings as $mappingField => $mapping) {
                $mappingIdentifier = $mapping->findIdentifier($row[$mappingField]);
                $rowResult[$mappingIdentifier] ??= [];

                if ($mapping->orderable) {
                    uksort($rowResult, function ($a, $b) use ($mapping) {
                        return  $mapping->data[$a]['order'] <=> $mapping->data[$b]['order'];
                    });
                }

                $rowResult = &$rowResult[$mappingIdentifier];
            }

            $rowResult = $row[$field];
        }

        return $result;
    }
}
