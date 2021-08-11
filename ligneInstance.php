<?php
namespace jemdev\dbrm;
use jemdev\dbrm\jemdevDbrmException;
use jemdev\dbrm\abstr\execute;
use jemdev\dbrm\cache\cache;
/**
 * @package jemdev
 */
/**
 * Classe implémentant un objet pour une table.
 *
 * Basée sur un multiton, on peut utiliser cette classe pour
 * autant de tables que nécessaire. Si on a besoin de plusieurs
 * instances pour une même table, on créera une instance en
 * précisant un alias différent pour chaque occurence de la table.
 *
 * Créé le 01/05/2008
 * @author      Jean Molliné <jmolline@gmail.com>
 * @since       PHP 5.4.x
 * @package     jemdev
 * @subpackage  dbrm
 * @todo Implémentation d'une méthode permettant d'affecter une fonction SQL en valeur à
 *       une colonne, par exemple, pouvoir faire :
 *       $instanceLigne->nom_colonne = "AES_ENCRYPT('valeur', 'Clé de chiffrement')";
 */
class ligneInstance extends execute
{
    /**
     * Description globale du schéma de données
     *
     * @var Array
     */
    private $_aConfigSchema;
    /**
     * Détails de la configuration de la table traitée.
     *
     * @var Array
     */
    private $_aConfigTable;
    /**
     * Nom de la table à traiter.
     *
     * @var String
     */
    private $_sNomTable;

    /**
     * Alias qui sera utilisé pour le nom de la table dans les requêtes.
     *
     * @var String.
     */
    private $_aliasTable;

    /**
     * Liste des colonnes de la table.
     *
     * On retrouvera dans ce tableau les informations permettant de valider
     * certaines requêtes avant exécution. Par exemple on devra savoir si on
     * peut envoyer une valeur NULL dans une colonne ou encore si la valeur envoyée
     * dans une colonne de type ENUM est bien répertoriée dans la liste des valeurs
     * possibles ou encore s'il s'agit bien d'un entier ou d'autre chose.
     *
     * La structure du tableau sera donc ainsi :
     * Array(
     *    [nom-de-la-colonne]=> Array(
     *        [type]    => String,
     *        [length]  => Int,
     *        [null]    => Bool,
     *        [attr]    => Array(
     *            String[,
     *            String]
     *        )
     *    )
     *)
     *
     * @var Array
     */
    private $_aColonnes = array ();

    /**
     * Liste des noms des colonnes de la table.
     *
     * @var Array
     */
    private $_aCles = array();

    /**
     * Liste des colonnes composant la clé primaire.
     *
     * @var Array
     */
    private $_aPk = array();

    /**
     * Blocage sur l'initialisation de valeurs sur les clés primaires.
     *
     * Ce blocage sera levé sur les tables relationnelles puisque
     * la clé primaire est multiple et composée de clés étrangères
     * venant d'autres tables.
     *
     * @var Boolean
     */
    private $_bLockPk = true;

    /**
     * Tableau des instances de la classe
     *
     * @var Array   Tableau d'objets.
     */
    private static $_aInstances = array ();

    /**
     * Requête SQL
     *
     * @var String
     */
    private $_sql;

    /**
     * Booléen indiquant qu'on peut écrire dans les colonnes de clé primaires
     * d'une table relationnelle.
     * Si on ne trouve pas de données lors de l'initialisation de l'instance de ligne,
     * c'est qu'on est en phase de création.
     *
     * @var Boolean
     */
    private $_wr = false;

    private $_bNewLine = false;

