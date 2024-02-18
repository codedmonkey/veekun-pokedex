<?php

namespace Pokedex;

trait MappingTrait
{
    public function mapAssociationField(Association $collection, string $field, ?string $mappingField = null): static
    {
        $mappingField ??= $field;

        foreach ($this->data as &$row) {
            $row[$field] = $collection->mapField($mappingField, $row['id']);
        }

        return $this;
    }

    function mapIdentifier(Collection $collection, string $field, ?string $mappingField = null): static
    {
        $mappingField ??= "{$field}_id";

        foreach ($this->data as &$row) {
            $value = $collection->findIdentifier($row[$mappingField]);
            unset($row[$mappingField]);

            $row[$field] = $value;
        }

        return $this;
    }

    public function mapLocalizedField(Localizations $localizations, string $field): static
    {
        return $this->mapAssociationField($localizations, $field);
    }

    public function mapNames(Localizations $names): static
    {
        return $this->mapLocalizedField($names, 'name');
    }
}
