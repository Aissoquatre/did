<?php

namespace Did\Database;

use DateTime;
use Did\Kernel\Environment;
use PDO;
use ReflectionClass;
use Did\Tools\StringTool;

/**
 * Class SmartConnector
 *
 *
 * @package Did\Database
 * @author (c) Julien Bernard <hello@julien-bernard.com>
 *
 * @method getId()
 */
class SmartConnector extends AbstractConnection
{
    /**
     * @var ReflectionClass
     */
    protected $childClass;

    /**
     * @var array
     */
    protected $errors          = [];

    /**
     * @var array
     */
    protected $selectedColumns = [];

    /**
     * @var bool
     */
    public $forDatabase        = false;

    public function __construct()
    {
        parent::connect();

        $this->childClass = new ReflectionClass($this);
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return string
     */
    public function getSelectedColumns()
    {
        return implode(',', $this->selectedColumns);
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return static::TABLE;
    }

    /**
     * @param string $class
     * @return mixed
     */
    public static function model(?string $class = null)
    {
        if (!$class) {
            $class = static::ENTITY;
        }

        return new $class();
    }

    /**
     * @param array $datas
     * @return mixed
     */
    public function setAttributes(array $datas)
    {
        foreach($datas as $field => $data) {
            $this->{'set' . ucfirst($field)}(StringTool::sanitize($data));
        }

        return $this;
    }

    /**
     * @param string|array $columnName
     * @return mixed
     */
    public function select($columnName)
    {
        $this->selectedColumns = is_array($columnName) ? $columnName : [$columnName];

        return $this;
    }

    /**
     * @param array $criterias
     * @param array $clauses
     * @return null|int
     */
    public function count(array $criterias = [], array $clauses = [])
    {
        $return  = null;
        $request = $this->db->prepare(
            sprintf(
                'SELECT COUNT(%s) as counter FROM %s %s',
                $this->getSelectedColumns() ? $this->getSelectedColumns() : static::COUNT_KEY,
                $this->getTable(),
                $this->createConditions($criterias, $clauses)
            )
        );
        $res = $request->execute();

        if ($res && ($res = $request->fetch(PDO::FETCH_ASSOC))) {
            $return = $res['counter'];
        }

        return $return;
    }

    /**
     * @param array $criterias
     * @param array $clauses
     * @return array
     */
    public function findAll(array $criterias = [], array $clauses = [])
    {
        $return  = [];
        $request = $this->db->prepare(
            sprintf(
                'SELECT %s FROM %s %s',
                $this->getSelectedColumns() ? $this->getSelectedColumns() : '*',
                $this->getTable(),
                $this->createConditions($criterias, $clauses)
            )
        );
        $res = $request->execute();

        if ($res && ($res = $request->fetchAll(PDO::FETCH_ASSOC))) {
            foreach ($res as $row) {
                $obj = $this->toObject($row);

                if (!empty($clauses['index'])) {
                    $return[$obj->{'get' . ucfirst($clauses['index'])}()] = $obj;
                } else {
                    $return[] = $obj;
                }
            }
        }

        return $return;
    }

    /**
     * @param array $criterias
     * @return mixed
     */
    public function find(array $criterias = [])
    {
        $return  = null;
        $request = $this->db->prepare(
            sprintf(
                'SELECT %s FROM %s %s LIMIT 1',
                $this->getSelectedColumns() ? $this->getSelectedColumns() : '*',
                $this->getTable(),
                $this->createConditions($criterias)
            )
        );
        $res = $request->execute();

        if ($res && ($res = $request->fetch(PDO::FETCH_ASSOC))) {
            $return = $this->toObject($res);
        }

        return $return;
    }

    /**
     * @param int $id
     * @return mixed
     */
    public function findById(int $id)
    {
        $return = null;
        $request = $this->db->prepare(
            sprintf(
                'SELECT %s FROM %s WHERE id = %s',
                $this->getSelectedColumns() ? $this->getSelectedColumns() : '*',
                $this->getTable(),
                $id
            )
        );

        $res = $request->execute();

        if ($res && ($res = $request->fetch(PDO::FETCH_ASSOC))) {
            $return = $this->toObject($res);
        }

        return $return;
    }

    /**
     * @param string $query
     * @param array $clauses
     * @return mixed
     */
    public function findBySQL(string $query, array $clauses = [])
    {
        $return  = null;
        $request = $this->db->prepare($query);
        $request->execute();

        $result = $request->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($clauses)) {
            foreach ($result as $row) {
                $tmpRow = !empty($clauses['field']) ? $row[$clauses['field']] : $row;

                if (!empty($clauses['index'])) {
                    $return[$row[$clauses['index']]] = $tmpRow;
                } else {
                    $return[] = $tmpRow;
                }
            }
        } else {
            $return = $result;
        }

        return $return;
    }

