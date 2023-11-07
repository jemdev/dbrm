<?php
namespace jemdev\dbrm\abstr;
use jemdev\dbrm\jemdevDbrmException;
use jemdev\dbrm\cache\cache;
use jemdev\dbrm\registre;
/**
 * @package     jemdev
 *
 * Ce code est fourni tel quel sans garantie.
 * Vous avez la liberté de l'utiliser et d'y apporter les modifications
 * que vous souhaitez. Vous devrez néanmoins respecter les termes
 * de la licence CeCILL dont le fichier est joint à cette librairie.
 * {@see http://www.cecill.info/licences/Licence_CeCILL_V2-fr.html}
 *
 * Note sur les constantes définies en début de fichier :
 * Ces constantes proposent des valeurs par défaut. Il est tout à fait possible de les
 * définir avec d'autres valeurs dans un fichier de configuration propre à
 * l'application dans la mesure où ce dernier est chargé avant cette classe.
 */
/* Active ou non la mise en cache des résultats de requêtes SQL */
defined('DBCACHE_ACTIF')    || define('DBCACHE_ACTIF',  false);
/* Durée de validité des données mises en cache (en secondes), infini si la valeur vaut zéro */
defined('VALIDE_DBCACHE')   || define('VALIDE_DBCACHE', 60);
/* Fichier de stockage des informations sur le cache des requêtes SQL */
defined('INFOS_DBCACHE')    || define('INFOS_DBCACHE', __DIR__ . DIRECTORY_SEPARATOR .'infosCacheSql.php');
/* Activation de l'utilisation de MEMCACHE */
defined('MEMCACHE_ACTIF')   || define('MEMCACHE_ACTIF', false);
defined('MEMCACHE_SERVER')  || define('MEMCACHE_SERVER', 'localhost');
defined('MEMCACHE_PORT')    || define('MEMCACHE_PORT',  11211);
/**
 * Type de stockage du cache : file, memcached ou all.
 * Attention, si on utilise uniquement memcache, les valeurs seront
 * effacées en cas de redémarrage du serveur.
 */
defined('CACHE_TYPE')       || define('CACHE_TYPE',     'file');

/**
 * Classe abstraite d'exécution SQL
 *
 * @author      Jean Molliné <jmolline@jem-dev.com>
 * @package     jemdev
 * @subpackage  dbrm
 */
abstract class execute
{
    const WHERE_AND                 = 'AND' ;
    const WHERE_OR                  = 'OR' ;
    private $_schema;
    private $_server;
    private $_user;
    private $_mdp;
    protected $_lastId;
    private $_cnx;
    private $_dbh;
    protected $_bTransaction        = false;
    protected $_aDbErreurs          = array();
    protected $_aConfig;
    /**
     * Indique si une connexion existe déjà;
     *
     * @var Boolean
     */
    protected $_bConnecte           = false;
    /**
     * Instance de jem\db\cache
     *
     * @var jem\db\cache
     */
    public $_oCache;
    /**
     * Cache des requêtes activé ou non.
     * Point de repère indépendant de la constante de l'application.
     *
     * Permet de désactiver le cache temporairement pour certaines requêtes lorsque
     * nécessaire, la constante servant à rétablir le satut normal de l'application.
     * @var Boolean
     */
    protected $_bCacheRequetes      = false;
    /**
     * Liste des tables par vue.
     * Pour la gestion du cache, si une de ces tables reçoit une écriture, le
     * cache de données devra être renouvelé.
     * @var Array
     */
    protected $_aTablesVues         = array();
    /**
     * Indication permettant ou non la mise en cache du résultat si le
     * cache de requête est activé.
     *
     * On peut indiquer pour certaines requêtes de ne pas mettre en cache
     * le résultat de façon d'abord à forcer la ré-exécution d'une requête
     * à chaque demande et ensuite par sécurité pour éviter de stocker une
     * information qui pourrait être accessible, fût-ce accidentellement,
     * voire volontairement pas un individu malveillant.
     *
     * Utilisation : il suffit simplement lors de l'appel de la méthode
     * jemdev\dbrm\vue::setRequête() d'envoyer FALSE en troisième paramètre.
     *
     * @var Boolean
     */
    protected $_bCacheResultat;