    /**
     * Constructeur.
     *
     * Ce constructeur est privé selon un système de
     * multiton, la propriété dbTable::_aInstances contenant
     * tous les objets instanciés via cette classe.
     *
     * @param   String $_schema          Nom du schéma de données sur lequel seront collectées les informations
     * @param   String $_sNomTable       Nom de la table
     * @param   String $aConfig          Tableau où sont enregistrés les paramètres des tables.
     * @param   String $_aliasTable      Alias du nom de table
     * @throws  jemdevDbrmException
     *
     * @todo    Comment intégrer les fonctions et autres procédures stockées...?
     */
    protected function __construct($_schema, $_sNomTable, $aConfig, $_aliasTable)
    {
        parent::__construct($aConfig);
        $this->_aConfigSchema = $aConfig;
        $this->_sNomTable     = $_sNomTable;
        $this->_aliasTable    = $_aliasTable;
        /**
         * Récupération des informations sur les colonnes de
         * la table.
         */
        if(array_key_exists($_sNomTable, $aConfig['tables']))
        {
            $this->_aConfigTable = $aConfig['tables'][$_sNomTable];
            /**
             * Ici, on demande un objet sur une table.
             */
            $this->_aColonnes = $aConfig['tables'][$_sNomTable]['fields'];
            foreach($this->_aColonnes as $k => $infos)
            {
                $this->_aCles[] = $k;
            }
            $this->_aPk = $aConfig['tables'][$_sNomTable]['key']['pk'];
            /**
             * S'il s'agit d'une table, on bloque l'accès en écriture sur
             * les clés primaires qui doivent être gérées en interne
             * par le SGBD.
             */
            $this->_bLockPk = true;
        }
        elseif(isset($aConfig['relations']) && array_key_exists($_sNomTable, $aConfig['relations']))
        {
            $this->_aConfigTable = $aConfig['relations'][$_sNomTable];
            /**
             * Ici, on demande un objet sur une table relationnelle.
             */
            $this->_aColonnes = $aConfig['relations'][$_sNomTable]['fields'];
            foreach($this->_aColonnes as $k => $infos)
            {
                $this->_aCles[] = $k;
            }
            $this->_aPk = $aConfig['relations'][$_sNomTable]['key']['pk'];
            /**
             * Dans une table relationnelle, la clé primaire étant
             * composée de clés étrangères, on débloque l'accès en
             * écriture des colonnes composant cette clé primaire
             * pour permettre l'ajout de lignes.
             */
            $this->_wr      = true;
            $this->_bLockPk = false;
        }
        elseif(isset($aConfig['vues']) && array_key_exists($_sNomTable, $aConfig['vues']))
        {
            /**
             * Ici, on demande un objet sur une vue.
             * Il convient donc de mettre en place un mécanisme
             * pour traiter les données en travaillant sur les
             * tables appropriées.
             */
            $this->_aColonnes = $aConfig['vues'][$_sNomTable]['fields'];
            foreach($this->_aColonnes as $k)
            {
                $this->_aCles[] = $k;
            }
        }
        else
        {
            /**
             * Si on a trouvé nulle part de correspondance, on jette une exception.
             */
            $msg = "La table &laquo; ". $_sNomTable ." &raquo; est inexistante";
            throw new jemdevDbrmException($msg, E_USER_ERROR);
        }
        $this->_connect($aConfig['schema']);
    }

    /**
     * Récupération d'un instance de la classe.
     *
     * Cette classe fonctionne sur le principe d'un multiton.
     * On limite à une instance par table de la base de
     * données pour permettre de travailler sur les différentes
     * tables. L'instance est identifiée par le nom de la table
     * ainsi que l'alias utilisé pour les requêtes.
     * Pour obtenir deux instances d'une même table, il faut
     * utiliser deux alias différents.
     *
     * @param   String  $_schema        Nom du schéma à utiliser
     * @param   String  $_sNomTable     Nom de la table à utiliser
     * @param   Array   $aConfig        Description des schémas
     * @param   String  $_aliasTable    Alias de la table (optionnel)
     * @return  Object
     */
    static public function getInstance($_schema, $_sNomTable, $aConfig, $_aliasTable = null)
    {
        if (!isset($_aliasTable))
        {
            $_aliasTable = $_sNomTable;
        }
        if (!isset(self::$_aInstances[$_sNomTable]) || !isset(self::$_aInstances[$_sNomTable][$_aliasTable]))
        {
            try
            {
                foreach ($aConfig as $schema)
                {
                    if(isset($schema['schema']['name']) && $schema['schema']['name'] == $_schema)
                    {
                        self::$_aInstances[$_sNomTable][$_aliasTable]= new ligneInstance($_schema, $_sNomTable, $schema, $_aliasTable);
                        break;
                    }
                }
            }
            catch (jemdevDbrmException $dbe)
            {
                return $dbe;
            }
            catch (jemdevDbrmException $e)
            {
                return $e;
            }
        }
        return self::$_aInstances[$_sNomTable][$_aliasTable];
    }

