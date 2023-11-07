<?php
namespace jemdev\dbrm\init;
use jemdev\dbrm\vue;
use jemdev\dbrm\cache\selectTablesNames;

/**
 * Classe de collecte d'informations sur un schéma de données
 *
 * Version MySQL qu collectera ces données dans le schéma INFORMATION_SCHEMA
 *
 * @author      Jean Molliné <jmolline@jem-dev.com>
 *
 */
class getSchemaInfosPgsql implements getSchemaInfosInterface
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
    private $_type_pk       = 'PRIMARY KEY';
    private $_type_fk       = 'FOREIGN KEY';
    private $_type_view     = 'VIEW';
    private $_aConstraint   = array();
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
      * @see jemdev\dbrm\init\getSchemaInfosInterface::_getInfosColonnes()
         */
    public function getInfosColonnes(string $table): array
    {
        $sql  = "SELECT". PHP_EOL .
                "  cl.ordinal_position,". PHP_EOL .
                "  cl.column_name,". PHP_EOL .
                "  cl.udt_name               AS data_type,". PHP_EOL .
                "  CASE". PHP_EOL .
                "    WHEN tc.constraint_type  = :P_COLUMN_KEY THEN NULL". PHP_EOL .
                "    WHEN cl.is_nullable  = 'YES' THEN NULL". PHP_EOL .
                "    ELSE cl.column_default". PHP_EOL .
                "  END                       AS column_default,". PHP_EOL .
                "  cl.is_nullable,". PHP_EOL .
                "  cl.character_maximum_length,". PHP_EOL .
                "  cl.numeric_precision,". PHP_EOL .
                "  cl.numeric_scale,". PHP_EOL .
                "  cl.character_set_name,". PHP_EOL .
                "  cl.collation_name,". PHP_EOL .
                "  NULL                      AS column_type,". PHP_EOL .
                "  CASE". PHP_EOL .
                "    WHEN tc.constraint_type  = :P_COLUMN_KEY THEN :P_COLUMN_KEY". PHP_EOL .
                "    ELSE NULL". PHP_EOL .
                "  END                       AS column_key,". PHP_EOL .
                "  NULL                      AS extra,". PHP_EOL .
                "  NULL                      AS column_comment". PHP_EOL .
                "FROM INFORMATION_SCHEMA.columns cl". PHP_EOL .
                "  LEFT JOIN INFORMATION_SCHEMA.key_column_usage         kc ON cl.table_catalog              = kc.constraint_catalog". PHP_EOL .
                "                                                          AND cl.table_schema               = kc.constraint_schema". PHP_EOL .
                "                                                          AND cl.table_name                 = kc.table_name". PHP_EOL .
                "                                                          AND cl.column_name                = kc.column_name". PHP_EOL .
                "                                                          AND kc.position_in_unique_constraint IS NULL". PHP_EOL .
                "  LEFT JOIN INFORMATION_SCHEMA.table_constraints        tc ON kc.constraint_catalog         = tc.constraint_catalog". PHP_EOL .
                "                                                          AND kc.constraint_schema          = tc.constraint_schema". PHP_EOL .
                "                                                          AND kc.constraint_name            = tc.constraint_name". PHP_EOL .
                "                                                          AND tc.constraint_type            = :P_COLUMN_KEY". PHP_EOL .
                "  LEFT JOIN INFORMATION_SCHEMA.referential_constraints  rc ON tc.constraint_catalog         = rc.constraint_catalog". PHP_EOL .
                "                                                          AND tc.constraint_schema          = rc.constraint_schema". PHP_EOL .
                "                                                          AND tc.constraint_name            = rc.constraint_name". PHP_EOL .
                "  LEFT JOIN INFORMATION_SCHEMA.constraint_column_usage  cc ON rc.unique_constraint_catalog  = cc.constraint_catalog". PHP_EOL .
                "                                                          AND rc.unique_constraint_schema   = cc.constraint_schema". PHP_EOL .
                "                                                          AND rc.unique_constraint_name     = cc.constraint_name". PHP_EOL .
                "WHERE cl.table_name     = :P_TABLE_NAME". PHP_EOL .
                "  AND cl.table_catalog  = :P_TABLE_SCHEMA". PHP_EOL .
                "ORDER BY cl.ordinal_position";
        $params = array(
            ':P_COLUMN_KEY'   => $this->_type_pk,
            ':P_TABLE_SCHEMA' => $this->_schemacible,
            ':P_TABLE_NAME'   => $table
        );
        $this->_oVue->setRequete($sql, $params);
        $aColonnes = $this->_oVue->fetchAssoc();
        $aConstraints = $this->getConstraints();
        foreach($aColonnes as $c => $colonne)
        {
            foreach($aConstraints as $constraint)
            {
                if($constraint['table_name'] == $table && $constraint['column_name'] == $colonne['column_name'] && empty($colonne['column_key']))
                {
                    $aColonnes[$c]['column_key'] = $constraint['constraint_type'];
                }
            }
        }
        $sequencePk = $this->_getInfosSequence($table);
        if(false !== $sequencePk)
        {
            foreach($aColonnes as $c => $col)
            {
                if($col['column_key'] == $this->_type_pk)
                {
                    $aColonnes[$c]['extra'] = $sequencePk;
                    $sequencePk = null;
                    break;
                }
            }
        }
        return $aColonnes;
    }

    /**
     * (non-PHPdoc)
     * @see jemdev\dbrm\init\getSchemaInfosInterface::_getRelations()
     */
    public function getRelations(): array
    {
        $sql  = "SELECT tc.table_name". PHP_EOL .
                "FROM ". $this->_metaschema .".table_constraints             tc". PHP_EOL .
                "  LEFT JOIN ". $this->_metaschema .".key_column_usage       kc   ON tc.constraint_catalog         = kc.constraint_catalog". PHP_EOL .
                "                                                          AND tc.constraint_schema          = kc.constraint_schema". PHP_EOL .
                "                                                          AND tc.constraint_name            = kc.constraint_name". PHP_EOL .
                "  LEFT JOIN ". $this->_metaschema .".referential_constraints  rc   ON tc.constraint_catalog         = rc.constraint_catalog". PHP_EOL .
                "                                                          AND tc.constraint_schema          = rc.constraint_schema". PHP_EOL .
                "                                                          AND tc.constraint_name            = rc.constraint_name". PHP_EOL .
                "  LEFT JOIN ". $this->_metaschema .".constraint_column_usage  cc   ON rc.unique_constraint_catalog  = cc.constraint_catalog". PHP_EOL .
                "                                                          AND rc.unique_constraint_schema   = cc.constraint_schema". PHP_EOL .
                "                                                          AND rc.unique_constraint_name     = cc.constraint_name". PHP_EOL .
                "WHERE tc.constraint_type = :P_COLUMN_KEY". PHP_EOL .
                "  AND tc.table_catalog = :P_TABLE_SCHEMA". PHP_EOL .
                "GROUP BY tc.table_name". PHP_EOL .
                "HAVING COUNT(kc.column_name) > 1";
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
     * @see jemdev\dbrm\init\getSchemaInfosInterface::_getReferenceFK()
     */
    public function getReferenceFK(string $table, string $colonne): array
    {
        $sql  = "SELECT". PHP_EOL .
                "  cc.table_name         AS REFERENCED_TABLE_NAME,". PHP_EOL .
                "  cc.column_name        AS REFERENCED_COLUMN_NAME". PHP_EOL .
                "FROM ". $this->_metaschema .".table_constraints               tc". PHP_EOL .
                "  LEFT JOIN ". $this->_metaschema .".key_column_usage         kc ON tc.constraint_catalog = kc.constraint_catalog". PHP_EOL .
                "                                                          AND tc.constraint_schema = kc.constraint_schema". PHP_EOL .
                "                                                          AND tc.constraint_name = kc.constraint_name". PHP_EOL .
                "  LEFT JOIN ". $this->_metaschema .".referential_constraints  rc ON tc.constraint_catalog = rc.constraint_catalog". PHP_EOL .
                "                                                          AND tc.constraint_schema = rc.constraint_schema". PHP_EOL .
                "                                                          AND tc.constraint_name = rc.constraint_name". PHP_EOL .
                "  LEFT JOIN ". $this->_metaschema .".constraint_column_usage  cc ON rc.unique_constraint_catalog = cc.constraint_catalog". PHP_EOL .
                "                                                          AND rc.unique_constraint_schema = cc.constraint_schema". PHP_EOL .
                "                                                          AND rc.unique_constraint_name = cc.constraint_name". PHP_EOL .
                "WHERE tc.table_name       = :P_TABLE_NAME". PHP_EOL .
                "  AND tc.table_catalog    = :P_CONSTRAINT_SCHEMA". PHP_EOL .
                "  AND kc.column_name      = :P_COLUMN_NAME". PHP_EOL .
                "  AND tc.constraint_type  = 'FOREIGN KEY'";
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
     * @see jemdev\dbrm\init\getSchemaInfosInterface::_getConstraints()
     */
    public function getConstraints(): array
    {
        if(count($this->_aConstraint) == 0)
        {
            $sql  = "SELECT". PHP_EOL .
                    "  kc.constraint_name,". PHP_EOL .
                    "  kc.table_name,". PHP_EOL .
                    "  kc.column_name        AS column_name,". PHP_EOL .
                    "  tc.constraint_type,". PHP_EOL .
                    "  cc.table_name         AS referenced_table_name,". PHP_EOL .
                    "  cc.column_name        AS referenced_column_name". PHP_EOL .
                    "FROM information_schema.table_constraints               tc". PHP_EOL .
                    "  LEFT JOIN information_schema.key_column_usage         kc ON tc.constraint_catalog         = kc.constraint_catalog". PHP_EOL .
                    "                                                          AND tc.constraint_schema          = kc.constraint_schema". PHP_EOL .
                    "                                                          AND tc.constraint_name            = kc.constraint_name". PHP_EOL .
                    "  LEFT JOIN information_schema.referential_constraints  rc ON tc.constraint_catalog         = rc.constraint_catalog". PHP_EOL .
                    "                                                          AND tc.constraint_schema          = rc.constraint_schema". PHP_EOL .
                    "                                                          AND tc.constraint_name            = rc.constraint_name". PHP_EOL .
                    "  LEFT JOIN information_schema.constraint_column_usage  cc ON rc.unique_constraint_catalog  = cc.constraint_catalog". PHP_EOL .
                    "                                                          AND rc.unique_constraint_schema   = cc.constraint_schema". PHP_EOL .
                    "                                                          AND rc.unique_constraint_name     = cc.constraint_name". PHP_EOL .
                    "WHERE tc.table_catalog    = :P_CONSTRAINT_SCHEMA". PHP_EOL .
                    "  AND tc.constraint_type <> 'CHECK'". PHP_EOL .
                    "ORDER BY kc.table_name";
            $params = array(
                ':P_CONSTRAINT_SCHEMA'  => $this->_schemacible
            );
            $this->_oVue->setRequete($sql, $params);
            $this->_aConstraint = $this->_oVue->fetchAssoc();
        }
        return $this->_aConstraint;
    }

    /**
     * (non-PHPdoc)
     * @see jemdev\dbrm\init\getSchemaInfosInterface::_getTables()
     */
    public function getTables(): array
    {
        $sql  = "SELECT tc.table_name". PHP_EOL .
                "FROM ". $this->_metaschema .".table_constraints             tc". PHP_EOL .
                "  LEFT JOIN ". $this->_metaschema .".key_column_usage       kc   ON tc.constraint_catalog         = kc.constraint_catalog". PHP_EOL .
                "                                                          AND tc.constraint_schema          = kc.constraint_schema". PHP_EOL .
                "                                                          AND tc.constraint_name            = kc.constraint_name". PHP_EOL .
                "LEFT JOIN ". $this->_metaschema .".referential_constraints  rc   ON tc.constraint_catalog         = rc.constraint_catalog". PHP_EOL .
                "                                                          AND tc.constraint_schema          = rc.constraint_schema". PHP_EOL .
                "                                                          AND tc.constraint_name            = rc.constraint_name". PHP_EOL .
                "LEFT JOIN ". $this->_metaschema .".constraint_column_usage  cc   ON rc.unique_constraint_catalog  = cc.constraint_catalog". PHP_EOL .
                "                                                          AND rc.unique_constraint_schema   = cc.constraint_schema". PHP_EOL .
                "                                                          AND rc.unique_constraint_name     = cc.constraint_name". PHP_EOL .
                "WHERE tc.constraint_type = :P_COLUMN_KEY". PHP_EOL .
                "  AND tc.table_catalog = :P_TABLE_SCHEMA". PHP_EOL .
                "GROUP BY tc.table_name". PHP_EOL .
                "HAVING COUNT(kc.column_name) < 2";
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
     * @see jemdev\dbrm\init\getSchemaInfosInterface::_getVues()
     */
    public function getVues(): array
    {
        $sql  = "SELECT table_name". PHP_EOL .
                "FROM ". $this->_metaschema .".views". PHP_EOL .
                "WHERE table_catalog = :P_TABLE_SCHEMA". PHP_EOL .
                "  AND table_schema NOT IN('pg_catalog', 'information_schema')". PHP_EOL .
                "  AND table_name !~ '^pg_'";
        $params = array(
            ':P_TABLE_SCHEMA' => $this->_schemacible
        );
        $this->_oVue->setRequete($sql, $params);
        $aVues = $this->_oVue->fetchAssoc();
        return $aVues;
    }

    /**
     *(non-PHPdoc)
     * @see jemdev\dbrm\init\getSchemaInfosInterface::_getReferencesFK()
     */
    public function getReferencesFK($table): array
    {
        $sql  = "SELECT". PHP_EOL .
                "  kc.column_name        AS COLUMN_NAME,". PHP_EOL .
                "  cc.table_name         AS REFERENCED_TABLE_NAME,". PHP_EOL .
                "  cc.column_name        AS REFERENCED_COLUMN_NAME". PHP_EOL .
                "FROM ". $this->_metaschema .".table_constraints               tc". PHP_EOL .
                "  LEFT JOIN ". $this->_metaschema .".key_column_usage         kc ON tc.constraint_catalog = kc.constraint_catalog". PHP_EOL .
                "                                                          AND tc.constraint_schema = kc.constraint_schema". PHP_EOL .
                "                                                          AND tc.constraint_name = kc.constraint_name". PHP_EOL .
                "  LEFT JOIN ". $this->_metaschema .".referential_constraints  rc ON tc.constraint_catalog = rc.constraint_catalog". PHP_EOL .
                "                                                          AND tc.constraint_schema = rc.constraint_schema". PHP_EOL .
                "                                                          AND tc.constraint_name = rc.constraint_name". PHP_EOL .
                "  LEFT JOIN ". $this->_metaschema .".constraint_column_usage  cc ON rc.unique_constraint_catalog = cc.constraint_catalog". PHP_EOL .
                "                                                          AND rc.unique_constraint_schema = cc.constraint_schema". PHP_EOL .
                "                                                          AND rc.unique_constraint_name = cc.constraint_name". PHP_EOL .
                "WHERE tc.table_name       = :P_TABLE_NAME". PHP_EOL .
                "  AND tc.table_catalog    = :P_CONSTRAINT_SCHEMA". PHP_EOL .
                "  AND tc.constraint_type  = :P_TYPE_FK";
        $params = array(
            ':P_CONSTRAINT_SCHEMA'  => $this->_schemacible,
            ':P_TABLE_NAME'         => $table,
            ':P_TYPE_FK'            => $this->_type_fk
        );
        $this->_oVue->setRequete($sql, $params);
        $aRef = $this->_oVue->fetchAssoc();
        return $aRef;
    }

    public function getViewTables(string $viewName): array
    {
        $sql  = "SELECT ".
                "  view_definition ".
                "FROM ". $this->_metaschema .".VIEWS ".
                "WHERE table_schema = :p_table_schema ".
                "  AND table_name   = :p_view_name";
        $sql  = "SELECT". PHP_EOL .
                "  view_definition". PHP_EOL .
                "FROM ". $this->_metaschema .".views". PHP_EOL .
                "WHERE table_c = :p_table_schema ".
                "  AND table_name = :p_view_name";
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
        $aVueDefinition = $this->_oSelectTablesName->getTables(true);
        return($aVueDefinition);
    }

    private function _getInfosSequence(string $table): array
    {
        $sql  = "SELECT sequence_name". PHP_EOL .
                "  FROM information_schema.sequences". PHP_EOL .
                "WHERE sequence_catalog = :p_schema". PHP_EOL .
                "  AND sequence_name LIKE '". $table ."%'";
        $params = array(
            ':p_schema' => $this->_schemacible
        );
        $this->_oVue->setRequete($sql, $params);
        $sequence = $this->_oVue->fetchOne();
        return $sequence;
    }
}

?>