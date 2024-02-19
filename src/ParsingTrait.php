<?php

namespace Pokedex;

use Symfony\Component\Serializer\Encoder\CsvEncoder;

trait ParsingTrait
{
    public static ?CsvEncoder $csvEncoder = null;

    public function inheritIdentifier(Collection $collection, string $field): static
    {
        $data = [];

        foreach ($this->data as $row) {
            $identifier = $collection->findIdentifier($row[$field]);

            $data[$identifier] = $row;
        }

        $this->data = $data;

        return $this;
    }

    public function inheritIdentifierFirstOccurrence(Collection $collection, string $mappingField): static
    {
        $data = [];

        foreach ($this->data as $row) {
            foreach ($collection->data as $collectionIdentifier => $collectionRow) {
                if ($collectionRow[$mappingField] === $row['id']) {
                    $data[$collectionIdentifier] = $row;

                    break;
                }
            }
        }

        $this->data = $data;

        return $this;
    }

    function parseBoolean(string $field): static
    {
        foreach ($this->data as &$row) {
            if ($row[$field] === '1') {
                $row[$field] = true;
            } elseif ($row[$field] === '0') {
                $row[$field] = false;
            } else {
                $row[$field] = null;
            }
        }

        return $this;
    }

    function parseInteger(string $field): static
    {
        foreach ($this->data as &$row) {
            if ($row[$field] === '') {
                $row[$field] = null;
            } else {
                $row[$field] = (int) $row[$field];
            }
        }

        return $this;
    }

    function parseNullAs(string $field, ?string $default = null): static
    {
        foreach ($this->data as &$row) {
            if ($row[$field] === '') {
                $row[$field] = $default;
            }
        }

        return $this;
    }

    function prefixIdentifier(string $prefix): static
    {
        $data = [];

        foreach ($this->data as $identifier => $row) {
            $data[$prefix.$identifier] = $row;
        }

        $this->data = $data;

        return $this;
    }

    function copy(string $from, string $to): static
    {
        foreach ($this->data as &$row) {
            $row[$to] = $row[$from];
        }

        return $this;
    }

    protected function read(): void
    {
        self::$csvEncoder ??= new CsvEncoder();

        $rawContents = file_get_contents(DATA_PATH . '/' . $this->filename . '.csv');
        $this->data = self::$csvEncoder->decode($rawContents, 'csv');
    }
}
