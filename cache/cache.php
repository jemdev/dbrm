<?php
namespace jemdev\dbrm\cache;
use jemdev\dbrm\cache\parseSelect;
use jemdev\dbrm\registre;

/**
 * @package     jem
 *
 * Ce code est fourni tel quel sans garantie.
 * Vous avez la liberté de l'utiliser et d'y apporter les modifications
 * que vous souhaitez. Vous devrez néanmoins respecter les termes
 * de la licence CeCILL dont le fichier est joint à cette librairie.
 * {@see http://www.cecill.info/licences/Licence_CeCILL_V2-fr.html}
 */
require(__DIR__ . DIRECTORY_SEPARATOR .'parseSelect.php');
/**
 * On définit une constante si elle ne l'a pas été dans un fichier de configuration chargé
 * en amont. Pour le cas où plusieurs copies de la même application tournent sur le serveur,
 * on évite ainsi de mélanger les données entre les copies.
 * Il est donc très vivement conseillé de définir cette constante dans le fichier de
 * configuration de l'application.
 * Cette constante indique le nom du schéma de données sur lequel on travaille.
 */
defined('DB_APP_SCHEMA') || define('DB_APP_SCHEMA', 'mydb');

/**
 * On définit trois constantes si elle ne l'ont pas été dans un fichier de configuration chargé
 * en amont.
 * Il est donc très vivement conseillé de définir ces constante dans le fichier de
 * configuration de l'application.
 * Ces constante indiquent :
 * - le chemin vers le répertoire où est situé le fichier de configuration.
 * - le nom du fichier de configuration du schéma de données.
 * - le chemin du répertoire de stockage des fichiers de cache.
 */
defined("CONF")          || define("CONF",                   "config". DIRECTORY_SEPARATOR);
defined("DB_CONF")       || define("DB_CONF",                'dbconf.php');
defined("DB_CACHE")      || define("DB_CACHE",               "db_cache". DIRECTORY_SEPARATOR);

/**
 * Gestion du cache de données.
 *
 * Cette classe gère l'écriture et le suivi d'un cache de requêtes SQL SELECT.
 * Ça n'a d'utilité que si on a aucun accès aux paramètres de configuration de
 * la base de données permettant d'utiliser la gestion de cache intégrée.
 *
 * Attention toutefois à un détail important : si les données de la base
 * sont modifiées par un autre accès que l'application, le cache pourrait
 * se révéler erroné voire handicapant. Ainsi par exemple, les procédures
 * stockées qui affectent les données d'une ou de plusieurs tables ne mettront
 * pas à jour le cache géré par cette classe.
 * Il convient donc de bien maitriser le fonctionnement des manipulations de
 * données de façon à désactiver la mise en cache dans certains cas ou encore
 * à forcer la mise à jour du cache sur certaines tables spécifiques.
 * Exemple : une requête est effectuée sur une table qui comporte un
 * trigger et ce trigger appelle une procédure stockée qui modifie les
 * données d'une autre table : il faut alors déclencher la mise à jour
 * du cache des données issues en tout ou partie de cette autre table.
 * La méthode cleanCacheTable() est prévue à cet effet.
 *
 * @author      Jean Molliné <jmolline@jem-dev.com.com>
 * @package     jemdev
 * @subpackage  dbrm
 */
