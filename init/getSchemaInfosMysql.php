<?php
namespace jemdev\dbrm\init;
use jemdev\dbrm\init\getSchemaInfos;
use jemdev\dbrm\vue;
use jemdev\dbrm\cache\selectTablesNames;

/**
 * @author Cyrano
 * Classe de collecte d'informations sur un schéma de données
 *
 * Version MySQL qu collectera ces données dans le schéma INFORMATION_SCHEMA
 *
 */
class getSchemaInfosMysql implements getSchemaInfos
{
    /**
     * Instance de la classe jemdev\dbrm\vue qui permettra d'Exécuter les requêtes
     * qui collecteront les informations.
     *
     * @var jemdev\dbrm\vue
     */
    private $_oVue;
    private $_schemacible;
    private $_metaschema    = 'INFORMATION_SCHEMA';
    private $_type_pk       = 'PRI';
    private $_type_view     = 'VIEW';
    /**
     * @var jemdev\dbrm\cache\selectTablesNames
     */
    private $_oSelectTablesName;

    /**
     *
     */
    public function __construct(vue $oVue, $schemaCible)
    {
        $this->_oVue = $oVue;
        $this->_schemacible = $schemaCible;
    }

    /**
   *(non-PHPdoc)
    * @see jemdev\dbrm\init\getSchemaInfos::_getInfosColonnes()
*/
    public function getInfosColonnes($table)
    {
        $sql  = "SELECT ".
                "  `COLUMN_NAME`, ".
                "  `ORDINAL_POSITION`, ".
                "  `COLUMN_DEFAULT`, ".
                "  `IS_NULLABLE`, ".
                "  `DATA_TYPE`, ".
                "  `CHARACTER_MAXIMUM_LENGTH`, ".
                "  `NUMERIC_PRECISION`, ".
                "  `NUMERIC_SCALE`, ".
                "  `CHARACTER_SET_NAME`, ".
                "  `COLLATION_NAME`, ".
                "  `COLUMN_TYPE`, ".
                "  `COLUMN_KEY`, ".
                "  `EXTRA`, ".
                "  `COLUMN_COMMENT` ".
                "FROM ". $this->_metaschema .".COLUMNS ".
                "WHERE TABLE_SCHEMA = :P_TABLE_SCHEMA ".
                "  AND TABLE_NAME   = :P_TABLE_NAME";
        $params = array(
            ':P_TABLE_SCHEMA' => $this->_schemacible,
            ':P_TABLE_NAME'   => $table
        );
        $this->_oVue->setRequete($sql, $params);
        $aVues = $this->_oVue->fetchAssoc();
        return $aVues;
    }

    /**
   *(non-PHPdoc)
    * @see jemdev\dbrm\init\getSchemaInfos::_getRelations()
*/
    public function getRelations()
    {
        $sql  = "SELECT DISTINCT(c.TABLE_NAME) ".
                "FROM ". $this->_metaschema .".COLUMNS c ".
                "WHERE c.COLUMN_KEY = :P_COLUMN_KEY ".
                "  AND TABLE_SCHEMA = :P_TABLE_SCHEMA ".
                "GROUP BY c.TABLE_NAME ".
                "HAVING COUNT(c.TABLE_NAME) > 1";
        $params = array(
            ':P_TABLE_SCHEMA' => $this->_schemacible,
            ':P_COLUMN_KEY'   => $this->_type_pk
        );
        $this->_oVue->setRequete($sql, $params);
        $aRelations = $this->_oVue->fetchAssoc();
        return $aRelations;
    }

    /**
   *(non-PHPdoc)
    * @see jemdev\dbrm\init\getSchemaInfos::_getReferenceFK()
*/
    public function getReferenceFK($table, $colonne)
    {
        $sql  = "SELECT ".
                "    REFERENCED_TABLE_NAME, ".
                "    REFERENCED_COLUMN_NAME ".
                "FROM ". $this->_metaschema .".KEY_COLUMN_USAGE ".
                "WHERE CONSTRAINT_SCHEMA = :P_CONSTRAINT_SCHEMA ".
                "  AND TABLE_NAME = :P_TABLE_NAME ".
                "  AND COLUMN_NAME = :P_COLUMN_NAME";
        $params = array(
            ':P_CONSTRAINT_SCHEMA' => $this->_schemacible,
            ':P_TABLE_NAME'  => $table,
            ':P_COLUMN_NAME' => $colonne
        );
        $this->_oVue->setRequete($sql, $params);
        $aRef = $this->_oVue->fetchLine();
        return $aRef;
    }

