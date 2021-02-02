<?php


namespace LightDBLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Statement;
use LightDBLayer\Utility\DBCaseTranslator;
use LightDBLayer\Utility\SQLTransformation;

abstract class Repository
{
    protected Connection $db;

    use DBCaseTranslator;
    use SQLTransformation;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function fetch($id, string $table): ?array
    {
        if ($id === null) {
            return null;
        }
        $query = $this->db->prepare('SELECT * FROM `' . $table . '` WHERE `id` = ? LIMIT 1');
        $query->execute([$id]);
        $data = $query->fetch();
        return $data ? $data : null;
    }

    public function fetchSelected(array $id, string $table): Statement
    {
        $query = $this->db->prepare('SELECT * FROM `' . $table . '` WHERE `id` IN(' . $this->arrayParam($id) . ')');
        $query->execute($id);
        return $query;
    }

    public function fetchAll(?string $limit = null, ?string $orderBy = null): Statement
    {
        $limit = $limit !== null ? ' LIMIT ' . $limit : '';
        $orderBy = $orderBy !== null ? ' ORDER BY ' . $orderBy : '';
        return $this->db->query('SELECT * FROM `' . $this->table . '`' . $orderBy . $limit);
    }

    protected function reduceStatementToArray(Statement $statement, string $key): array
    {
        $result = [];
        while ($data = $statement->fetch()) {
            $result[] = $data[$key];
        }
        return $result;
    }

    protected function reduceStatementToKeyValue(Statement $statement, string $key): array
    {
        $ids = [];
        while ($data = $statement->fetch()) {
            $ids[$data[$key]] = $data;
        }
        return $ids;
    }

    //maybe not needed at all
    protected function migrate($from, $to, string $table, string $field)
    {
        $statement = $this->db->prepare('UPDATE `'.$table.'` SET `'.$field.'`=? WHERE `'.$field.'`=?');
        $statement->execute([$to, $from]);
    }

    //maybe not needed at all
    protected function migrateOnlyUnique($from, $to, string $table, string $field, string $unique)
    {
        $statement = $this->db->prepare('UPDATE `'.$table.'`
          LEFT JOIN `'.$table.'` AS `to` ON `'.$table.'`.`'.$unique.'`=`to`.`'.$unique.'` AND `to`.`'.$field.'`=?
          SET `'.$table.'`.`'.$field.'`=? WHERE `'.$table.'`.`'.$field.'`=? AND `to`.`id` IS NULL');
        $statement->execute([$to, $to, $from]);
        $purgeOther = $this->db->prepare('DELETE FROM `'.$table.'` WHERE `'.$field.'`=?');
        $purgeOther->execute([$from]);
    }
}
