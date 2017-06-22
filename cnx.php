<?php
namespace jemdev\dbrm;
use jemdev\dbrm\jemdevDbrmException;
use jemdev\dbrm\vue;
use jemdev\dbrm\table;
use jemdev\dbrm\init\genereconf;
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
 * Classe principale d'accès aux objets de manipulation de bases de données.
 *
 * @author      Jean Molliné <jmolline@gmail.com>
 * @package     jemdev
 * @subpackage  dbrm
 */
class cnx
{
    /**#@+
     * Messages d'erreurs utilisés dans les levées d'exceptions.
     */
    const ERREUR_CONF_NULL      = "Configuration de base de données non définie, exécutez jemdev\dbrm\init\genereconf";
    const ERREUR_ACCES_PROP     = "Propriété %s inaccessible ou inexistante de la classe jemdev\dbrm\cnx";
    const ERREUR_CALL_METHODE   = "Méthode %s inaccessible ou inexistante de la classe jemdev\dbrm\cnx";
    const ERREUR_FICHIER_CONF   = "Fichier de configuration de base de données introuvable";
    /**#@-*/
    /**
     * Instance de la classe jemdev\dbrm\vue
     *
     * @var Object jemdev\dbrm\vue
     */
    private $_oVue;
    /**
     * Instance de la classe jemdev\dbrm\table
     *
     * @var Object jemdev\dbrm\table
     */
    private $_oTable;
    /**
     * Configuration de la base de donnée visée.
     *
     * @var Array
     */
    private $_dbConf;
    /**
     * Propriétés accessibles via les méthodes __get() ou __set()
     *
     * @var Array
     */
    private $_aProps;

    /**
     * Constructeur.
     *
     * @param Mixed $dbConf     Tableau de description ou chemin vers le fichier.
     */
    public function __construct($dbConf)
    {
        if(!is_null($dbConf))
        {
            if(is_array($dbConf))
            {
                $this->_dbConf = $dbConf;
            }
            else
            {
                if(file_exists($dbConf))
                {
                    require_once($dbConf);
                }
                else
                {
                    throw new jemdevDbrmException(self::ERREUR_FICHIER_CONF, E_USER_NOTICE);
                }
            }
        }
        else
        {
            throw new jemdevDbrmException(self::ERREUR_CONF_NULL , E_USER_NOTICE);
        }
        $this->_aProps = array(
                'out' => array(
                'vue',
            ),
                'in' => array(
                'dbConf'
            )
        );
    }

    /**
     * Récupération d'une propriété de l'instance.
     *
     * @param   String  $prop
     * @return  Mixed
     */
    public function __get($prop)
    {
        if(in_array($prop, $this->_aProps['out']))
        {
            $objet = false;
            switch ($prop)
            {
                case 'vue':
                    if(!isset($this->_oVue))
                    {
                        if(isset($this->_dbConf))
                        {
                            // $this->_oVue = new jemdev\dbrm\vue($this->_dbConf);
                            $this->_oVue = vue::getInstance($this->_dbConf);
                        }
                        else
                        {
                            throw new jemdevDbrmException(self::ERREUR_CONF_NULL , E_USER_NOTICE);
                        }
                    }
                    $objet = $this->_oVue;
                    break;
                default:
                    /**
                     * On se garde la possibilité d'ajouter ultérieurement
                     * d'autres propriétés accessible.
                     */
                    $objet = $this->{$prop};
            }
            return $objet;
        }
        else
        {
            throw new jemdevDbrmException(sprintf(self::ERREUR_ACCES_PROP, $prop), E_USER_NOTICE);
        }
    }

    /**
     * Initialisation d'une propriété de l'instance de la classe.
     *
     * @param   String  $prop
     * @param   Mixed   $val
     */
    public function __set($prop, $val)
    {
        if(in_array($prop, $this->_aProps['in']))
        {
            switch($prop)
            {
                case 'dbConf':
                    $this->_dbConf = $val;
                    break;
                default:
                    $this->{$prop} = $val;
            }
        }
        else
        {
            throw new jemdevDbrmException(sprintf(self::ERREUR_ACCES_PROP, $prop), E_USER_NOTICE);
        }
    }