class cache
{
    const CLE_REGISTRE_MEMCACHED = 'oMemcached';
    private $_repertoireCache;
    private $_validite;
    private $_fichierInfosCache;
    private $_aInfosCache;
    private $_dbVues;
    /**
     * Instance de jemdev\dbrm\cache\parseSelect
     * Objet analysant les requêtes SELECT pour gérer le cache de requête.
     * @var jemdev\dbrm\cache\parseSelect
     */
    private $_oSelectParser;
    /**
     * Instance de Memcache
     * @var Memcached
     */
    private $_oMemcached;
    /**
     * Constructeur.
     *
     * Définit le répertoire de stockage et la durée de conservation des informations.
     * @param String    $repCache            Chemin absolu vers le répertoire de stockage du cache de requêtes;
     * @param Int        $duree                Durée de validité en seconde des fichiers de cache. Infini si valeur à 0;
     * @param String    $fichierInfosCache    Chemin absolu vers le fichier de description du stockage de cache.
     * @param Array     $dbVues             Liste des vues SQL, tableaux de résultats de requêtes.
     */
    public function __construct(string $repCache = './cache', int $duree = 3600, string $fichierInfosCache = './infosCache.php', array $dbVues = array())
    {
        $this->_repertoireCache   = rtrim($repCache, DIRECTORY_SEPARATOR);
        $this->_validite          = $duree;
        $this->_fichierInfosCache = $fichierInfosCache;
        $this->_dbVues            = $dbVues;
        $this->_oSelectParser     = new parseSelect();
    }

    /**
     * Enregistre en cache le résultat d'une requête SQL.
     * La requête elle-même est hachée afin de servir de nom de fichier et les
     * données seront sérialisées pour être enregistrées sous la forme d'une
     * fichier texte.
     *
     * @param  String     $requete
     * @param  Array     $resultat
     * @return Bool
     */
    public function setCache(string $requete, array $resultat): bool
    {
        $retour = false;
        $key     = md5($requete);
        $fichier = $this->_repertoireCache . DIRECTORY_SEPARATOR . $key .".cache";
        $infos = serialize($resultat);
        if((CACHE_TYPE == 'file' || CACHE_TYPE == 'all') && false != $infos && false != ($f = fopen($fichier, 'w')))
        {
            $s = fwrite($f, $infos);
            fclose($f);
        }
        elseif((CACHE_TYPE == 'memcached' || CACHE_TYPE == 'all') && $this->_oMemcached instanceof \Memcached)
        {
            $this->_oMemcached->set(DB_APP_SCHEMA . $key, $infos, VALIDE_DBCACHE);
        }
        $this->_setInfosCache($requete);
        return($retour);
    }

    /**
     * Récupération des données pour une requête donnée.
     *
     * Si les données sont trouvées et qu'elles sont toujours valides selon la
     * durée définie, elles seront désérialisées et retournées, sinon, on retournera
     * FALSE indiquant par là que la requête doit être à nouveau exécutée.
     *
     * @param  String $requete
     * @return Array
     */
    public function getCache(string $requete): array
    {
        $retour = false;
        $key     = md5($requete);
        $fichier = $this->_repertoireCache . DIRECTORY_SEPARATOR . md5($requete) .".cache";
        if((CACHE_TYPE == 'memcache' || CACHE_TYPE == 'all') && $this->_oMemcached instanceof \Memcached)
        {
            $datas = $this->_oMemcached->get(DB_APP_SCHEMA . $key);
            $retour = (false !== $datas) ? unserialize($datas) : $datas;
        }
        if((CACHE_TYPE == 'file' || CACHE_TYPE == 'all') && false === $retour && file_exists($fichier))
        {
            $dateFichier = filemtime($fichier);
            $now          = time();
            if(($this->_validite > 0 && (($now - $dateFichier) < $this->_validite)) || $this->_validite == 0)
            {
                if(false != ($f = fopen($fichier, 'r')))
                {
                    $s = fread($f, filesize($fichier));
                    $retour = unserialize($s);
                }
            }
            else
            {
                $this->resetCache($requete);
            }
        }
        return($retour);
    }

