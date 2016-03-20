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
 * Create DB Table (optional, override in child if needed). Access through setUpDB()
 */
    protected function createTable(){}

/*
 * Accessor for createtable (optional).
 */
    public function setUpDB()
    {
        $this->createTable();
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
 * Get Object's Fields
 *
 */
    public function getFields()
    {
        $refclass = new \ReflectionClass($this);
        foreach ($refclass->getProperties() as $property) {
            if ($property->class == $refclass->name) {
                $name = $property->getName();
                echo "{$name} => {$this->$name}<br>";
            }
        }
    }

/*
 * Set all of this Object's fields NULL
 *
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
 * Set Object's Fields
 *
 */
    public function setFields($fields = array())
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
 * Read an Object
 *
 * get object by field and return it as an associative array
 *
 * returns false on failure
 */
    public function getByField($fieldName = '', $value = '')
    {
        $className = (new \ReflectionClass($this))->getShortName();
        $query = 'SELECT * FROM '.$className.' WHERE %s = :value LIMIT 1';
        $query = sprintf($query, $fieldName);

        $query_params = array(
            ':value' => $value,
        );

        try {
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute($query_params);
        } catch (\PDOException $ex) {
            //die("Failed to run query: " . $ex->getMessage());
        }

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row;
    }

/*
 *  Save an Object (set idObject null to create)
 *
 *  returns id of object or exception
 *
 *  Note: for new object insertions, unset idField
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
            //var_dump($sql);
            //die($ex->getMessage());
        }
    }

/*
 * Delete an Object
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
            try {
                $sql = 'DELETE FROM '.$className.' WHERE ' .$idField. ' = :idObject LIMIT 1';

                $stmt = $this->db->prepare($sql);
                $success = $stmt->execute([':idObject' => $idObject]);
                if ($success) {
                    $this->setFieldsNull();
                }
                return $success;
            } catch (\PDOException $ex) {
                //var_dump($sql);
                //die($ex->getMessage());
            }
        } else {
            return false;
        }
    }

/*
 * Return an Object Count
 *
 */
    public function getCount()
    {
        $query = 'SELECT COUNT(*) FROM '.(new \ReflectionClass($this))->getShortName();

        try {
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute();
        } catch (\PDOException $ex) {
            //die("Failed to run query: " . $ex->getMessage());
        }

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row['COUNT(*)'];
    }

/*
 *  Execute a query
 *
 *  returns resulting rows
 */
    public function query($query = '', $vars = [])
    {
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($vars);
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\PDOException $ex) {}
    }

/*
 * Execute simple query
 *
 *  returns the number of affected rows
 */
    public function execute($query = '')
    {
        try {
            return $this->db->exec($query);
        } catch (\PDOException $ex) {}
    }
}
