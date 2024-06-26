<?php
namespace jemdev\dbrm;
use jemdev\dbrm\abstr\execute;
/**
 * @package     jemdev
 *
 * Ce code est fourni tel quel sans garantie.
 * Vous avez la liberté de l'utiliser et d'y apporter les modifications
 * que vous souhaitez. Vous devrez néanmoins respecter les termes
 * de la licence CeCILL dont le fichier est joint à cette librairie.
 * {@see http://www.cecill.info/licences/Licence_CeCILL_V2-fr.html}
 */
/**
 * Classe d'accès aux données.
 *
 * Permet l'Exécution de requêtes SQL essentiellement de sélection.
 * On peut toutefois envoyer des requêtes d'écriture, mais dans la
 * mesure où ça ne concerne qu'une seule ligne de données, il est
 * préférable de faire appel à jemdev\dbrm\table.
 *
 * @author      Jean Molliné <jmolline@gmail.com>
 * @since       PHP 5.x.x
 * @package     jemdev
 * @subpackage  dbrm
 */
class vue extends execute
{
    const JEMDB_FETCH_OBJECT  = 'object';
    const JEMDB_FETCH_ARRAY   = 'array';
    const JEMDB_FETCH_ASSOC   = 'assoc';
    const JEMDB_FETCH_NUM     = 'num';
    const JEMDB_FETCH_LINE    = 'line';
    const JEMDB_FETCH_ONE     = 'one';
    /**
     * Instance du singleton.
     * @var jemdev\dbrm\vue
     */
    private static $_instance;
    /**
     * Paramètres PDO de préparation de requête.
     *
     * @var Array
     */
    private $_aParams   = array();
    /**
     * Requête SQL finale.
     *
     * @var String
     */
    private $_sSql;
    /**
     * Nom de la procédure stockée à appeler
     *
     * @var String
     */
    private $_sNomProcedureStockee;

    public function __construct($dbConf)
    {
        parent::__construct($dbConf);
    }

    /**
     * Récupération du singleton de jemdev\dbrm\vue.
     * @param  array        $dbConf configuration de la base de données
     * @return jemdev\dbrm\vue
     */
    public static function getInstance($dbConf)
    {
        if(is_null(self::$_instance))
        {
            self::$_instance = new vue($dbConf);
        }
        return(self::$_instance);
    }

    /**
     * Envoyer directement une requête.
     *
     * @param  String   $sql        Requête SQL à exécuter
     * @param  Array    $aParams    Paramètres PDO à intégrer dans la requête
     * @param  Boolean  $cache      Mise en cache du résultat de la requête
     * @return Array
     * @todo Mettre en place la gestion de blocage de mise en cache à partir du troisième paramètre.
     */
    public function setRequete($sql, $aParams = array(), $cache = false)
    {
        $this->_sSql            = $this->_optimiseSqlString($sql);
        $this->_aParams         = $aParams;
        $this->_bCacheResultat  = $cache;
    }

    /**
     * Retourne un tableau associatif de données.
     *
     * @return Array
     */
    public function fetchAssoc()
    {
        return $this->_getDatas('assoc');
    }
    /**
     * Retourne un tableau avec un double index numérique et
     * associatif pour chaque donnée
     *
     * @return Array
     */
    public function fetchArray()
    {
        return $this->_getDatas('array');
    }
    /**
     * Retourne un tableau de données sous le forme d'un objet.
     *
     * @return Object
     */
    public function fetchObject()
    {
        return $this->_getDatas('object');
    }
    /**
     * Retourne une unique ligne de données sous forme d'un tableau associatif.
     *
     * @return Array
     */
    public function fetchLine($out = 'array')
    {
        if($out == self::JEMDB_FETCH_ASSOC )
        {
            $aInfosTmp = $this->_getDatas(self::JEMDB_FETCH_ASSOC);
            $aInfos = (count($aInfosTmp) > 0) ? $aInfosTmp[0] : array();
        }
        elseif($out == self::JEMDB_FETCH_NUM )
        {
            $aInfosTmp = $this->_getDatas(self::JEMDB_FETCH_NUM);
            $aInfos = (count($aInfosTmp) > 0) ? $aInfosTmp[0] : array();
        }
        else
        {
            $aInfos = $this->_getDatas(self::JEMDB_FETCH_LINE);
        }
        $retour = (count($aInfos) > 0) ? $aInfos : array();
        return $retour;
    }
    /**
     * Retourne une unique donnée.
     *
     * @return String
     */
    public function fetchOne()
    {
        $aInfos = $this->_getDatas('one');
        $retour = ((!empty($aInfos)) || (is_array($aInfos) && count($aInfos) > 0)) ? $aInfos : null;
        return $retour;
    }

    /**
     * Exécute une requête préparée définie au préalable.
     *
     * @see setRequete()
     * @see jemdev\dbrm\abstr\execute::_execProc()
     * @return Mixed    true ou tableau d'informations sur l'erreur;
     */
    public function execute()
    {
        /**
         * Connexion si inexistant.
         */
        if(true !== $this->_bConnecte)
        {
            $this->_connect($this->_aConfig['schema']);
            $this->_bConnecte = true;
        }
        $retour = $this->_execProc($this->_sSql, $this->_aParams);
        return($retour);
    }