    /**
     * Régénération du cache.
     *
     * On supprime le contenu du cache, forçant de ce fait à ré-exécuter les
     * requêtes. Si on indique une requête spécifique, alors seuls le résultat
     * y correspondant sera effacé.
     * Il sera avisé de régénérer le cache lors de l'insertion de nouvelles
     * données ou encore de mise à jour de celles-ci.
     *
     * @param  String $requete
     * @return Bool
     */
    public function resetCache(string $requete = null): bool
    {
        $retour = true;
        if(!is_null($requete))
        {
            $sqlKey = md5($requete);
            /* Identification des tables concernées */
            $this->_oSelectParser->setNewQuery($requete);
            $infos = $this->_oSelectParser->getTables();
            if(file_exists($this->_fichierInfosCache))
            {
                $aTmpKeys = array();
                $aCacheRequetes = $this->_getInfosStockage();
                $aInfosCache = array();
                foreach($aCacheRequetes AS $k => $v)
                {
                    foreach($infos as $inf)
                    {
                        foreach($inf['tables'] as $table)
                        {
                            if(in_array($table, $v['tables']))
                            {
                                /* Si un fichier de cache existe avec cette table, on le supprime */
                                $suppr = $this->_delKeyCache($k);
                                if(false === $suppr)
                                {
                                    $retour = false;
                                    break(3);
                                }
                                /* On met à jour les informations de cache */
                                $aTmpKeys[] = $k;
                            }
                        }
                    }
                }
                foreach($aCacheRequetes AS $k => $v)
                {
                    if(!in_array($k, $aTmpKeys))
                    {
                        $aInfosCache[$k] = $v;
                    }
                }
                $this->_writeInfosCache($aInfosCache);
            }
        }
        else
        {
            if($this->_oMemcached instanceof \Memcached)
            {
                $suppression = $this->_oMemcached->flush();
            }
            elseif(defined('MEMCACHE_ACTIF') && true === MEMCACHE_ACTIF)
            {
                $this->_oMemcached = registre::get(self::CLE_REGISTRE_MEMCACHED);
                $suppression = $this->_oMemcached->flush();
            }
            if(false !== ($d = opendir(DB_CACHE)))
            {
                $typefichier = "#([^\.]+)\.cache#";
                while(false != ($fichier = readdir($d)))
                {
                    if($fichier != '.' && $fichier != '..' && (preg_match($typefichier, $fichier) || $fichier == 'infosCacheSql.php'))
                    {
                        if($fichier == 'infosCacheSql.php')
                        {
                            unlink(DB_CACHE . DS . $fichier);
                        }
                        else
                        {
                            $k = preg_replace($typefichier, "$1", $fichier);
                            $suppr = $this->_delKeyCache($k);
                            if(false === $suppr)
                            {
                                $retour = false;
                                break;
                            }
                        }
                    }
                }
            }
        }
        return($retour);
    }

    /**
     * Nettoyage du cache comportant des données d'une table spécifique.
     *
     * La méthode va analyser le fichier d'information de stockage et identifier
     * les fichiers où cette table apparaît. Les fichiers correspondant seront
     * détruits et le fichier d'information sera ré-écrit.
     *
     * @param String $table    Nom de la table ayant reçu une écriture.
     * 
     * @return bool
     */
    public function cleanCacheTable(string $table): bool
    {
        $retour = true;
        $tmpInfosCache = array();
        $aInfosCache = $this->_getInfosStockage();
        /**
         * On commence par faire sauter toutes les lignes où une occurence de
         * la table concernée apparaît.
         */
        foreach($aInfosCache as $k => $ligne)
        {
            if(!in_array($table, $ligne['tables']))
            {
                $tmpInfosCache[$k] = $ligne;
            }
            else
            {
                $fCache = $this->_repertoireCache . DS . $k .'.cache';
                /**
                 * On fait sauter les fichiers absents du fichier ré-écrit.
                 */
                $suppr = $this->_delKeyCache($k);
                if(false === $suppr)
                {
                    $retour = false;
                    break;
                }
            }
        }
        /**
         * Lancement de la ré-écriture du fichier d'information sur le cache existant.
         */
        if(false !== $retour)
        {
            $retour = $this->_writeInfosCache($tmpInfosCache);
        }
        return($retour);
    }

    /**
     * Activation du stockage de cache dans memcached.
     *
     * @param Memcached $oMemcached
     * @param string $server
     * @param int $port
     * 
     * @return void
     */
    public function activerMemcache(\Memcached $oMemcached, string $server = 'localhost', int $port = 11211): void
    {
        $this->_oMemcached = $oMemcache;
        $oMemcache->addServer($server, $port);
    }