    /**
     * Blocage du clonage d'instance.
     *
     * Retourne une erreur de type Warning.
     */
    public function __clone()
    {
        trigger_error("Clonage non autorisé : utilisez un alias de table différent.", E_USER_WARNING);
        throw new jemdevDbrmException("Clonage non autorisé : utilisez un alias de table différent.");
    }

    /**
     * Récupération de la valeur d'une colonne.
     *
     * Retourne la valeur de la colonne si elle existe, sinon
     * retourne une erreur de type Warning.
     *
     * @param  String   $colonne
     * @return String
     */
    public function __get($colonne)
    {
        $retour = false;
        if (in_array($colonne, $this->_aCles))
        {
            $retour = (isset($this->{$colonne})) ? $this->{$colonne} : null;
        }
        else
        {
            $msg  = "Propriété inexistante pour cette instance : la colonne «". $colonne ."» n'existe pas dans la table ". $this->_sNomTable;
            trigger_error($msg, E_USER_WARNING);
        }
        return $retour;
    }

    /**
     * Affecte une valeur pour une colonne donnée.
     *
     * Si la colonne existe, on lui affecte la valeur envoyée en
     * paramètre, sinon, retourne une erreur de type USER_ERROR.
     * Si la valeur est vide et que la colonne est « nullable », on
     * affecte alors la valeur NULL. Sinon, on valide le type de
     * valeur par rapport au type de la colonne.
     *
     * @param String $colonne
     * @param Mixed  $valeur
     */
    public function __set($colonne, $valeur)
    {
        if (in_array($colonne, $this->_aCles))
        {
            if(in_array($colonne, $this->_aPk) && $this->_bLockPk === true)
            {
                $msg = "Propriété inaccessible :<br />". PHP_EOL;
                $msg .= " - La colonne <em>". $colonne ."</em> est une clé primaire<br />". PHP_EOL;
                $msg .= " - Sa valeur est automatiquement gérée par le serveur de base de données.<br />". PHP_EOL;
                trigger_error($msg, E_USER_WARNING);
            }
            else
            {
                $valeur = trim($valeur);
                $typeData = 'null';
                try
                {
                    if(empty($valeur) && $valeur !== '0' && $valeur !== 0)
                    {
                        /**
                         * Si la valeur envoyée est vide ou nulle, soit la colonne est « nullable », soit
                         * il s'agit d'une nouvelle ligne et on permet alors de ré-initialiser toutes les colonnes.
                         */
                        if(true === $this->_aConfigTable['fields'][$colonne]['null'] || (true !== $this->_aConfigTable['fields'][$colonne]['null'] && true === $this->_bNewLine))
                        {
                            $valeur = null;
                            $typeData = 'null';
                        }
                        else
                        {
                            if(isset($this->_aConfigTable['fields'][$colonne]['attr']['default']) && !is_null($this->_aConfigTable['fields'][$colonne]['attr']['default']))
                            {
                                $valeur = $this->_aConfigTable['fields'][$colonne]['attr']['default'];
                                $typeData = 'string';
                            }
                            else
                            {
                                throw new jemdevDbrmException("Une valeur est obligatoirement requise dans la table ". $this->_sNomTable ." pour la colonne ". $colonne, E_USER_WARNING);
                            }
                        }
                    }
                    else
                    {
                        $l = strlen($valeur);
                        switch ($this->_aConfigTable['fields'][$colonne]['type'])
                        {
                            case 'VARCHAR':
                            case 'VARBINARY':
                            case 'TEXT':
                            case 'CHAR':
                                if(!is_null($this->_aConfigTable['fields'][$colonne]['length']) && $l > $this->_aConfigTable['fields'][$colonne]['length'])
                                {
                                    throw new jemdevDbrmException("La longueur de la valeur envoyée excède l'espace disponible pour la colonne ". $colonne ."; longueur reçue : ". $l .", maximum autorisé : ". $this->_aConfigTable['fields'][$colonne]['length'], E_USER_WARNING);
                                }
                                $typeData = 'string';
                                break;
                            case 'ENUM':
                                if(!in_array($valeur, $this->_aConfigTable['fields'][$colonne]['attr']['vals']))
                                {
                                    throw new jemdevDbrmException("La valeur reçue pour la colonne ". $colonne ." n'est pas répertoriée dans les valeurs possibles (". implode(", ", $this->_aConfigTable['fields'][$colonne]['attr']['vals']) .")", E_USER_WARNING);
                                }
                                $typeData = 'string';
                                break;
                            case 'INT':
                            case 'MEDIUMINT':
                            case 'TINYINT':
                            case 'SMALLINT':
                            case 'DECIMAL':
                            case 'FLOAT':
                                if(!is_numeric($valeur))
                                {
                                    throw new jemdevDbrmException("La valeur reçue pour la colonne ". $colonne ." n'est pas numérique (reçu «". $valeur ."»)", E_USER_WARNING);
                                }
                                if(preg_match("#^[0-9]+$#", $valeur))
                                {
                                    $typeData = 'int';
                                }
                                else
                                {
                                    $typeData = 'string';
                                }
                            case 'BIGINT':
                                if(
                                    (false === ($valeur >= pow(-2,63) && $valeur < pow(2,63)))
                                )
                                {
                                    throw new jemdevDbrmException("La valeur reçue pour la colonne ". $colonne ." dépasse les limites permises (reçu «". $valeur ."», minimum autorisé ". pow(-2,63) .", maximum ". (pow(2,63)-1) .")", E_USER_WARNING);
                                }
                                $typeData = 'int';
                                break;
                            case 'INT':
                                if(
                                    (false === ($valeur >= pow(-2,31) && $valeur < pow(2,31)))
                                )
                                {
                                    throw new jemdevDbrmException("La valeur reçue pour la colonne ". $colonne ." dépasse les limites permises (reçu «". $valeur ."», minimum autorisé ". pow(-2,31) .", maximum ". (pow(2,31)-1) .")", E_USER_WARNING);
                                }
                                $typeData = 'int';
                                break;
                            case 'MEDIUMINT':
                                if(
                                    (false === ($valeur >= pow(-2,23) && $valeur < pow(2,23)))
                                )
                                {
                                    throw new jemdevDbrmException("La valeur reçue pour la colonne ". $colonne ." dépasse les limites permises (reçu «". $valeur ."», minimum autorisé ". pow(-2,23) .", maximum ". (pow(2,23)-1) .")", E_USER_WARNING);
                                }
                                $typeData = 'int';
                                break;
                            case 'SMALLINT':
                                if(
                                    (false === ($valeur >= pow(-2,15) && $valeur < pow(2,15)))
                                )
                                {
                                    throw new jemdevDbrmException("La valeur reçue pour la colonne ". $colonne ." dépasse les limites permises (reçu «". $valeur ."», minimum autorisé ". pow(-2,15) .", maximum ". (pow(2,15)-1) .")", E_USER_WARNING);
                                }
                                $typeData = 'int';
                                break;
                            case 'TINYINT':
                                if(
                                    (false === ($valeur >= pow(-2,7) && $valeur < pow(2,7)))
                                )
                                {
                                    throw new jemdevDbrmException("La valeur reçue pour la colonne ". $colonne ." dépasse les limites permises (reçu «". $valeur ."», minimum autorisé ". pow(-2,7) .", maximum ". (pow(2,7)-1) .")", E_USER_WARNING);
                                }
                                $typeData = 'int';
                                break;
                            case 'DECIMAL':
                            case 'FLOAT':
                                $masque = "#([0-9]+)((\.|,)([0-9]+))?#";
                                $nombre = preg_replace($masque, "$1.$4", $valeur);
                                $length = $this->_aConfigTable['fields'][$colonne]['length'];
                                $ap     = explode(".", (string) $length);
                                $e      = (int)$ap[0] - (int)$ap[1];
                                $p      = (int) $ap[1];
                                $maxd   = ((pow(10, $p) -1) / ((pow(10, $p) -1) + 1));
                                $max    = (float) (pow(10, $e)-1) + $maxd;
                                $min    = 0 - $max;
                                if(
                                    (false === ($nombre <= $max && $nombre >= $min))
                                )
                                {
                                    throw new jemdevDbrmException("La valeur reçue pour la colonne ". $colonne ." dépasse les limites permises (reçu «". $valeur ."», minimum autorisé ". $min .", maximum ". $max .")", E_USER_WARNING);
                                }
                                $typeData = 'float';
                                break;
                            case 'DATE':
                                $masque = "#^(:?[0-2])?[0-9]{1,3}-(:?0[1-9]|1[0-2])-(?:0[1-9]|[1-2][0-9]|3[0-1])$#";
                                if($valeur != '0000-00-00' && !preg_match($masque, $valeur))
                                {
                                    throw new jemdevDbrmException("La date envoyée pour la colonne ". $colonne ." n'est pas au format approprié AAAA-MM-DD, reçu «". $valeur ."»", E_USER_WARNING);
                                }
                                $typeData = 'string';
                                break;
                            case 'DATETIME':
                                $masque = "#^(:?[0-2])?[0-9]{1,3}-(:?0[1-9]|1[0-2])-(?:0[1-9]|[1-2][0-9]|3[0-1])\s(?:[0-1][0-9]|2[0-3]):(:?[0-5][0-9])(:(:?[0-5][0-9]))?$#";
                                if($valeur != '0000-00-00 00:00:00' && !preg_match($masque, $valeur))
                                {
                                    throw new jemdevDbrmException("La date envoyée pour la colonne ". $colonne ." n'est pas au format approprié AAAA-MM-DD HH:MI[:SC], reçu «". $valeur ."»", E_USER_WARNING);
                                }
                                $typeData = 'string';
                                break;
                            case 'BLOB':
                                $lm = (!is_null($this->_aConfigTable['fields'][$colonne]['length'])) ? $this->_aConfigTable['fields'][$colonne]['length'] : pow(2,16);
                                if($l > $lm)
                                {
                                    throw new jemdevDbrmException("La longueur de la valeur envoyée excède l'espace disponible pour la colonne ". $colonne ."; longueur reçue : ". $l .", maximum autorisé : ". $lm, E_USER_WARNING);
                                }
                                $typeData = 'string';
                                break;
                            default:
                                $typeData = 'string';
                        }
                    }
                    $this->{$colonne} = $valeur;
                    settype($this->{$colonne}, $typeData);
                }
                catch (jemdevDbrmException $e)
                {
                    echo('<pre style="font-size: 11px; text-align: left;">' . "\n");
                    var_dump($e);
                    echo("</pre>\n");
                }
                catch (jemdevDbrmException $e)
                {
                    echo('<pre style="font-size: 11px; text-align: left;">' . "\n");
                    var_dump($e);
                    echo("</pre>\n");
                }
            }
        }
        else
        {
            $msg = "Propriété inexistante pour cette instance :<br />". PHP_EOL;
            $msg .= " - La colonne <em>". $colonne ."</em> n'existe pas dans la table <em>". $this->_sNomTable ."</em><br />". PHP_EOL;
            trigger_error($msg, E_USER_WARNING);
        }
    }