    /**
   *(non-PHPdoc)
    * @see jemdev\dbrm\init\getSchemaInfos::_getConstraints()
*/
    public function getConstraints()
    {
        $sql  = "SELECT ".
                "  CONSTRAINT_NAME, ".
                "  TABLE_NAME, ".
                "  COLUMN_NAME, ".
                "  REFERENCED_TABLE_NAME, ".
                "  REFERENCED_COLUMN_NAME ".
                "FROM ". $this->_metaschema .".KEY_COLUMN_USAGE ".
                "WHERE TABLE_SCHEMA = :P_TABLE_SCHEMA ".
                "  AND CONSTRAINT_NAME <> :P_CONSTRAINT_NAME ".
                "  AND REFERENCED_TABLE_NAME IS NOT NULL";
        $params = array(
            ':P_TABLE_SCHEMA' => $this->_schemacible,
            ':P_CONSTRAINT_NAME' => 'PRIMARY'
        );
        $this->_oVue->setRequete($sql, $params);
        $aConstraints = $this->_oVue->fetchAssoc();
        return $aConstraints;
    }

    /**
   *(non-PHPdoc)
    * @see jemdev\dbrm\init\getSchemaInfos::_getTables()
*/
    public function getTables()
    {
        $sql  = "SELECT DISTINCT(c.TABLE_NAME) ".
                "FROM ". $this->_metaschema .".COLUMNS c ".
                "WHERE c.COLUMN_KEY = :P_COLUMN_KEY ".
                "  AND TABLE_SCHEMA = :P_TABLE_SCHEMA ".
                "GROUP BY c.TABLE_NAME ".
                "HAVING COUNT(c.TABLE_NAME) < 2";
        $params = array(
            ':P_TABLE_SCHEMA' => $this->_schemacible,
            ':P_COLUMN_KEY'   => $this->_type_pk
        );
        $this->_oVue->setRequete($sql, $params);
        $aTables = $this->_oVue->fetchAssoc();
        return $aTables;
    }

    /**
     * (non-PHPdoc)
     * @see jemdev\dbrm\init\getSchemaInfos::_getVues()
     */
    public function getVues()
    {
        $sql  = "SELECT ".
                "    TABLE_NAME ".
                "FROM ". $this->_metaschema .".TABLES ".
                "WHERE TABLE_SCHEMA = :P_TABLE_SCHEMA ".
                "  AND TABLE_TYPE = :P_TABLE_TYPE";
        $params = array(
            ':P_TABLE_SCHEMA' => $this->_schemacible,
            ':P_TABLE_TYPE'   => $this->_type_view
        );
        $this->_oVue->setRequete($sql, $params);
        $aVues = $this->_oVue->fetchAssoc();
        return $aVues;
    }

    /**
     *(non-PHPdoc)
     * @see jemdev\dbrm\init\getSchemaInfos::_getReferencesFK()
     */
    public function getReferencesFK($table)
    {
        $sql  = "SELECT ".
                "    COLUMN_NAME, ".
                "    REFERENCED_TABLE_NAME, ".
                "    REFERENCED_COLUMN_NAME ".
                "FROM ". $this->_metaschema .".KEY_COLUMN_USAGE ".
                "WHERE CONSTRAINT_SCHEMA = :P_CONSTRAINT_SCHEMA ".
                "  AND TABLE_NAME = :P_TABLE_NAME ".
                "  AND REFERENCED_TABLE_NAME IS NOT NULL";
        $params = array(
            ':P_CONSTRAINT_SCHEMA' => $this->_schemacible,
            ':P_TABLE_NAME'  => $table
        );
        $this->_oVue->setRequete($sql, $params);
        $aRef = $this->_oVue->fetchAssoc();
        return $aRef;
    }

    public function getViewTables($viewName)
    {
        $sql  = "SELECT ".
                "  view_definition ".
                "FROM ". $this->_metaschema .".VIEWS ".
                "WHERE table_schema = :p_table_schema ".
                "  AND table_name   = :p_view_name";
        $params = array(
            ':p_table_schema' => $this->_schemacible,
            ':p_view_name'    => $viewName
        );
        $this->_oVue->setRequete($sql, $params);
        $definition = $this->_oVue->fetchOne();
        if(is_null($this->_oSelectTablesName))
        {
            $this->_oSelectTablesName = new selectTablesNames();
        }
        $this->_oSelectTablesName->setNewQuery($definition);
        $aTables = $this->_oSelectTablesName->getTables(true);
        return($aTables);
    }

}

?>