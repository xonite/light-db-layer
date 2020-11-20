<?php


namespace LightDBLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Statement;
use LightDBLayer\Exception\NoDataForOperation;
use LightDBLayer\Utility\DBCaseTranslator;

abstract class Repository
{
    protected ?string $table;
    protected Connection $db;

    use DBCaseTranslator;

    public function __construct(Connection $db)
    {
        $this->db = $db;
        $className = $this->getClassName($this);
        $this->table = $this->toDbCase($className);
    }


    public function insert(Entity $data): string
    {
        $params = $data->getArray();
        $statement = $this->prepareInsert($params);
        $statement->execute($params);
        return $this->db->lastInsertId();
    }

    public function delete(array $id): int
    {
        $id = (array)$id;
        if (count($id)) {
            $query = $this->db->prepare('DELETE FROM `' . $this->table . '`
              WHERE `id` IN(' . $this->arrayParam($id) . ')');
            $query->execute($id);
        }
        return $query->rowCount();
    }

    public function prepareInsert(array $data, bool $onDuplicateUpdate = false)
    {
        $query = 'INSERT INTO `' . $this->table . '` (';
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
        return $this->db->prepare($query);
    }

    public function updateBatch(Entity $data, array $ids): int
    {
        $data = $data->getArray();
        $query = 'UPDATE `' . $this->table . '` SET ';
        $placeholders = [];
        foreach (array_keys($data) as $keys) {
            $query .= '`' . $keys . '` = ?, ';
            $placeholders[] = $data[$keys];
        }
        $query = substr($query, 0, -2) . ' WHERE `id`=?';
        $statement = $this->db->prepare($query);
        $count = count($placeholders);
        foreach ($ids as $id) {
            $placeholders[$count] = $id;
            $statement->execute($placeholders);
        }
        return $statement->rowCount();
    }

    public function prepareUpdate(array $data, $id): Statement
    {
        $query = 'UPDATE `' . $this->table . '` SET ';
        $placeholders = [];
        foreach (array_keys($data) as $keys) {
            $query .= '`' . $keys . '` = ?, ';
            $placeholders[] = $data[$keys];
        }
        $query = substr($query, 0, -2) . ' WHERE `id` IN (';
        foreach ((array)$id as $val) {
            $query .= '?, ';
            $placeholders[] = $val;
        }
        $query = substr($query, 0, -2) . ')';
        $statement = $this->db->prepare($query);
    }

    public function update(array $data, $id): int
    {
        $statement = $this->prepareUpdate($data, $id);
        $statement->execute([...$data, $id]);
        return $statement->rowCount();
    }

    public function fetchObject($id): ?\stdClass
    {
        if ($id === null) {
            return null;
        }
        $query = $this->db->prepare('SELECT * FROM `' . $this->table . '` WHERE `id` = ? LIMIT 1');
        $query->execute([$id]);
        return $query->fetch(\PDO::FETCH_OBJ);
    }

    public function fetchSelected(array $id): Statement
    {
        $query = $this->db->prepare('SELECT * FROM `' . $this->table . '` WHERE `id` IN(' . $this->arrayParam($id) . ')');
        $query->execute($id);
        return $query;
    }

    public function fetchAll(?string $limit = null, ?string $orderBy = null): \Doctrine\DBAL\Driver\Statement
    {
        $limit = $limit !== null ? ' LIMIT ' . $limit : '';
        $orderBy = $orderBy !== null ? ' ORDER BY ' . $orderBy : '';
        return $this->db->query('SELECT * FROM `' . $this->table . '`' . $orderBy . $limit);
    }

    public function getEnumerableValues($field)
    {
        $enum = [];
        $type = $this->db->query("SHOW COLUMNS FROM {$this->table} WHERE Field = '{$field}'")->fetch();
        preg_match('/^enum\((.*)\)$/', $type['Type'], $matches);
        foreach (explode(',', $matches[1]) as $value) {
            $enum[] = trim($value, "'");
        }
        return $enum;
    }

    public function toSelectArray(array $enum): array
    {
        $array = [];
        foreach ($enum as $val) {
            $array[] = ['id' => $val, 'name' => $val];
        }
        return $array;
    }

    public function findFirst(Entity $terms, $insert = false)
    {
            $sql = 'SELECT `id` FROM `' . $this->table . '` WHERE ';
            $data = $terms->getArray();
            foreach ($data as $col => $row) {
                $sql .= '`' . $col . '` = :' . $col . ' AND ';
            }
            $sql = substr($sql, 0, -5);
            $query = $this->db->prepare($sql);
            $query->execute($data);
            $id = $query->fetch();
            if ($id) {
                return $id['id'];
            }
            if ($insert) {
                $query = $this->prepareInsert($data);
                $query->execute($data);
                return $this->db->lastInsertId();              
            }
            return false;
    }

    protected function reduceStatementToArray(Statement $statement, string $key): array
    {
        $ids = [];
        while ($data = $statement->fetch(\PDO::FETCH_OBJ)) {
            $ids[] = $data->$key;
        }
        return $ids;
    }

    protected function reduceStatementToIdArray(Statement $statement, string $key): array
    {
        $ids = [];
        while ($data = $statement->fetch(\PDO::FETCH_OBJ)) {
            $ids[$data->$key] = $data;
        }
        return $ids;
    }

    protected function migrate($from, $to, string $table, string $field)
    {
        $statement = $this->db->prepare('UPDATE `'.$table.'` SET `'.$field.'`=? WHERE `'.$field.'`=?');
        $statement->execute([$to, $from]);
    }

    protected function migrateOnlyUnique($from, $to, string $table, string $field, string $unique)
    {
        $statement = $this->db->prepare('UPDATE `'.$table.'`
          LEFT JOIN `'.$table.'` AS `to` ON `'.$table.'`.`'.$unique.'`=`to`.`'.$unique.'` AND `to`.`'.$field.'`=?
          SET `'.$table.'`.`'.$field.'`=? WHERE `'.$table.'`.`'.$field.'`=? AND `to`.`id` IS NULL');
        $statement->execute([$to, $to, $from]);
        $purgeOther = $this->db->prepare('DELETE FROM `'.$table.'` WHERE `'.$field.'`=?');
        $purgeOther->execute([$from]);
    }

    protected function arrayParam(array $array): string
    {
        if (!count($array)) {
            throw new NoDataForOperation();
        }
        return implode(',', array_fill(0, count($array), '?'));
    }

    protected function getClassName($object = null)
    {
        if (!is_object($object) && !is_string($object)) {
            return false;
        }

        $class = explode('\\', (is_string($object) ? $object : get_class($object)));
        return $class[count($class) - 1];
    }
}
