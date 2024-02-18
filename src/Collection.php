<?php

namespace Pokedex;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class Collection
{
    use MappingTrait;
    use ParsingTrait {
        read as baseRead;
    }
    use SortingTrait;

    public static ?Filesystem $filesystem = null;

    public array $data = [];

    function __construct(
        public string $filename,
        public ?string $identifierField = 'identifier',
        public bool $orderable = false,
    ) {
        self::$filesystem ??= new Filesystem();

        $this->read();
    }

    function addOrderField(): self
    {
        $order = 1;
        foreach ($this->data as &$row) {
            $row['order'] = $order++;
        }

        return $this;
    }

    function buildMachines(Collection $items, Collection $moves, Collection $versionGroups): void
    {
        $data = [];

        foreach ($this->data as $row) {
            $itemIdentifier = $items->findIdentifier($row['item_id']);
            $moveIdentifier = $moves->findIdentifier($row['move_id']);
            $versionGroupIdentifier = $versionGroups->findIdentifier($row['version_group_id']);

            $data[$itemIdentifier][$versionGroupIdentifier] = $moveIdentifier;
        }

        $this->data = $data;
    }

    function embedAssociation(Association $association, string $field, ?string $associationIdentifierField = null, ?string $associationValueField = null): self
    {
        foreach ($this->data as &$row) {
            $row[$field] = [];

            foreach ($association->data as $associationRow) {
                if ($associationRow[$association->mappingField] === $row['id']) {
                    $value = $associationRow;
                    unset($value[$association->mappingField]);

                    if ($associationValueField) {
                        $value = $value[$associationValueField];
                    } else {
                        if ($associationIdentifierField) {
                            unset($value[$associationIdentifierField]);
                        }

                        ksort($value);
                    }

                    if (!$associationIdentifierField) {
                        $row[$field][] = $value;
                    } else {
                        $row[$field][$associationRow[$associationIdentifierField]] = $value;
                    }
                }
            }
        }

        return $this;
    }

    function embedCollection(Collection $collection, string $field, string $mappingField, ?string $collectionIdentifierField = null): self
    {
        $collection->unsetFields(['id']);

        foreach ($this->data as &$row) {
            $row[$field] = [];

            foreach ($collection->data as $collectionIdentifier => $collectionRow) {
                if ($collectionRow[$mappingField] === $row['id']) {
                    $value = $collectionRow;
                    unset($value[$mappingField]);

                    if ($collectionIdentifierField) {
                        unset($value[$collectionIdentifierField]);
                    }

                    ksort($value);

                    if (!$collectionIdentifierField) {
                        if ($collection->identifierField) {
                            $row[$field][$collectionIdentifier] = $value;
                        } else {
                            $row[$field][] = $value;
                        }
                    } else {
                        $row[$field][$collectionRow[$collectionIdentifierField]] = $value;
                    }
                }
            }
        }

        return $this;
    }

    function embedCollectionField(Collection $collection, string $field, string $idField, string $mappingField = null): self
    {
        $mappingField ??= $field;

        foreach ($this->data as &$row) {
            $embedIdentifier = $collection->findIdentifier($row[$idField]);
            $embedRow = $collection->data[$embedIdentifier];

            $row[$field] = $embedRow[$mappingField];
        }

        return $this;
    }

    function embedGroupedAssociation(Association $association, string $field, array $groupByFields, ?string $associationValueField = null): self
    {
        foreach ($this->data as &$row) {
            $row[$field] = [];

            foreach ($association->data as $associationRow) {
                if ($associationRow[$association->mappingField] === $row['id']) {
                    $value = $associationRow;
                    unset($value[$association->mappingField]);

                    ksort($value);

                    $groupValue = &$row[$field];

                    foreach ($groupByFields as $groupField => $groupMapping) {
                        $groupIdentifier = $groupMapping->findIdentifier($value[$groupField]);
                        $groupValue[$groupIdentifier] ??= [];

                        unset($value[$groupField]);

                        $groupValue = &$groupValue[$groupIdentifier];
                    }

                    if ($associationValueField) {
                        $groupValue = $value[$associationValueField];
                    } else {
                        $groupValue = $value;
                    }
                }
            }
        }

        return $this;
    }