    public function save()
    {
        try {
            $this->forDatabase = true;

            $isUpdate = method_exists($this, 'getId') && $this->getId();

            $request = $this->db->prepare(
                $isUpdate ? $this->update() : $this->insert()
            );

            $return = $request->execute()
                ? ($isUpdate
                    ? $this->getId()
                    : $this->db->lastInsertId()
                )
                : false;

            $this->forDatabase = false;

            return $return;
        } catch (\PDOException $exception) {
            $this->forDatabase = false;

            throw new \PDOException($exception);
        }
    }

    /**
     * @param array $criterias
     * @return array|bool
     */
    public function delete(array $criterias = [])
    {
        try {
            $request = $this->db->prepare(
                sprintf(
                    'DELETE FROM %s %s',
                    $this->getTable(),
                    $this->createConditions($criterias)
                )
            );

            return $request->execute();
        } catch (\PDOException $exception) {
            throw new \PDOException($exception);
        }
    }

    /**
     * @return string
     */
    private function update(): string
    {
        return sprintf(
            'UPDATE %s SET %s WHERE id = %s',
            $this->getTable(),
            $this->serialize($this->getOwnProps(), false),
            $this->getId()
        );
    }

    /**
     * @return string
     */
    private function insert(): string
    {
        return sprintf(
            'INSERT INTO %s SET %s',
            $this->getTable(),
            $this->serialize($this->getOwnProps(), true)
        );
    }

    /**
     * @return array
     */
    private function getOwnProps(): array
    {
        $props              = [];
        $excludedProperties = [];
        $traits             = $this->childClass->getTraits();

        if ($traits) {
            foreach ($traits as $trait) {
                $reflectedTrait = new ReflectionClass($trait->name);

                if (
                    in_array('toExclude', $reflectedTrait->getStaticProperties()) &&
                    $reflectedTrait->getStaticPropertyValue('toExclude') === true
                ) {
                    foreach ($reflectedTrait->getProperties() as $property) {
                        $excludedProperties[$property->name] = $property->name;
                    }
                }
            }
        }

        foreach ($this->childClass->getProperties() as $property) {
            if ($property->class === $this->childClass->name) {
                if (!empty($excludedProperties) && in_array($property->name, $excludedProperties)) {
                    continue;
                }

                $props[] = $property->name;
            }
        }

        return $props;
    }

    /**
     * @param array $props
     * @param bool $isNew
     * @return string
     */
    private function serialize(array $props, bool $isNew = false): string
    {
        $return = [];

        foreach ($props as $prop) {
            if (in_array($prop, ['id', 'updatedAt'])) {
                continue;
            }

            $value = $this->smartFormat($this->{'get' . ucfirst($prop)}());

            if ($isNew && $prop === 'createdAt' && !$value) {
                $value = $this->smartFormat((new DateTime())->format('Y-m-d H:i:s'));
            }

            if (!$value) {
                switch (gettype($this->{'get' . ucfirst($prop)}())) {
                    case 'integer':
                    case 'boolean':
                        break;
                    default:
                        $value = 'NULL';
                        break;
                }
            }

            $return[] = '`' . $prop . '` = ' . $value;
        }

        return implode(',', $return);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function smartFormat($value)
    {
        $return = null;
        $type   = gettype($value);

        switch ($type) {
            case 'boolean':
                $return = $value ? 1 : 0;
                break;
            case 'string':
                $return =  $this->db->quote($value);
                break;
            case 'array':
            case 'object':
                $return =  $this->db->quote(serialize($value));
                break;
            default:
                $return = $value;
                break;
        }

        return $return;
    }

    /**
     * @param array $criterias
     * @param array $clauses
     * @return string
     */
    private function createConditions(array $criterias, array $clauses = []): string
    {
        $condition = 'WHERE 1';

        foreach ($criterias as $key => $value) {
            $fragment = ' AND `' . $key . '` ';

            if (is_array($value)) {
                foreach ($value as $k => $val) {
                    $value[$k] = $this->smartFormat($val);
                }

                $fragment .= 'IN (' . implode(',', $value) . ')';
            } elseif (preg_match('/^(BETWEEN|LIKE|REGEXP)/', $value)) {
                $fragment .= $value;
            } else {
                $fragment .= '= ' . $this->smartFormat($value);
            }

            $condition .= $fragment;
        }

        $condition .= (!empty($clauses['groupBy']) ? ' GROUP BY ' . $clauses['groupBy'] : '') .
            (!empty($clauses['orderBy']) ? ' ORDER BY ' . $clauses['orderBy'] : '') .
            (!empty($clauses['limit']) ? ' LIMIT ' . $clauses['limit'] : '') .
            (!empty($clauses['offset']) ? ' OFFSET ' . $clauses['offset'] : '');

        return $condition;
    }

    /**
     * @param array $row
     * @return mixed
     */
    private function toObject(array $row)
    {
        /** @var static $obj */
        $obj = new $this->childClass->name();
        $obj->setAttributes($row);

        return $obj;
    }
}