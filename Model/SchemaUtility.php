<?php
namespace Lib\Model;

class SchemaUtility extends Model
{
    protected function getIdField() { return '';}

/*
 * Get tables
 */
    public function getTables()
    {
        $sql = "SHOW TABLES";
        if ($stmt = $this->db->query($sql)) {
            $tables = [];
            while ($obj = $stmt->fetch(\PDO::FETCH_OBJ)) {
                $tables[] = $obj->Tables_in_Core;
            }
            return $tables;
        }

        return false;
    }

/*
 * Get PRIMARY key for table
 */
    public function getTablePrimaryKey($table)
    {
        $sql = <<<"SQL"
            SELECT k.COLUMN_NAME
            FROM information_schema.table_constraints t
            LEFT JOIN information_schema.key_column_usage k
            USING(constraint_name,table_schema,table_name)
            WHERE t.constraint_type='PRIMARY KEY'
                AND t.table_schema=DATABASE()
                AND t.table_name='$table'
SQL;
        $stmt = $this->db->query($sql);
        if ($obj = $stmt->fetch(\PDO::FETCH_OBJ)) {
            return $obj->COLUMN_NAME;
        }
        return false;
    }

/*
 *  generate Model class files for schema
 */
    public function generateModels($namespace, $parentClass, $basePath = '', $using = [])
    {
        $tables = $this->getTables();
        foreach ($tables as $table) {
            try {
                $this->generateModelClassFile($table, $namespace, $parentClass, $basePath, $using);
            } catch (\Exception $e) {
                die($e->getMessage());
            }
        }
    }

/*
 * generate a Model class file
 */
    public function generateModelClassFile($tableName, $namespace, $parentClass, $basePath = '', $using = [])
    {
        if (empty($basePath)) {
            $basePath = __DIR__;
        }

        if (!file_exists($basePath)) {
            if (!mkdir($basePath, 0755, true)) {
                throw new \Exception('could not create directory :'.$basePath);
            }
        }

        $filePath = $basePath.'/'.$tableName.'.php';

        $primaryKey = $this->getTablePrimaryKey($tableName);
        if (!$primaryKey) {
            throw new \Exception($tableName.' has no primary key');
        }

        $file = fopen($filePath,'w');
        if (!$file) {
            throw new \Exception('cannot open file for writing');
        }

        fwrite($file, "<?php\n");
        fwrite($file, "\n");
        fwrite($file, "namespace $namespace;\n");
        fwrite($file, "\n");

        foreach ($using as $use) {
            fwrite($file, "use $use;\n");
            if (end($using) == $use) {
                fwrite($file, "\n");
            }
        }

        fwrite($file, "class $tableName extends $parentClass\n");
        fwrite($file, "{\n");
        fwrite($file, "    protected function getIdField()\n");
        fwrite($file, "    {\n");
        fwrite($file, "        return '$primaryKey';\n");
        fwrite($file, "    }\n");
        fwrite($file, "}\n");

        fclose($file);

        return $this;
    }
}