    /**
     * Initialisation de l'Instance avec les informations d'une
     * ligne donnée à partir d'une clé primaire.
     *
     * @param Array $aPk
     */
    public function init($aPk = null)
    {
        $bPkNull = true;
        if(!is_null($aPk) && is_array($aPk))
        {
            foreach($aPk as $k => $v)
            {
                if(!is_null($v) && !empty($v))
                {
                    $bPkNull = false;
                }
            }
        }
        $aPk             = (true !== $bPkNull) ? $aPk : null;
        $this->_bNewLine = (is_null($aPk)) ? true : false;
        $etatLock        = $this->_bLockPk;
        $this->_bLockPk  = false;
        $npk             = count($this->_aPk);
        if(!is_null($aPk))
        {
            $pre_data = (!is_null($aPk)) ? $this->_getLigneTable($aPk) : false;
            if(is_array($pre_data) && count($pre_data) > 0)
            {
                foreach ($this->_aCles as $cle)
                {
                    if($this->_aConfigTable['fields'][$cle]['type'] == 'DECIMAL')
                    {
                        $this->{$cle} = 0.00;
                    }
                    $this->{$cle} = $pre_data[$cle];
                }
                $etatLock = true;
            }
            if(false === $pre_data)
            {
                if($npk > 1)
                {
                    $this->_wr = true;
                    foreach($aPk as $col => $val)
                    {
                        if(in_array($col, $this->_aCles))
                        {
                            $this->{$col} = $val;
                        }
                    }
                }
            }
            else
            {
                $this->_wr = false;
            }
        }
        else
        {
            foreach($this->_aCles as $col)
            {
                $this->{$col} = null;
            }
        }
        $this->_bLockPk = $etatLock;
    }

