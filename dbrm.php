<?php
namespace jemdev\dbrm;
use jemdev\dbrm\Exception;
use jemdev\dbrm\vue;
use jemdev\dbrm\table;
use jemdev\form\process\validation;
use jemdev\dbrm\init\genereconf;
/**
 * DataBase Relational Mapping class.
 *
 * Classe principale du package permettant de récupérer une instance de connexion ainsi
 * que des instances nécessaires pour toutes les opération SQL.
 *
 * @author Cyrano
 *
 */
class dbrm
{
    /**
     *
     * @var array
     */
    private $_aDbConf;
    private $_server;
    /**
     * Nom du schéma de données sur lequel on travaille.
     * @var string
     */
    private $_schema;
    private $_user;
    private $_mdp;
    private $_type;
    private $_port;
    private $_cheminRepertoireDbConf;
    private $_cheminFichierDbConf;
    /**
     * Instance de la classe jemdev\dbrm\vue
     *
     * Note importante : il faut interprêter le mot « vue » au sens SQL du terme et non
     * par rapport à une méthode d'affichage quelconque.
     *
     * @var jemdev\dbrm\vue
     */
    private $_oVue;

    /**
     * Constructeur : point d'accès unique du package d'accès aux données.
     *
     * @param   string  $server     Adresse du serveur, Ex. « localhost »
     * @param   string  $schema     Nom du schéma de données dont on va définir la configuration
     * @param   string  $user       Utilisateur pouvant se connecter, Ex. « root »
     * @param   string  $mdp        Mot-de-Passe de l'utilisateur
     * @param   string  $type       Type de SGBDR, Ex. « mysql »
     * @param   string  $port       Port sur lequel doit être effectuée la connexion, Ex. « 3306 »
     */
    public function __construct($server, $schema, $user, $mdp, $type = 'mysql', $port = '3306')
    {
        $this->_server  = $server;
        $this->_schema  = $schema;
        $this->_user    = $user;
        $this->_mdp     = $mdp;
        $this->_type    = $type;
        $this->_port    = $port;
        $this->_cheminRepertoireDbConf = realpath(__DIR__ . DIRECTORY_SEPARATOR .'conf'. DIRECTORY_SEPARATOR);
        $this->_cheminFichierDbConf = $this->_cheminRepertoireDbConf .'dbConf.php';
    }

    /**
     * Récupération d'une instance de ligne de table permettant d'y effectuer des écritures.
     * @param   string  $nomTable
     * @return  jemdev\dbrm\ligneInstance ou FALSE si le fichier de configuration n'est pas défini.
     */
    public function getLigneInstance($nomTable)
    {
        /**
         * Chargement du système d'accès aux données et aux objets.
         * Attention au chemin, il est relatif au fichier courant.
         */
        if(!is_null($this->_aDbConf))
        {
            $oTable = new table($this->_schema, $nomTable, $this->_aDbConf);
            $objet  = $oTable->getInstance();
        }
        else
        {
            $objet = false;
        }
        return $objet;
    }

    /**
     * Récupération de l'instance d'accès aux données.
     *
     * @return jemdev\dbrm\vue
     */
    public function getVueInstance()
    {
        if(is_null($this->_oVue))
        {
            $this->_oVue = vue::getInstance($this->_aDbConf[0]);
        }
        return($this->_oVue);
    }

    /**
     * Ré-initialisation du fichier de configuration du schéma de données.
     * @param   string  $cheminRepertoireConf   Chemin absolu vers le répertoire où sera stocké le ficher de configuration
     * @return  boolean
     */
    public function resetDbConf($cheminRepertoireConf)
    {
        if(is_null($this->_oVue))
        {
            $this->getVueInstance();
        }
        $oConfGenerator = new genereconf($this->_schema, $this->_user, $this->_mdp, $this->_user, $this->_mdp, $this->_type, $this->_server, $this->_port);
        $bSetConf = $oConfGenerator->genererConf($this->_oVue, $this->_cheminFichierDbConf);
        return($bSetConf);
    }

    /**
     * [Re]définit le chemin vers le fichier de configuration du schéma de données.
     *
     * Si le répertoire n'existe pas, une exception sera levée.
     * Si le fichier n'existe pas, il sera créé.
     *
     * @param unknown $cheminRepertoireConf
     * @throws Exception
     */
    public function setCheminFichierConf($cheminRepertoireConf)
    {
        $cheminRep = preg_replace("#/#", DIRECTORY_SEPARATOR, $cheminRepertoireConf);
        $this->_cheminRepertoireDbConf = rtrim($cheminRep, DIRECTORY_SEPARATOR);
        if(file_exists($this->_cheminRepertoireDbConf) && is_dir($this->_cheminRepertoireDbConf))
        {
            $this->_cheminFichierDbConf = $this->_cheminRepertoireDbConf  . DIRECTORY_SEPARATOR . 'dbConf.php';
        }
        else
        {
            throw new Exception("Le répertoire de stockage du fichier de configuration est introuvable", E_USER_ERROR);
        }
        if(is_null($this->_cheminFichierDbConf) || !file_exists($this->_cheminFichierDbConf))
        {

            $this->_aDbConf = array(
                0 => array(
                    'schema' => array(
                        'name'   => $this->_schema,
                        'SGBD'   => $this->_type,
                        'server' => $this->_server,
                        'port'   => $this->_port,
                        'user'   => $this->_user,
                        'mdp'    => $this->_mdp,
                        'pilote' => $this->_type
                    ),
                    'tables' => array(),
                    'relations' => array(),
                    'vues' => array()
                )
            );
            $this->_oVue = $this->getVueInstance();
            $this->resetDbConf($cheminRepertoireConf);
        }
        else
        {
            include($this->_cheminFichierDbConf);
            $this->_aDbConf = $dbConf;
        }
    }

    public function startTransaction()
    {
        if(is_null($this->_oVue))
        {
            $this->getVueInstance();
        }
        $this->_oVue->startTransaction();
    }
    public function finishTransaction($bOk)
    {
        if(is_null($this->_oVue))
        {
            $this->getVueInstance();
        }
        $this->_oVue->finishTransaction($bOk);
    }
}
