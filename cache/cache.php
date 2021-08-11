<?php
namespace jemdev\dbrm\cache;
use jemdev\dbrm\cache\parseSelect;
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
 * @author      Jean Molliné <jmolline@gmail.com>
 * @package     jemdev
 * @subpackage  dbrm
 */
class cache
{
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
     * @var Memcache
     */
    private $_oMemcache;
    /**
     * Constructeur.
     *
     * Définit le répertoire de stockage et la durée de conservation des informations.
     * @param String    $repCache            Chemin absolu vers le répertoire de stockage du cache de requêtes;
     * @param Int        $duree                Durée de validité en seconde des fichiers de cache. Infini si valeur à 0;
     * @param String    $fichierInfosCache    Chemin absolu vers le fichier de description du stockage de cache.
     * @param Array     $dbVues             Liste des vues SQL, tableaux de résultats de requêtes.
     */
    public function __construct($repCache = './cache', $duree = 3600, $fichierInfosCache = './infosCache.php', $dbVues = array())
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
     * @return Boolean
     */
    public function setCache($requete, $resultat)
    {
        $retour = false;
        $key     = md5($requete);
        $fichier = $this->_repertoireCache . DIRECTORY_SEPARATOR . $key .".cache";
        $infos = serialize($resultat);
        if((CACHE_TYPE == 'memcache' || CACHE_TYPE == 'all') && $this->_oMemcache instanceof \Memcache)
        {
            $this->_oMemcache->set(DB_APP_SCHEMA . $key, $infos, MEMCACHE_COMPRESSED, VALIDE_DBCACHE);
        }
        elseif((CACHE_TYPE == 'file' || CACHE_TYPE == 'all') && false != $infos && false != ($f = fopen($fichier, 'w')))
        {
            $s = fwrite($f, $infos);
            fclose($f);
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
    public function getCache($requete)
    {
        $retour = false;
        $key     = md5($requete);
        $fichier = $this->_repertoireCache . DIRECTORY_SEPARATOR . md5($requete) .".cache";
        if((CACHE_TYPE == 'memcache' || CACHE_TYPE == 'all') && $this->_oMemcache instanceof \Memcache)
        {
            $datas = $this->_oMemcache->get(DB_APP_SCHEMA . $key);
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
     * @return Boolean
     */
    public function resetCache($requete = null)
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
            if($this->_oMemcache instanceof \Memcache)
            {
                $suppression = $this->_oMemcache->flush();
            }
            elseif(defined('MEMCACHE_ACTIF') && true === MEMCACHE_ACTIF)
            {
                $this->_oMemcache = Hoa\Registry\Registry::get('oMemcache');
                $suppression = $this->_oMemcache->flush();
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
     */
    public function cleanCacheTable($table)
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
     * @param Memcache $oMemcache
     */
    public function activerMemcache(\Memcache $oMemcache, $server = 'localhost', $port = 11211)
    {
        $this->_oMemcache = $oMemcache;
        $oMemcache->addServer($server, $port);
    }

    private function _setInfosCache($requete)
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

    private function _writeInfosCache($aInfosCache)
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

    private function _getInfosStockage()
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

    private function _delKeyCache($k)
    {
        $retour = true;
        if($this->_oMemcache instanceof \Memcache)
        {
            if(false !== ($c = $this->_oMemcache->get(DB_APP_SCHEMA . $k)))
            {
                $key = DB_APP_SCHEMA . $k;
                $retour = $this->_oMemcache->delete($key);
            }
        }
        elseif(defined('MEMCACHE_ACTIF') && true === MEMCACHE_ACTIF)
        {
            $this->_oMemcache = Hoa\Registry\Registry::get('oMemcache');
            $retour = $this->_oMemcache->delete($key);
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
}