    /**
     * Récupération d'une ligne de la table à partir de la valeur de la clé primaire.
     *
     * Le paramètre est un tableau indiquant en index les colonnes composant la
     * clé primaire suivies de leurs valeurs.
     * Pour cette requête on désactive automatiquement la mise en cache : on remet
     * ensuite ce paramètre dans l'état où il se trouvait avant modification.
     *
     * @param   Array $aPk
     * @return  Array
     */
    private function _getLigneTable($aPk)
    {
        $result = false;
        if(!is_null($aPk))
        {
            $sql = "SELECT ";
            $sql .= implode(", ", $this->_aCles) ." ";
            $sql .= "FROM ". $this->_sNomTable ." ";
            $sql .= "WHERE ";
            $aWhere = array();
            $in     = array();
            if(is_array($aPk))
            {
                foreach ($aPk as $pk => $val)
                {
                    $aWhere[] = $pk .' = :p_'. $pk;
                    $in[':p_'. $pk] = $val;
                }
            }
            else
            {
                $aWhere[] = $this->_aPk[0] .' = :p_'. $this->_aPk[0];
                $in[':p_'. $this->_aPk[0]] = $aPk;
            }
            $sql .= implode(" AND ", $aWhere);
            $tmpCache = $this->_bCacheRequetes;
            $this->_bCacheRequetes = false;
            $result = $this->_fetchLine($sql, $in);
            $this->_bCacheRequetes = $tmpCache;
        }
        return $result;
    }