    /**
     * Liste des vues de la base de données utilisée.
     * Cette liste est nécessaire pour la gestion du cache de requête.
     *
     * @var Array
     */
    private $_aListeVues = array();

    /**
     * @param array $dbConf
     * @TODO remplacer Memcached
     */
    protected function __construct(array $dbConf)
    {
        $this->_aConfig = $dbConf;
        if(true === DBCACHE_ACTIF && false !== $this->_bCacheResultat)
        {
            if(defined('DB_CACHE') && file_exists(DB_CACHE))
            {
                $this->_aListeVues = array();
                foreach($this->_aConfig['vues'] as $vue => $infos)
                {
                    $this->_aListeVues[$vue] = $infos['tables'];
                }
                $this->_oCache = new cache(DB_CACHE, VALIDE_DBCACHE, INFOS_DBCACHE, $this->_aListeVues);
                $cleMemcached = $this->_oCache->getCleRegistreMemcached();
                $oMemcached = registre::isRegistered($cleMemcached) ? registre::get($cleMemcached) : false;
                if(true == MEMCACHE_ACTIF)
                {
                    if(false === $oMemcached && class_exists('Memcached', true))
                    {
                        $oMemcached = new \Memcached;
                        registre::set($cleMemcached, $oMemcached);
                    }
                    $this->_oCache->activerMemcache($oMemcached, MEMCACHE_SERVER, MEMCACHE_PORT);
                }
                $this->_bCacheRequetes = true;
            }
            else
            {
                $this->_bCacheRequetes = false;
                throw new jemdevDbrmException("La constante de configuration « DB_CACHE » indiquant le chemin vers le répertoire de mise en cache n'a pas été défini.", E_USER_ERROR);
            }
        }
    }