    /**
     * Appel dynamique de méthodes de classes.
     *
     * @param   String  $methode
     * @param   Array   $parametres
     * @return  Mixed
     */
    public function __call($methode, $parametres)
    {
        $instance = false;
        if(method_exists($this, $methode))
        {
            switch ($methode)
            {
                case 'table':
                    if(isset($this->_dbConf))
                    {
                        $oT = new table($this->_dbConf['schema']['name'], $parametres[0], $this->_dbConf);
                        $instance = $oT->getInstance();
                    }
                    break;
                default:
                    $instance = call_user_func($methode, $parametres);
            }
        }
        else
        {
            throw new jemdevDbrmException(sprintf(self::ERREUR_CALL_METHODE, $methode), E_USER_NOTICE);
        }
        return $instance;
    }

    /**
     * Lancement de la génération du fichier de configuration de base de données.
     *
     * @param String    $rep_conf       Chemin vers le répertoire de stockage du fichier généré
     * @param String    $schema         Nom du schéma dont on veut la description
     * @param String    $schemauser     Nom d'utilisateur pouvant accéder à information_schema (mode production)
     * @param String    $schemamdp      Mot de passe de l'utilisateur (mode production)
     * @param String    $rootuser       Nom de l'utilisateur root qui doit se connecter à information_Schema
     * @param String    $rootmdp        Mot-de-passe de l'utilisateur root qui doit se connecter à information_Schema
     * @param String    $typeserveur    Type de serveur (MySQL, [Oracle ?], pgSql, etc...)
     * @param String    $host           Serveur où est situé information_schema
     * @param Int       $port           Port à utiliser pour la connexion au serveur où est information_schema
     * @param String    $schemauserdev  Nom d'utilisateur du schéma à décrire (mode développement)
     * @param String    $schemamdpdev   Mot de passe de l'utilisateur du schéma à décrire (mode développement)
     * @return  Boolean
     */
    public static function setFichierConf(
        $rep_conf,
        $schema,
        $schemauser,
        $schemamdp,
        $rootuser       = 'root',
        $rootmdp        = '',
        $typeserveur    = 'mysql',
        $host           = 'localhost',
        $port           = null,
        $schemauserdev  = null,
        $schemamdpdev   = null
    )
    {
        defined("DS")
            || define("DS",            DIRECTORY_SEPARATOR);
        $rep_db = realpath(dirname(__FILE__));
        $sPathExecute = $rep_db . DS .'dbExecute.php';
        $sPathVue     = $rep_db . DS .'dbVue.php';
        $sFichierConf = $rep_conf . DS .'dbConf.php';
        /**
         * On établit la connexion.
         * Pour l'instant, on tapera uniquement sur MySQL donc ce sera dans
         * INFORMATION_SCHEMA. Ultérieurement il faudra développer les
         * requêtes appropriées pour d'autres types de serveurs n'implémentant
         * pas cette règle du SQL92.
         */
        $dbConf = array(
            'schema' => array(
                'name'   => 'information_schema',
                'SGBD'   => $typeserveur,
                'server' => $host,
                'port'   => $port,
                'user'   => $rootuser,
                'mdp'    => $rootmdp,
                'pilote' => $typeserveur
            )
        );
        require_once($rep_db . DS .'init'. DS .'genereconf.php');
        require_once($sPathExecute);
        require_once($sPathVue);
        // $oVue = new vue($dbConf);
        $oVue = vue::getInstance($dbConf);

        $oConf = new genereconf(
            $schema,
            $schemauser,
            $schemamdp,
            $rootuser,
            $rootmdp,
            $typeserveur,
            $host,
            $port,
            $schemauserdev,
            $schemamdpdev
        );
        $sConf = $oConf->genererConf($oVue, $sFichierConf);
        return (false !== $sConf);
    }
}