    /**
     * Suppression d'une ligne de données
     *
     * En cas d'erreur, une exception sera levée.
     *
     * @return Bool
     */
    public function supprimer()
    {
        // code de suppression de données.
        $retour = false;
        if(count($this->_aPk) > 0)
        {
            $aWhere = array();
            $params = array();
            foreach ($this->_aPk as $cle)
            {
                $aWhere[] = $cle .' = :p_'. $cle;
                $params[':p_'. $cle] = $this->{$cle};
            }
            $sql = "DELETE FROM ". $this->_sNomTable ." ".
                   "WHERE ". implode(" AND ", $aWhere);

            $retour = $this->_execProc($sql, $params);
            if(true !== $retour)
            {
                $sErreurs = debug_backtrace();
                $msg = isset($retour[0][1]) ? PHP_EOL . $retour[0][1] .";". PHP_EOL : null;
                $retour = new jemdevDbrmException("Requête «". $sql ."»;". PHP_EOL . $msg ."Trace :". PHP_EOL . $this->_setTraceMessage($sErreurs), E_USER_ERROR);
            }
            else
            {
                $this->init();
            }
            if(false !== $retour && $this->_oCache instanceof cache)
            {
                $this->_oCache->cleanCacheTable($this->_sNomTable);
            }
        }
        return($retour);
    }

