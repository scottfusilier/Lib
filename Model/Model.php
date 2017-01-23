<?php
namespace Lib\Model;


interface Model
{
/*
 * Static Convenience Method
 */
    public static function get($idObject);

/*
 * Fetch an object instance
 */
    public function fetchObject($idObject);

/*
 * Fetch an object instance based on field
 */
    public function fetchByField($field, $value);

/*
 *  Save the instance to a peristent storage
 */
    public function save();

/*
 * Remove object from persistent storage
 */
    public function delete($idObject);

/*
 *  Get the object instance fields
 */
    public function getFields();

/*
 *  Set the object instance fields
 */
    public function setFields(array $fields);
}
