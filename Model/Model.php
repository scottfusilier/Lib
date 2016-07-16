<?php
namespace Lib\Model;

use Lib\Container\ConnectionContainer;

abstract class Model
{

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
 * READ db conection configuration name
 *
 * override in child classes for non-default configuration
 */
    protected function getReadConfigName()
    {
        return 'default';
    }

/*
 * WRITE db conection configuration name
 *
 * override in child classes for non-default configuration
 */
    protected function getWriteConfigName()
    {
        return 'default';
    }

/*
 *  Get model table name
 *
 *  defaults to classname, override if needed
 */
    protected function getTableName()
    {
        return (new \ReflectionClass($this))->getShortName();
    }

/*
 * NOTE: Use Model::get() convenience method (set up for chaining, returns instance or false if not found)
 */
    public function __construct() {}

/*
 * get READ PDO connection reference based on config
 */
    protected function getReadPdo()
    {
        $namespace = DBCONFIG_NAMESPACE;
        return ConnectionContainer::getConnection($namespace::getDatabaseConfig($this->getReadConfigName()));
    }

/*
 * get WRITE PDO connection reference based on config
 */
    protected function getWritePdo()
    {
        $namespace = DBCONFIG_NAMESPACE;
        return ConnectionContainer::getConnection($namespace::getDatabaseConfig($this->getWriteConfigName()));
    }

/*
 * Define this Objects fields based on table data
 */
    protected function defineFields()
    {
        $fields = $this->getTableFields();
        if (!$fields) {
            throw new \Exception('no fields');
        }
        foreach ($fields as $field) {
            $this->{$field} = null;
        }
    }

/*
 * Get create table info
 */
    public function getCreateTable()
    {
        if ($stmt = $this->getReadPdo()->query('SHOW CREATE TABLE '.$this->getTableName())) {
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
        $sql = "SHOW COLUMNS FROM {$this->getTableName()}";
        if ($stmt = $this->getReadPdo()->query($sql)) {
            $fields = [];
            while ($obj = $stmt->fetch(\PDO::FETCH_OBJ)) {
                $fields[] = $obj->Field;
            }
            return $fields;
        }

        return false;
    }

/*
 * Get object's fields
 */
    public function getFields()
    {
        $fields = [];
        $refclass = new \ReflectionObject($this);
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
        $refclass = new \ReflectionObject($this);
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
            $this->{$key} = $value;
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
        $query = 'SELECT * FROM '.$this->getTableName().' WHERE %s = :value LIMIT 1';
        $query = sprintf($query, $fieldName);
        $stmt = $this->query($query, [':value' => $value]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

/*
 * Begin a transaction
 */
    public function beginTransaction()
    {
        return $this->getWritePdo()->beginTransaction();
    }

/*
 * Commit a transaction
 *
 * Note: always call after Model::save()
 */
    public function commitTransaction()
    {
        return $this->getWritePdo()->commit();
    }

/*
 * Roll back a transaction
 */
    public function rollBackTransaction()
    {
        return $this->getWritePdo()->rollBack();
    }

/*
 *
 */
    public function inTransaction()
    {
        return $this->getWritePdo()->inTransaction();
    }

/*
 *  Save object in database, new or exisiting
 *
 *  Return trun on success or false on failure
 */
    public function save()
    {
        $idField = $this->getIdField();
        if (!isset($this->$idField)) {
            return $this->saveNewRecord();
        }

        return $this->saveExistingRecord();
    }

/*
 * Save new record
 *
 * NOTE: not meant to use directly, use Model::save() for convenience
 *
 * Return true on success
 */
    private function saveNewRecord()
    {
        $data = array();
        $values = '';
        $refclass = new \ReflectionObject($this);
        $idField = $this->getIdField();
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

        $sql = 'INSERT INTO '.$this->getTableName().' ('.$fields.') VALUES ('.$values.')';
        try {
            $db = $this->getWritePdo();
            $stmt = $db->prepare($sql);
            $success = $stmt->execute($data);
            $lastInsert = $db->lastInsertId();
            if ($lastInsert) {
                //new object, update id
                $this->{$idField} = $lastInsert;
            }

            return $success;
        } catch (\PDOException $ex) {
            //die($ex->getMessage());
        }
    }

/*
 * Update exisiting record
 *
 * NOTE: not meant to use directly, use Model::save() for convenience
 *
 * Return true on success
 */
    private function saveExistingRecord()
    {
        $data = array();
        $values = '';
        $refclass = new \ReflectionObject($this);
        $idField = $this->getIdField();
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

        $sql = 'UPDATE '.$this->getTableName().' SET '.$values.' WHERE '.$idField.' = '.$this->$idField;
        try {
            $stmt = $this->getWritePdo()->prepare($sql);

            return $stmt->execute($data);
        } catch (\PDOException $ex) {
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
        $idField = $this->getIdField();
        $valid = false;
        if (!empty($idObject)) {
            $valid = $this->fetchObject($idObject);
        } elseif (!empty($this->$idField)) {
            $valid = true;
            $idObject = $this->$idField;
        }

        if ($valid) {
            try {
                $sql = 'DELETE FROM '.$this->getTableName().' WHERE ' .$idField. ' = :idObject LIMIT 1';
                $stmt = $this->getWritePdo()->prepare($sql);
                $success = $stmt->execute([':idObject' => $idObject]);
                if ($success) {
                    $this->setFieldsNull();
                }

                return $success;
            } catch (\PDOException $ex) {
                //die($ex->getMessage());
            }
        } else {
            return false;
        }
    }

/*
 *  Execute a read query
 *
 *  Return PDO statement object
 */
    public function readQuery($query = '', $vars = [])
    {
        try {
            $stmt = $this->getReadPdo()->prepare($query);
            $stmt->execute($vars);
        } catch (\PDOException $ex) {
            //die($ex->getMessage());
        }

        return $stmt;
    }

/*
 *  Execute a write query
 *
 *  Return PDO statement object
 */
    public function writeQuery($query = '', $vars = [])
    {
        try {
            $stmt = $this->getWritePdo()->prepare($query);
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
            return $this->getWritePdo()->exec($query);
        } catch (\PDOException $ex) {
            //die($ex->getMessage());
        }
    }

/*
 * Fetch all model objects for given conditions
 *
 * Return an array of model objects
 */
    public function fetchAllObjects(array $where = [], array $vars = [], array $joins = [], array $clauses = [])
    {
        $ref = new \ReflectionClass($this);
        $className = $ref->getShortName();
        $sql = $this->fetchAllQuery($this->getTableName(),$where,$joins,$clauses);
        $stmt = $this->readQuery($sql,$vars);

        return $stmt->fetchAll(\PDO::FETCH_CLASS, $ref->getNamespaceName().'\\'.$className);
    }

/*
 * Fetch all object rows for given conditions
 *
 * Return an array of associative arrays
 */
    public function fetchAllArray(array $where = [], array $vars = [], array $joins = [], array $clauses = [])
    {
        $sql = $this->fetchAllQuery($this->getTableName(),$where,$joins,$clauses);
        $stmt = $this->readQuery($sql,$vars);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

/*
 * Create fetchAll query
 *
 * Return string
 */
    private function fetchAllQuery($tableName = '', array $where = [], array $joins = [], array $clauses = [])
    {
        $sql = 'SELECT Obj.* FROM '.$tableName.' Obj ';

        if (!empty($joins)) {
            $sql .= implode(' ', $joins);
        }

        if (!empty($where)) {
            $sql .= ' WHERE '.implode(' AND ',$where);
        }

        if (!empty($clauses)) {
            $sql .= ' '.implode(' ', $clauses);
        }

        return $sql;
    }
}
