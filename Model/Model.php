<?php
namespace Lib\Model;

use Lib\Container\ConnectionContainer;

abstract class Model
{
/*
 * db conection configuration -- redefine this in child classes (ex: non-default db connection)
 */
    protected $config = ['namespace' => DBCONFIG_NAMESPACE,'config' => 'default'];

/*
 * db connection
 */
    protected $db;

/*
 * Return the id field for this object
 */
    abstract protected function getIdField();

/*
 * Static Convenience Method, get.
 *
 * (set up for chaining, returns instance or false if not found)
 *
 * examples:
 *
 * $obj = Model::get(); //returns instance
 * $obj = Model::get(134); // returns instance or false id not found
 * $obj = Model::get()->fetchByField('Field','value'); // returns instance or false if not found
 *
 * $result = Model::get()->select('SELECT * FROM ...');
 */
    public static function get($idObject = null)
    {
        if ($idObject) {
            return (new static())->fetchObject($idObject);
        }
        return new static();
    }

/*
 * NOTE: Use Model::get() convenience method (set up for chaining, returns instance or false if not found)
 */
    public function __construct()
    {
        $this->setConnection();
    }

/*
 * Set db connection reference based on config
 */
    private function setConnection()
    {
        $nameSpace = $this->config['namespace'];
        $this->db = ConnectionContainer::getConnection($nameSpace::getDatabaseConfig($this->config['config']));
    }

/*
 * Get create table info
 */
    public function getCreateTable()
    {
        $className = (new \ReflectionClass($this))->getShortName();
        if ($stmt = $this->db->query('SHOW CREATE TABLE '.$className)) {
            $obj = $stmt->fetch(\PDO::FETCH_OBJ);
            if (isset($obj->{'Create Table'})) {
                return $obj->{'Create Table'};
            }
        }

        return false;
    }

/*
 * Get table fields
 */
    public function getTableFields()
    {
        $className = (new \ReflectionClass($this))->getShortName();
        $sql = "SHOW COLUMNS FROM $className";
        if ($stmt = $this->db->query($sql)) {
            $fields = [];
            while ($obj = $stmt->fetch(\PDO::FETCH_OBJ)) {
                $fields[] = $obj->Field;
            }
            return $fields;
        }

        return false;
    }

/*
 * Get model fields
 */
    public function getModelFields()
    {
        $fields = [];
        $refclass = new \ReflectionClass($this);
        foreach ($refclass->getProperties() as $property) {
            if ($property->class == $refclass->name) {
                $name = $property->getName();
                $fields[] = $name;
            }
        }

        return $fields;
    }

/*
 * Get object's fields
 */
    public function getFields()
    {
        $fields = [];
        $refclass = new \ReflectionClass($this);
        foreach ($refclass->getProperties() as $property) {
            if ($property->class == $refclass->name) {
                $name = $property->getName();
                $fields[$name] = $this->$name;
            }
        }

        return $fields;
    }

/*
 * Set object's fields NULL
 */
    public function setFieldsNull()
    {
        $refclass = new \ReflectionClass($this);
        foreach ($refclass->getProperties() as $property) {
            if ($property->class == $refclass->name) {
                $name = $property->getName();
                $this->$name = NULL;
            }
        }
    }

/*
 * Set object's fields
 */
    public function setFields(array $fields = [])
    {
        foreach ($fields as $key => $value) {
            $this->$key = $value;
        }
    }

/*
 * Fetch an object by id and populate this object's fields
 */
    public function fetchObject($idObject = 0) {
        $fields = $this->getByField($this->getIdField(), $idObject);
        if ($fields) {
            $this->setFields($fields);
            return $this;
        } else {
            return false;
        }
    }

/*
 * Fetch first object by a field and populate this object's fields
 */
    public function fetchByField($field = '', $value = '') {
        $fields = $this->getByField($field, $value);
        if ($fields) {
            $this->setFields($fields);
            return $this;
        } else {
            return false;
        }
    }

/*
 * Read an object by field
 *
 * Return associative array of object fields or false on failure
 */
    public function getByField($fieldName = '', $value = '')
    {
        $className = (new \ReflectionClass($this))->getShortName();
        $query = 'SELECT * FROM '.$className.' WHERE %s = :value LIMIT 1';
        $query = sprintf($query, $fieldName);
        $stmt = $this->query($query, [':value' => $value]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

/*
 *  Save object in database
 *
 *  Return object's id on success or false on failure
 */
    public function save()
    {
        $data = array();
        $values = '';
        $refclass = new \ReflectionClass($this);
        $idField = $this->getIdField();

        if (!isset($this->$idField)) {
            //new object insert
            $fields = '';
            foreach ($refclass->getProperties() as $property) {
                if ($property->class == $refclass->name) {
                    $name = $property->name;
                    if ($this->$name && $name != $idField) {
                        $values .= ':'.$name.', ';
                        $data[':'.$name] = $this->$name;
                        $fields .= $name.', ';
                    }
                }
            }
            $values = rtrim($values, ', ');
            $fields = rtrim($fields, ', ');

            $sql = 'INSERT INTO '.$refclass->getShortName().' ('.$fields.') VALUES ('.$values.')';
        } else {
            //existing object update
            foreach ($refclass->getProperties() as $property) {
                if ($property->class == $refclass->name) { 
                    $name = $property->name;
                    if ($this->$name && $name != $idField) {
                        $values .= $name.' = :'.$name.', ';
                        $data[':'.$name] = $this->$name;
                    }
                }
            }
            $values = rtrim($values, ', ');

            $sql = 'UPDATE '.$refclass->getShortName().' SET '.$values.' WHERE '.$idField.' = '.$this->$idField;
        }
        try {
            $stmt = $this->db->prepare($sql);
            $this->db->beginTransaction();
            $success = $stmt->execute($data);
            $lastInsert = $this->db->lastInsertId();
            $this->db->commit();
            if ($lastInsert) {
                //new object, update id
                $this->$idField = $lastInsert;
            }
            return $success;
        } catch (\PDOException $ex) {
            $this->db->rollBack();
            //die($ex->getMessage());
        }
    }

/*
 * Delete object from database
 *
 * Return false if Object does not exist, nulls object's fields on delete and returns true to indicate success
 */
    public function delete($idObject = '')
    {
        $className = (new \ReflectionClass($this))->getShortName();
        $idField = $this->getIdField();

        $valid = false;
        if (!empty($idObject)) {
            $valid = $this->fetchObject($idObject);
        } elseif (!empty($this->$idField)) {
            $valid = true;
            $idObject = $this->$idField;
        }

        if ($valid) {
            $sql = 'DELETE FROM '.$className.' WHERE ' .$idField. ' = :idObject LIMIT 1';

            $success = $this->query($sql, [':idObject' => $idObject]);
            if ($success) {
                $this->setFieldsNull();
            }

            return $success;
        } else {
            return false;
        }
    }

/*
 * Return object count
 *
 */
    public function getCount()
    {
        $query = 'SELECT COUNT(*) FROM '.(new \ReflectionClass($this))->getShortName();
        $stmt = $this->query($query);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row['COUNT(*)'];
    }

/*
 *  Execute a query
 *
 *  Return PDO statement object
 */
    public function query($query = '', $vars = [])
    {
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($vars);
        } catch (\PDOException $ex) {
            //die($ex->getMessage());
        }

        return $stmt;
    }

/*
 * Execute simple query
 *
 * Return the number of affected rows
 */
    public function execute($query = '')
    {
        try {
            return $this->db->exec($query);
        } catch (\PDOException $ex) {
            //die($ex->getMessage());
        }
    }

/*
 * Fetch all model objects for given conditions
 *
 * Return an array of model objects
 */
    public function fetchAll(array $where = [], array $vars = [], array $joins = [])
    {
        $ref = new \ReflectionClass($this);
        $className = $ref->getShortName();
        $sql = 'SELECT Obj.* FROM '.$className.' Obj ';

        if (!empty($joins)) {
            $sql .= implode(' ', $joins);
        }

        if (!empty($where)) {
            $sql .= ' WHERE '.implode(' AND ',$where);
        }

        $stmt = $this->query($sql,$vars);

        return $stmt->fetchAll(\PDO::FETCH_CLASS, $ref->getNamespaceName().'\\'.$className);
    }
}