    /**
     * Récupération des erreurs d'exécution.
     *
     * @return Array
     */
    public function getErreurs()
    {
        return $this->_aDbErreurs;
    }

    public function startTransaction()
    {
        $st = $this->_debutTransaction();
    }
    public function finishTransaction($bOk)
    {
        if(true === $bOk)
        {
            $this->_confirmeTransaction();
        }
        else
        {
            $this->_annuleTransaction();
        }
    }

    /**
     * Initialisation d'une variable utilisateur qui sera utilisée dans
     * une requête.
     *
     * Exemple d'utilisation :
     * <code>
     * // Initialisation d'un compteur de ligne à zéro
     * $this->-Db->setVariableUtilisateur('@v_compteur', 0);
     * // Définition de la requête principale
     * $sql = "SELECT". PHP_EOL .
     *        "  @v_compteur := @v_compteur +1 AS rang,". PHP_EOL .
     *        "  col_1". PHP_EOL .
     *        "FROM matable";
     * // Initialisation de la requête
     * $this->setRequete($sql);
     * // Exécution de la requête et récupératin du résultat.
     * $maliste =  = $this->_oDb->fetchAssoc();
     * </code>
     * Le résultat sera ici un tableau dont la première colonne sera un nombre automatiquement
     * incrémenté de 1 à chaque ligne, la première commençant ici à 1.
     *
     * @param   String  $var    Variable qui sera définie sous la forme « @v_variable »
     * @param   mixed   $valeur Optionnel, valeur initiale de la variable avant l'exécution de
     *                          la requête qui l'utilisera.
     * @return  boolean
     */
    public function setVariableUtilisateur($var, $valeur = null)
    {
        $sql = "SET ". $var ." = :p_valeur";
        $params = array(':p_valeur' => $valeur);
        $this->setRequete($sql, $params);
        $retour = $this->execute();
        return $retour;
    }

    /**
     * Activation ou désactivation dynamique et temporaire du cache de requête.
     * Indépendamment de la constante d'application.
     *
     * @param Boolean $statut
     */
    public function setTmpActivationCache($statut = false)
    {
        $this->_bCacheRequetes = $statut;
    }

    /**
     * Appelle la méthode appropriée en indiquant le type de retour attendu.
     *
     * @param   String  $out
     * @return  Mixed
     */
    private function _getDatas($out = 'assoc')
    {
        /**
         * Connexion
         */
        if(true !== $this->_bConnecte)
        {
            $this->_connect($this->_aConfig['schema']);
        }
        /**
         * Exécution et récupération du résultat
         */
        return $this->_fetchDatas($this->_sSql, $this->_aParams, $out);
    }

    /**
     * Attention :
     *    - la constante « CONF » définit le répertoire où est stocké le fichier
     *      de configuration de la base de données généré; @see jemdev\dbrm\init\genereconf
     *    - le fichier « appConf.php » contient les constantes définies pour l'application;
     *    - la constante « DB_CONF » contient le nom du fichier de configuration;
     *    - la constante « DB_APP_SCHEMA » contient le nom du schéma de données.
     * @param   String $nomTable Nom de la table dont on veut un instance
     * @return  ligneInstance
     */
    public function getInstanceLigneTable($nomTable): ligneInstance
    {
        require_once(CONF .'appConf.php');
        require(DB_CONF);
        /**
         * Chargement du système d'accès aux données et aux objets.
         * Attention au chemin, il est relatif au fichier courant.
        */
        if(isset($dbConf))
        {
            $oTable = new table(DB_APP_SCHEMA, $nomTable, $dbConf[0]);
            $oInstanceLigne = $oTable->getInstance();
        }
        else
        {
            $oInstanceLigne = false;
        }
        return $oInstanceLigne;
    }

    /**
     * Récupération de la liste des valeurs possible d'une colonne de type ENUM
     *
     * Retourne un tableau de valeurs ou bien false si la table n'est pas trouvée, ou si
     * la colonne n'existe pas ou encore si le type de colonne ne correspond pas.
     *
     * @param   string  $table      Nom de la table;
     * @param   string  $colonne    Nom de la colonne à vérifier;
     * @return array
     */
    public function getValeursChampsEnum($table, $colonne)
    {
        $retour = [];
        if(isset($this->_aConfig['tables'][$table]))
        {
            $t = 'tables';
        }
        elseif(isset($this->_aConfig['relations'][$table]))
        {
            $t = 'relations';
        }
        else
        {
            $t = false;
        }
        if(false !== $t && isset($this->_aConfig[$t][$table]['fields'][$colonne]))
        {
            if($this->_aConfig[$t][$table]['fields'][$colonne]['type'] == TYPE_ENUM)
            {
                $retour['vals']    = $this->_aConfig[$t][$table]['fields'][$colonne]['attr']['vals'];
                $retour['default'] = $this->_aConfig[$t][$table]['fields'][$colonne]['attr']['default'];
            }
            else
            {
                if(isset($this->_aConfig[$t][$table]['fields'][$colonne]['attr']['default']))
                {
                    $retour['default'] = $this->_aConfig[$t][$table]['fields'][$colonne]['attr']['default'];
                    $retour['vals']    = null;
                }
            }
        }
        if(empty($retour))
        {
            $retour = false;
        }
        return $retour;
    }

    public function __destruct()
    {
        // unset($this);
    }

    public function __serialize(): array
    {
        return ['Instance of PDO'];
    }

}