    /**
     * Sauvegarde de la ligne de données.
     *
     * Code qui va définir si on insère de nouvelles données ou s'il s'agit
     * d'une mise à jour : selon qu'on a un identifiant ou non, on appellera
     * l'une des méthodes privées dbTable::inserer() ou dbTable::mettreajour()
     *
     * @return Boolean
     */
    public function sauvegarder()
    {
        /*
         * temporaire
         * en mode développement, va retourner systématiquement true
         * sans enregistrer quoique ce soit et éviter de pourrir la
         * base de données.
         */
        if(defined('DEV_LOCK') && DEV_LOCK === true)
        {
            return(true);
        }
        $aCols   = array();
        $aParams = array();
        $in      = array();
        /**
         * Validation de la présence des données obligatoires.
         */
        foreach($this->_aCles as $cle)
        {
            if(!in_array($cle, $this->_aPk) || (in_array($cle, $this->_aPk) && $this->_bLockPk == false))
            {
                $aField = $this->_aColonnes[$cle];
                $val = $this->{$cle};
                $bv = ($val !== 0 && $val !== 0.0 && $val !== '0' && (is_null($val) || empty($val)));
                if(isset($aField['attr']['default']) && true == $bv)
                {
                    $val = $aField['attr']['default'];
                    $bv = false;
                }
                if($aField['null'] === false && $bv == true)
                {
                    throw new jemdevDbrmException('La colonne '. $cle .' (table « '. $this->_sNomTable .' ») requiert obligatoirement une valeur. (aucune valeur valide reçue)', E_USER_WARNING);
                }
                else
                {
                    $sCle      = ':p_'. $cle;
                    $aCols[]   = $cle;
                    $aParams[] = $sCle;
                    $in[$sCle] = $val;
                }
            }
        }
        /**
         * Vérification si on fait une mise à jour (UPDATE -> u) ou une insertion (INSERT -> i).
         * Attention : si on a une clé primaire multiple, on peut avoir à faire une insertion,
         * donc on vérifiera si TOUTES les clés primaires ont déjà ou non une valeur.
         */
        $npk = count($this->_aPk);
        $nk  = count($this->_aCles);
        if($npk <= 1)
        {
            $pk = $this->_aPk[0];
            $sauvegarde = (isset($this->{$pk}) && !empty($this->{$pk})) ? 'u' : 'i';
        }
        else
        {
            /**
             * Clé primaire multiple => table relationnelle :
             * On rajoute les colonnes de clés primaires avec leurs valeurs.
             */
            foreach($this->_aPk as $k)
            {
                $sCle = ':p_'. $k;
                if(!array_key_exists($sCle, $in))
                {
                    $aParams[] = $sCle;
                    $aCols[]   = $k;
                    $in[$sCle] = $this->{$k};
                }
            }
            $sauvegarde = (true == $this->_wr) ? 'i' : 'u';
        }
        if($sauvegarde == 'i')
        {
            $retour = $this->_inserer($aCols, $aParams, $in);
        }
        else
        {
            $retour = $this->_mettreajour($aCols, $aParams, $in);
        }
        if(false !== $retour && $this->_oCache instanceof cache)
        {
            $this->_oCache->cleanCacheTable($this->_sNomTable);
        }
        return $retour;
    }

    /**
     * Démarrage d'une transaction SQL
     */
    public function transactionDebut()
    {
        $debut = $this->_debutTransaction();
        if(false === $debut)
        {
            $debut = $this->_aDbErreurs;
        }
        return $debut;
    }

    /**
     * Termine une transaction.
     *
     * Si le paramètre vaut true, on lancera un COMMIT, sinon
     * on effectuera un ROLLBACK.
     *
     * @param Boolean $ok
     */
    public function transactionFin($ok = true)
    {
        if(true === $ok)
        {
            $fin = $this->_confirmeTransaction();
        }
        else
        {
            $fin = $this->_annuleTransaction();
        }
        if(false === $fin)
        {
            $fin = $this->_aDbErreurs;
        }
        return $fin;
    }

