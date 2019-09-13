<?php

namespace Did\Database;

use PDO;
use ReflectionClass;
use Did\Tools\StringTool;

/**
 * Class SmartConnector
 *
 * @package Did\Database
 * @author (c) Julien Bernard <hello@julien-bernard.com>
 *
 * @method getTable()
 * @method getId()
 */
class SmartConnector extends AbstractConnection
{
    protected $childClass;

    public function __construct()
    {
        parent::connect();

        $this->childClass = new ReflectionClass($this);
    }

    /**
     * @param string $classname
     * @return static
     */
    public static function model($classname)
    {
        $classname = $_SESSION['defaultNamespaceForRoutingConfig'] . '\Entity\\' . $classname;

        return new $classname();
    }

    /**
     * @param array $datas
     * @return $this
     */
    public function setAttributes(array $datas)
    {
        foreach($datas as $field => $data) {
            $this->{'set' . ucfirst($field)}(StringTool::sanitize($data));
        }

        return $this;
    }

    /**
     * @param array $criterias
     * @return null|$this
     */
    public function find(array $criterias = [])
    {
        $return  = null;
        $request = $this->db->prepare(
            sprintf(
                'SELECT %s FROM %s %s',
                '*',
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

    public function save()
    {
        try {
            $request = $this->db->prepare(
                $this->getId() ? $this->update() : $this->insert()
            );

            return $request->execute();
        } catch (\PDOException $exception) {
            throw new \PDOException($exception);
        }
    }

    private function update()
    {
        //@TODO
    }

    private function insert()
    {
        return sprintf(
            'INSERT INTO %s SET %s',
            $this->getTable(),
            $this->serialize($this->getOwnProps())
        );
    }

    /**
     * @return array
     */
    private function getOwnProps()
    {
        $props = [];

        foreach ($this->childClass->getProperties() as $property) {
            if ($property->class === $this->childClass->name) {
                $props[] = $property->name;
            }
        }

        return $props;
    }

    /**
     * @param array $props
     * @return string
     */
    private function serialize(array $props): string
    {
        $return = [];

        foreach ($props as $prop) {
            if ($prop === 'id') {
                continue;
            }

            $value = $this->smartFormat($this->{'get' . ucfirst($prop)}());

            $return[] = '`' . $prop . '` = ' . ($value ?: 'NULL');
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
                $return =  '"' . $value . '"';
                break;
            case 'array':
            case 'object':
                $return = serialize($value);
                break;
            default:
                $return = $value;
                break;
        }

        return $return;
    }

    /**
     * @param array $criterias
     * @return string
     */
    private function createConditions(array $criterias)
    {
        $condition = 'WHERE 1';

        foreach ($criterias as $key => $value) {
            $fragment = ' AND `' . $key . '` ';

            if (is_array($value)) {
                foreach ($value as $k => $val) {
                    $value[$k] = $this->smartFormat($val);
                }

                $fragment .= 'IN (' . implode(',', $value) . ')';
            } else {
                $fragment .= '= ' . $this->smartFormat($value);
            }

            $condition .= $fragment;
        }

        return $condition;
    }

    /**
     * @param array $row
     * @return $this
     */
    private function toObject(array $row)
    {
        /** @var static $obj */
        $obj = new $this->childClass->name();
        $obj->setAttributes($row);

        return $obj;
    }
}