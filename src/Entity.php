<?php
namespace LightDBLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use LightDBLayer\Utility\DBCaseTranslator;
use LightDBLayer\Utility\SQLTransformation;

abstract class Entity
{
    use DBCaseTranslator;
    use SQLTransformation;

    private const FORCE_NULL = "\0";

    public function __construct(private ?Connection $connection = null) {}

    public function insert(bool $onDuplicateUpdate = false): string
    {
        if ($this->connection === null) {
            throw new ConnectionException();
        }
        $data = $this->getArray();
        $query = 'INSERT INTO `' . $this->getTable() . '` (';
        foreach (array_keys($data) as $keys) {
            $query .= '`' . $keys . '`, ';
        }
        $query = substr($query, 0, -2) . ') VALUES (';
        foreach (array_keys($data) as $keys) {
            $query .= ':' . $keys . ', ';
        }
        $query = substr($query, 0, -2) . ')';
        if ($onDuplicateUpdate) {
            $query .= ' ON DUPLICATE KEY UPDATE ';
            foreach (array_keys($data) as $keys) {
                $query .= '`' . $keys . '`=:' . $keys . ', ';
            }
            $query = substr($query, 0, -2);
        }
        $statement = $this->connection->prepare($query);
        $statement->execute($data);
        return $this->connection->lastInsertId();
    }

    public function update(array $ids): int
    {
        if ($this->connection === null) {
            throw new ConnectionException();
        }
        $data = $this->getArray();
        $query = 'UPDATE `' . $this->getTable() . '` SET ';
        $placeholders = [];
        foreach (array_keys($data) as $keys) {
            $query .= '`' . $keys . '` = ?, ';
            $placeholders[] = $data[$keys];
        }
        $query = substr($query, 0, -2) . ' WHERE `id`=?';
        $statement = $this->connection->prepare($query);
        $count = count($placeholders);
        foreach ($ids as $id) {
            $placeholders[$count] = $id;
            $statement->execute($placeholders);
        }
        return $statement->rowCount();
    }

    public function delete(array $ids): int
    {
        if ($this->connection === null) {
            throw new ConnectionException();
        }
        if (count($ids)) {
            $query = $this->connection->prepare("DELETE FROM `{$this->getTable()}` WHERE `id` IN({$this->arrayParam($ids)})");
            $query->execute($ids);
        }
        return $query->rowCount();
    }

    public function fetchId($insert = false): ?string
    {
        if ($this->connection === null) {
            throw new ConnectionException();
        }
        $sql = 'SELECT `id` FROM `' . $this->getTable() . '` WHERE ';
        $data = $this->getArray();
        foreach ($data as $col => $row) {
            $sql .= '`' . $col . '` = :' . $col . ' AND ';
        }
        $sql = substr($sql, 0, -5);
        $query = $this->connection->prepare($sql);
        $query->execute($data);
        $id = $query->fetch();
        if ($id) {
            return $id['id'];
        }
        if ($insert) {
            return $this->insert();
        }
        return null;
    }

    public function getArray(): array
    {
        $data = $this->getPublicVars();
        foreach ($data as $k => $row) {
            //Magic happens here, possibly php bug because it filters out undefined nulls only
            //When comparing with normal null it filters undefined and defined nulls.
            if ($row === self::FORCE_NULL) {
                unset($data[$k]);
            }
        }
        return $this->translateData($data);
    }

    private function getTable()
    {
        $class = explode('\\', (is_string($this) ? $this : get_class($this)));
        return $this->toDbCase($class[count($class) - 1]);
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

    private function getPublicVars(): array
    {
        $me = new class {
            function getPublicVars($object)
            {
                return get_object_vars($object);
            }
        };
        return $me->getPublicVars($this);
    }

    //deprecated
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
}