    /**
     * Construction d'une requête d'insertion
     * @param   array   $aCols      Liste des colonnes de la table
     * @param   array   $aParams    Liste des paramètres de la requête
     * @param   array   $in         Liste des valeurs des paramètres
     * @return  boolean             Retourne true si l'insertion s'est correctement déroulée.
     */
    private function _inserer($aCols, $aParams, $in)
    {
        /* Code d'ajout de données dans la base */
        $sql = "INSERT INTO ". $this->_sNomTable ."(";
        $sql .= implode(", ", $aCols);
        $sql .= ") VALUES (";
        $sql .= implode(", ", $aParams);
        $sql .= ")";
        /* Exécution */
        $retour = $this->_execProc($sql, $in);
        /**
         * Récupération de la clé primaire (S'il n'y a qu'une seule colonne).
         * S'il s'agit d'une clé multiple, on a à faire avec une table
         * relationnelle, on connait donc déjà ces valeurs.
         */
        if(true === $retour && count($this->_aPk) === 1)
        {
            $this->_bLockPk = false;
            $name = null;
            if($this->_aConfigSchema['schema']['SGBD'] == 'pgsql')
            {
                $name = $this->_aConfigTable['fields'][$this->_aConfigTable['key']['pk'][0]]['attr']['extra'];
            }
            $this->{$this->_aPk[0]} = $this->_getLastId($name);
            $this->_bLockPk = true;
        }
        return $retour;
    }

    private function _mettreajour($aCols, $aParams, $in)
    {
        // Code de mise à jour d'une ligne de données
        $aSet = array();
        foreach($aCols as $i => $col)
        {
            if(!in_array($col, $this->_aPk))
            {
                $aSet[] = $col ." = ". $aParams[$i];
            }
        }
        $aWhere = array();
        foreach($this->_aPk as $pk)
        {
            $aWhere[] = $pk ." = :p_". $pk;
            $in[':p_'. $pk] = $this->{$pk};
        }
        $sql  = "UPDATE ". $this->_sNomTable ." SET ";
        $sql .= implode(", ", $aSet) ." ";
        $sql .= "WHERE ". implode(" AND ", $aWhere);
        /* Exécution */
        $retour = (count($aSet) > 0) ? $this->_execProc($sql, $in) : true;
        return $retour;
    }

    private function _getInfosFromTable()
    {
        // Récupération des colonnes de la table si la config n'est pas disponible
        // exemple MySQL... à ajuster...
        $sql = "DESCRIBE " . $this->_sNomTable;
    }

    public function __toString()
    {
        $sRetour = <<<PLAIN_TEXT
Informations sur l'instance :
Table : {$this->_sNomTable};
Colonnes :

PLAIN_TEXT;
        foreach($this->_aCles as $cle)
        {
            $val = (isset($this->{$cle})) ? $this->{$cle} : 'null';
            $sRetour .= <<<PLAIN_TEXT
    {$cle} = &laquo; {$val} &raquo;;

PLAIN_TEXT;
        }
        return $sRetour;
    }

    /**
     * Construction des messages d'erreur.
     *
     * Le paramètre attendu est le retour de la fonction PHP
     * debug_backtrace()
     *
     * @param   Array   $aTraces
     * @return  String
     */
    private function _setTraceMessage($aTraces)
    {
        $msg = null;
        /**
         * On commence par isoler les traces contenant un chemin de fichier et
         * un numéro de ligne
         */
        $aTrace = array();
        foreach ($aTraces as $t)
        {
            if(isset($t['file']) && isset($t['line']))
            {
                $aTrace[] = $t;
            }
        }
        $nt = count($aTrace);
        if($nt > 0)
        {
            $msg .= " - Trace :<br />". PHP_EOL;
            foreach ($aTrace as $trace)
            {
                $nt--;
                $msg .= '   # ['. $nt .'] '. $trace['file'] .' (ligne '. $trace['line'] .')' . "<br />". PHP_EOL;
            }
            $msg .= " -------------------------------------------------<br />". PHP_EOL;
        }
        return $msg;
    }

    public function getErreurs()
    {
        return $this->_aDbErreurs;
    }
}