    /**
     * Établissement de la connexion au SGBD
     * 
     * @param array $aInfosCnx
     * 
     * @return void
     */
    protected function _connect(array $aInfosCnx): void
    {
        if(false === (registre::isRegistered('dbCnx')))
        {
            $dns  = $aInfosCnx['pilote'];
            $dns .= ':host=' . $aInfosCnx['server'];
            $dns .= ((!empty($aInfosCnx['port'])) ? (';port=' . $aInfosCnx['port']) : '');
            $dns .= ';dbname=' . $aInfosCnx['name'];
            $options = ($aInfosCnx['pilote'] == 'mysql') ? array(\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8;') : null;
            try
            {
                $this->_dbh = new \PDO($dns, $aInfosCnx['user'], $aInfosCnx['mdp'], $options);
                registre::set('dbCnx', $this->_dbh);
                $this->_dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $this->_bConnecte = true;
            }
            catch (\PDOException $p)
            {
                $this->_bConnecte = false;
                $this->_aDbErreurs[] = array(
                    'La connexion a échoué',
                    'Message : '. $p->getMessage(),
                    'Trace : '. $p->getTraceAsString()
                );
            }
            catch (jemdevDbrmException $e)
            {
                $this->_bConnecte = false;
                $this->_aDbErreurs[] = array(
                    'La connexion a échoué',
                    'Message : '. $e->getMessage(),
                    'Trace : '. $e->getTraceAsString()
                );
            }
        }
        else
        {
            $this->_dbh = registre::get('dbCnx');
            $this->_bConnecte = true;
        }
    }

    /**
     * @param string        $sql
     * @param array|null    $params
     * @param string        $out
     * 
     * @return mixed
     */
    protected function _fetchDatas(string $sql, array $params = null, string $out = 'array'): mixed
    {
        try
        {
            $cache = false;
            $select = "#^SELECT\s.*#";
            $requete = $sql;
            $bSelect = preg_match($select, $sql);
            if($this->_oCache instanceof cache && true === $this->_bCacheRequetes && false !== $this->_bCacheResultat)
            {
                if(count($params) > 0)
                {
                    foreach ($params as $k => $val)
                    {
                        $requete = str_replace($k, "'". $val ."'", $requete);
                    }
                }
                $cache = $this->_oCache->getCache($requete);
            }
            if(false == $cache)
            {
                if(is_null($this->_dbh) || (true !== ($this->_dbh instanceof \PDO)))
                {
                    $this->_connect($this->_aConfig['schema']);
                }
                $sth = $this->_dbh->prepare($sql, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
                $sth->execute($params);
                switch ($out)
                {
                    case 'assoc':
                        $sortie = \PDO::FETCH_ASSOC;
                        break;
                    case 'num':
                        $sortie = \PDO::FETCH_NUM;
                        break;
                    case 'object':
                        $sortie = \PDO::FETCH_OBJ;
                        break;
                    case 'one':
                        $sortie = \PDO::FETCH_COLUMN;
                        break;
                    case 'array':
                    case 'line':
                    default:
                        $sortie = \PDO::FETCH_BOTH;
                }
                if($out != 'one' && $out != 'line')
                {
                    $result = $sth->fetchAll($sortie);
                }
                else
                {
                    $result = $sth->fetch($sortie);
                }
                if($this->_oCache instanceof cache && true === $this->_bCacheRequetes)
                {
                    $this->_oCache->setCache($requete, $result);
                }
            }
            else
            {
                $result = $cache;
            }
        }
        catch (\PDOException $e)
        {
            $result = $e->getMessage() ."<br />\n". $e->getTraceAsString();
        }
        return $result;
    }

    /**
     * @param string $sql
     * @param array $params
     * 
     * @return array
     */
    protected function _fetchAssoc(string $sql, array $params): array
    {
        $result = $this->_fetchDatas($sql, $params, 'assoc');
        return $result;
    }

    /**
     * @param string $sql
     * @param array $params
     * 
     * @return array
     */
    protected function _fetchNum(string $sql, array $params): array
    {
        $result = $this->_fetchDatas($sql, $params, 'num');
        return $result;
    }

    /**
     * @param string $sql
     * @param array $params
     * 
     * @return object
     */
    protected function _fetchObject(string $sql, array $params): object
    {
        $result = $this->_fetchDatas($sql, $params, 'object');
        return $result;
    }

    /**
     * @param string $sql
     * @param array $params
     * 
     * @return mixed
     */
    protected function _fetchOne(string $sql, array $params): mixed
    {
        $result = $this->_fetchDatas($sql, $params, 'one');
        return $result;
    }

    /**
     * @param string $sql
     * @param array $params
     * 
     * @return array
     */
    protected function _fetchArray(string $sql, array $params): array
    {
        $result = $this->_fetchDatas($sql, $params, 'array');
        return $result;
    }

    /**
     * @param string $sql
     * @param array $params
     * 
     * @return array
     */
    protected function _fetchLine(string $sql, array $params): array
    {
        $result = $this->_fetchDatas($sql, $params, 'line');
        return $result;
    }

    /**
     * @param string|null $col
     * 
     * @return int
     */
    protected function _getLastId(string $col = null): int
    {
        try
        {
            $result = $this->_dbh->lastInsertId($col);
        }
        catch (\PDOException $e)
        {
            $result = $e->getMessage() ."<br />\n". $e->getTraceAsString();
        }
        return $result;
    }

    protected function _execProc(string $sql, array $params): mixed
    {
        $result = false;
        $p = is_array($params) ? $params : array();
        try
        {
            $sth    = $this->_dbh->prepare($sql, array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
            try
            {
                if(count($p) > 0)
                {
                    foreach($p as $key => $val)
                    {
                        $typeData = $this->_getPDOConstantType($val);
                        $sth->bindValue($key, $val, $typeData);
                    }
                }
                $result = $sth->execute($p);
                if(false === $result)
                {
                    $result = array(
                        'codeErreur' => "Requête «". $sql ."»". PHP_EOL ."Code erreur : ". $sth->errorCode(),
                        'infos'      => $sth->errorInfo()
                    );
                    $this->_aDbErreurs[] = array(
                        "L'exécution a échoué.". PHP_EOL ."Requête : ". $sql .";". PHP_EOL ."Paramètres : ". sprintf('%s', print_r($params, true)) .";",
                        'Message : '. $sth->errorInfo(),
                        'Trace : '. debug_backtrace()
                    );
                }
            }
            catch (\PDOException $p)
            {
                $this->_aDbErreurs[] = array(
                    "L'exécution a échoué.". PHP_EOL ."Requête : ". $sql .";". PHP_EOL ."Paramètres : ". sprintf('%s', print_r($params, true)) .";",
                    'Message : '. $p->getMessage(),
                    'Trace : '.$p->getTraceAsString()
                );
            }
            catch (\Exception $e)
            {
                $this->_aDbErreurs[] = array(
                    "L'exécution a échoué.". PHP_EOL ."Requête : ". $sql ."". PHP_EOL ."Paramètres : ". sprintf('%s', print_r($params, true)) .";",
                    'Message : '. $e->getMessage(),
                    'Trace : '.$e->getTraceAsString()
                );
            }
        }
        catch (\PDOException $p)
        {
            $sqlexecute = sprintf('%s', $sth->debugDumpParams());
            $this->_aDbErreurs[] = array(
                "La préparation de  la requête ". $sql ." a échoué : \n". $sqlexecute,
                'Message : '. $p->getMessage(),
                'Trace : '.$p->getTraceAsString()
            );
        }
        catch (\Exception $e)
        {
            $sqlexecute = sprintf('%s', $sth->debugDumpParams());
            $this->_aDbErreurs[] = array(
                "La préparation de  la requête ". $sql ." a échoué : \n". $sqlexecute,
                'Message : '. $e->getMessage(),
                'Trace : '.$e->getTraceAsString()
            );
        }
        $result = (true !== $result) ? $this->_aDbErreurs : $result;
        return $result;
    }

    /**
     * Démarre une transaction.
     *
     * @return bool
     */
    protected function _debutTransaction(): bool
    {
        $retour = false;
        if(is_null($this->_dbh))
        {
            $this->_connect($this->_aConfig['schema']);
        }
        try
        {
            $this->_bTransaction = $this->_dbh->beginTransaction();
            $retour = $this->_bTransaction;
        }
        catch (\PDOException $p)
        {
            $this->_aDbErreurs[] = array(
                "L'ouverture de la transaction a échoué",
                'Message : '. $p->getMessage(),
                'Trace : '.$p->getTraceAsString()
            );
        }
        catch (jemdevDbrmException $e)
        {
            $this->_aDbErreurs[] = array(
                "L'ouverture de la transaction a échoué",
                'Message : '. $e->getMessage(),
                'Trace : '.$e->getTraceAsString()
            );
        }
        catch (\Exception $e)
        {
            $this->_aDbErreurs[] = array(
                "L'ouverture de la transaction a échoué",
                'Message : '. $e->getMessage(),
                'Trace : '.$e->getTraceAsString()
            );
        }
        return($retour);
    }

    /**
     * Annule une transaction.
     *
     * @return bool
     */
    protected function _annuleTransaction(): bool
    {
        try
        {
            $annulation = $this->_dbh->rollBack();
        }
        catch (\PDOException $p)
        {
            $annulation = false;
            $this->_aDbErreurs[] = array(
                "L'annulation d'exécution de la transaction a échoué",
                'Message : '. $p->getMessage(),
                'Trace : '.$p->getTraceAsString()
            );
        }
        catch (jemdevDbrmException $e)
        {
            $annulation = false;
            $this->_aDbErreurs[] = array(
                "L'annulation d'exécution de la transaction a échoué",
                'Message : '. $e->getMessage(),
                'Trace : '.$e->getTraceAsString()
            );
        }
        catch (\Exception $e)
        {
            $annulation = false;
            $this->_aDbErreurs[] = array(
                "L'annulation d'exécution de la transaction a échoué",
                'Message : '. $e->getMessage(),
                'Trace : '.$e->getTraceAsString()
            );
        }
        $this->_bTransaction = false;
        return($annulation);
    }

    /**
     * Confirme et exécute une transaction.
     *
     * @return bool
     */
    protected function _confirmeTransaction(): bool
    {
        $retour = false;
        try
        {
            $this->_dbh->commit();
            $this->_bTransaction = false;
            $retour = true;
        }
        catch (\PDOException $p)
        {
            $this->_annuleTransaction();
            $this->_aDbErreurs[] = array(
                "L'exécution de la transaction a échoué",
                'Message : '. $p->getMessage(),
                'Trace : '.$p->getTraceAsString()
            );
        }
        catch (jemdevDbrmException $e)
        {
            $this->_annuleTransaction();
            $this->_aDbErreurs[] = array(
                "L'exécution de la transaction a échoué",
                'Message : '. $e->getMessage(),
                'Trace : '.$e->getTraceAsString()
            );
        }
        catch (\Exception $e)
        {
            $this->_annuleTransaction();
            $this->_aDbErreurs[] = array(
                "L'exécution de la transaction a échoué",
                'Message : '. $e->getMessage(),
                'Trace : '.$e->getTraceAsString()
            );
        }
        return $retour;
    }

    /**
     * Optimisation de la requête SQL par compactage.
     *
     * Les retours de chariot seront supprimés,
     * les espaces multiples seront réduits à un seul par groupe,
     * les espaces entourant les parenthèses ou les opérateurs seront également supprimés.
     *
     * Ainsi, une requête qui aura la forme suivante :
     * <code>
     * SELECT
     *      col_a,
     *   col_b
     * FROM table
     * WHERE col_a = 1234
     *   AND (
     *     col_c > col_d
     *     OR col_c IS NULL
     *   );
     * </code>
     * Sera retournée sous la forme :
     * <code>SELECT col_a,col_b FROM table WHERE col_a=1234 AND(col_c>col_d OR col_c IS NULL);</code>
     *
     * Le SGBD le lira plus vite en ayant pas à parser des caractères inutiles, d'autant moins si la
     * requête est longue et complexe..
     *
     * @param  string $sql
     * @return string
     */
    public function _optimiseSqlString(string $sql): string
    {
        $rc = "#(\r|\n|\r\n|". PHP_EOL .")#";
        $espaces = "#\s+#";
        $parentheses = "#\s*(\(|\)|,|/|\*|=|\+|<|>)\s*#";
        $s1 = preg_replace($rc, ' ', $sql);
        $s2 = preg_replace($espaces, ' ', $s1);
        $s3 = preg_replace($parentheses, '$1', $s2);
        return $s3;
    }

    /**
     * Destructeur.
     *
     * Fait le ménage au cas où une transaction non terminée existerait et
     * supprime l'instance de la classe.
     */
    public function __destruct()
    {
        /**
         * Si on arrive ici et qu'une transaction a été ouverte, on commence
         * par l'annuler avant de détruire l'instance.
         */
        if(true === $this->_bTransaction)
        {
            $this->_annuleTransaction();
        }
        /**
         * Destruction de l'instance.
         */
        $this->_dbh = null;
    }

    /**
     * Définition du type \PDO de données à insérer dans une colonne.
     * @param  String $value
     * @return number
     */
    private function _getPDOConstantType(string $value = null): int
    {
        if(is_null($value) || empty($value))
        {
            $type = \PDO::PARAM_NULL;
        }
        elseif(is_numeric($value))
        {
            if(is_int($value))
            {
                $type = \PDO::PARAM_INT;
            }
            else
            {
                $type = \PDO::PARAM_STR;
            }
        }
        elseif(is_bool($value))
        {
            $type = \PDO::PARAM_BOOL;
        }
        else
        {
            $type = \PDO::PARAM_STR;
        }
        return($type);
    }
}
