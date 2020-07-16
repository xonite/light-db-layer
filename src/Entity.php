<?php


namespace LightDBLayer;


use LightDBLayer\Utility\DBCaseTranslator;

abstract class Entity
{
    use DBCaseTranslator;

    const FORCE_NULL = "\0";

    public function getArray(): array
    {
        $data = get_object_vars($this);

        foreach ($data as $k => $row) {
            //Magic happens here, possibly php bug because it filters out undefined nulls only
            //When comparing with normal null it filters undefined and defined nulls.
            if ($row === self::FORCE_NULL) {
                unset($data[$k]);
            }
        }
        return $this->translateData($data);
    }

    public function hydrate(\stdClass $scope): self
    {
        foreach (get_object_vars($scope) as $key => $value) {
            $property = $this->toCamelCase($key);
            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }
        return $this;
    }

    private function translateData(array $data): array
    {
        $translated = [];
        foreach ($data as $k => $row) {
            $translated[$this->toDbCase($k)] = $row;
            //looks for Formatter method in entity class and applies it to value
            if (method_exists($this, $k.'Formatter')) {
                $translated[$this->toDbCase($k)] = $this->{$k.'Formatter'}();
            }
        }
        return $translated;
    }
}