    function embedGroupedCollection(Collection $collection, string $field, string $mappingField, array $groupByFields): self
    {
        $collection->unsetFields(['id']);

        foreach ($this->data as &$row) {
            $row[$field] = [];

            foreach ($collection->data as $collectionRow) {
                if ($collectionRow[$mappingField] === $row['id']) {
                    $value = $collectionRow;
                    unset($value[$mappingField]);

                    ksort($value);

                    $groupValue = &$row[$field];

                    foreach ($groupByFields as $groupField => $groupMapping) {
                        $groupIdentifier = $groupMapping->findIdentifier($value[$groupField]);
                        $groupValue[$groupIdentifier] ??= [];

                        unset($value[$groupField]);

                        $groupValue = &$groupValue[$groupIdentifier];
                    }

                    $groupValue = $value;
                }
            }
        }

        return $this;
    }

    function embedOneToOneAssociation(Association $association, string $field): self
    {
        foreach ($this->data as &$row) {
            $row[$field] = null;

            foreach ($association->data as $associationRow) {
                if ($associationRow[$association->mappingField] === $row['id']) {
                    $value = $associationRow;
                    unset($value[$association->mappingField]);

                    $row[$field] = $value;

                    break;
                }
            }
        }

        return $this;
    }

    public function embedJoin(Join $join, string $field): self
    {
        foreach ($this->data as &$row) {
            $row[$field] = [];

            foreach ($join->data as $joinRow) {
                if ($joinRow[$join->mappingField] === $row['id']) {

                    $value = $join->values->findIdentifier($joinRow[$join->valueField]);
                    $row[$field][] = $value;
                }
            }
        }

        return $this;
    }

    function findIdentifier(string $id): ?string
    {
        if (!$this->identifierField) {
            throw new \Exception('No identifier specified');
        }

        if ($id === '') {
            return null;
        }

        foreach ($this->data as $identifier => $row) {
            if ($row['id'] === $id) {
                return $identifier;
            }
        }

        throw new \Exception('Invalid id "' . $id . '" in collection "' . $this->filename . '".');
    }

    function unsetFields(array $fields): self
    {
        foreach ($this->data as &$row) {
            foreach ($fields as $field) {
                unset($row[$field]);
            }
        }

        return $this;
    }

    function write(bool $splitToFiles = false): void
    {
        if ($splitToFiles) {
            self::$filesystem->mkdir(BUILD_PATH . '/' . $this->filename);

            foreach ($this->data as $identifier => $row) {
                $contents = $this->dump([$identifier => $row]);
                file_put_contents(BUILD_PATH . '/' . $this->filename . '/'. $identifier . '.yaml', $contents);
            }
        } else {
            $contents = $this->dump($this->data);
            file_put_contents(BUILD_PATH . '/' . $this->filename . '.yaml', $contents);
        }
    }

    protected function dump(array $data): string
    {
        return Yaml::dump($data, 32, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_NULL_AS_TILDE);
    }

    protected function read(): void
    {
        $this->baseRead();

        if ($this->identifierField) {
            $data = $this->data;
            $this->data = [];

            foreach ($data as $row) {
                $identifier = $row[$this->identifierField];

                if ($this->identifierField !== 'id') {
                    unset($row[$this->identifierField]);
                }

                $this->data[$identifier] = $row;
            }
        }
    }

    public function mapCsTranslations(Collection $translationsCs, Collection $languages, string $table): self
    {
        foreach ($this->data as &$row) {
            foreach ($translationsCs->data as $translationRow) {
                if ($translationRow['table'] === $table && $translationRow['id'] === $row['id']) {
                    $field = $translationRow['column'];
                    $languageIdentifier = $languages->findIdentifier($translationRow['language_id']);

                    if (isset($row[$field])) {
                        $row[$field][$languageIdentifier] = $translationRow['string'];
                    }
                }
            }
        }

        return $this;
    }
}