    /**
     * @param string $requete
     * 
     * @return void
     */
    private function _setInfosCache(string $requete): void
    {
        $tmpInfosCache = array();
        if(file_exists($this->_fichierInfosCache))
        {
            $tmpInfosCache = $this->_getInfosStockage();
        }
        foreach($tmpInfosCache AS $info)
        {
            $this->_oSelectParser->setNewQuery($info['sql']);
        }
        $this->_oSelectParser->setNewQuery($requete);
        $infos = $this->_oSelectParser->getTables();
        foreach($infos as $k => $v)
        {
            $aTables = array();
            foreach($v['tables'] as $t)
            {
                if(!array_key_exists($t, $this->_dbVues))
                {
                    $aTables[] = $t;
                }
                else
                {
                    foreach($this->_dbVues[$t] as $tv)
                    {
                        $aTables[] = $tv;
                    }
                }
            }
            $listeTables = array_unique($aTables);
            if(!array_key_exists($k, $tmpInfosCache))
            {
                $tmpInfosCache[$k] = array(
                    'sql'    => $v['sql'],
                    'tables' => $listeTables
                );
            }
        }
        $ecriture = $this->_writeInfosCache($tmpInfosCache);
    }

    /**
     * @param array $aInfosCache
     * 
     * @return bool
     */
    private function _writeInfosCache(array $aInfosCache): bool
    {
        $retour = false;
        include(__DIR__ . DIRECTORY_SEPARATOR .'infosCache.tpl');
        $sLignes = '';
        foreach($aInfosCache as $fichier => $infos)
        {
            $sTables = '';
            foreach($infos['tables'] as $t => $table)
            {
                $sTables .= sprintf($sLigneTable, $t, $table);
            }
            $sLignes .= sprintf(
                $sLigneTableau,
                $fichier,
                $infos['sql'],
                $sTables
            );
        }
        $infosCache = sprintf($sContenu, $sLignes);
        if(false !== ($f = fopen($this->_fichierInfosCache, 'w')))
        {
            fwrite($f, $infosCache);
            if(true === fclose($f))
            {
                $this->_aInfosCache = array();
                $retour = true;
            }
        }
        return($retour);
    }

    /**
     * @return array
     */
    private function _getInfosStockage(): array
    {
        $tmpInfosCache = array();
        $aCacheRequetes = array();
        if(file_exists($this->_fichierInfosCache))
        {
            $lf = filesize($this->_fichierInfosCache);
            $ftmp = fopen($this->_fichierInfosCache, 'r');
            $str = '';
            if(false !== ($r = file($this->_fichierInfosCache, FILE_SKIP_EMPTY_LINES)))
            {
                foreach($r as $ligne)
                {
                    $str .= $ligne;
                }
                if(!empty($str))
                {
                    eval($str);
                    $tmpInfosCache = $aCacheRequetes;
                }
            }
            fclose($ftmp);
        }
        return($tmpInfosCache);
    }

    /**
     * @param string $k
     * 
     * @return bool
     */
    private function _delKeyCache(string $k): bool
    {
        $retour = true;
        if($this->_oMemcached instanceof \Memcached)
        {
            if(false !== ($c = $this->_oMemcached->get(DB_APP_SCHEMA . $k)))
            {
                $key = DB_APP_SCHEMA . $k;
                $retour = $this->_oMemcached->delete($key);
            }
        }
        elseif(defined('MEMCACHE_ACTIF') && true === MEMCACHE_ACTIF)
        {
            $this->_oMemcached = registre::get(self::CLE_REGISTRE_MEMCACHED);
            $retour = $this->_oMemcached->delete($k);
        }
        $fichier = $this->_repertoireCache . DIRECTORY_SEPARATOR . $k .".cache";
        if(false !== $retour && file_exists($fichier))
        {
            if(true !== unlink($fichier))
            {
                $retour = false;
            }
        }
        return($retour);
    }

    /**
     * @return string
     */
    public function getCleRegistreMemcached(): string
    {
        return self::CLE_REGISTRE_MEMCACHED;
    }
}