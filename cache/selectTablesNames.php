<?php
namespace jemdev\dbrm\cache;
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
 * Classe de parsing de requêtes SQL SELECT.
 * L'unique rôle de cette classe consiste à extraire le nom des tables
 * utilisées dans une requête SELECT.
 * Son usage dans jem\dbrm est destiné à la gestion d'un cache de requêtes
 * pouvant pallier aux mécanismes intégré dans les SGBD si on n'y a pas
 * un accès permettant d'en gérer les paramètres voire simplement l'activation.
 *
 * Son utilisation est simplifiée au maximum avec un constructeur et deux méthodes
 * publiques :
 *  - le constructeur permet d'analyser une première requête SQL;
 *  - setNewQuery() qui permet d'ajouter une autre requête dans la collection;
 *  - getTables() qui permet de récupérer les informations collectées.
 * En outre, une méthode reset() permet de vider la collection pour un nouvel
 * usage avec d'autres requêtes si nécessaire. Cette méthode est automatiquement
 * utilisée lorsqu'on appelle getTables().
 *
 * @author      Jean Molliné <jmolline@jem-dev.com>
 * @since       PHP 5.x.x
 * @package     jemdev
 * @subpackage  dbrm
 * @version     1.0a
 */
class selectTablesNames
{
    private static $_aSqlQueries = array();
    private $_query;
    private static $_hash;

    /**
     * Constructeur.
     * Le paramètre est facultatif. On peut l'utiliser lorsqu'on a juste une
     * seule requête à analyser, auquel cas, on peut tout de suite après appeler
     * la méthode getTables.
     * 
     * @param string|null $query Requête SQL SELECT (optionnel)
     * 
     * @return [type]
     */
    public function _construct(string $query = null)
    {
        if(!is_null($query))
        {
            $this->_init($query);
        }
    }

    /**
     * Lancement automatique du traitement de la requête.
     *
     * La requête est au préalable légèrement modifiée : les jointures sont
     * en quelque sorte normalisées et les « JOIN » non précédés d'un des
     * mots-clés habituellement utilisés sont remplacés par un
     * « INNER JOIN ».
     */
    private function _init(string $query): void
    {
        $this->_query = preg_replace("#(?<!CROSS\s|LEFT\s|RIGHT\s|INNER\s|OUTER\s|NATURAL)JOIN#i", "INNER JOIN", $query);
        self::$_hash = md5($query);
        self::$_aSqlQueries[self::$_hash] = array('query' => $query, 'tables' => array());
        $this->_parseQuery();
    }

    /**
     * Méthode de callback appelée depuis la méthode _parseQuery.
     * Cette méthode va terminer le travail et stocker la liste des tables
     * dans la propriété de stockage self::$_aSqlQueries.
     */
    private static function _blocTablesSplit(array $captures): void
    {
        preg_match_all('#(?:^|,\s)+(?:\(*`?\w+`?\.)?`?(\w+)`?#', $captures[1], $tableGroup);
        foreach($tableGroup[1] as $table )
        {
            self::$_aSqlQueries[self::$_hash]['tables'][] = $table;
        }
    }

    /**
     * Initialisation de l'analyse de la requête SQL.
     * La méthode va isoler certains blocs dans la requête et appeler
     * une méthode de callback _blocTablesSplit()
     */
    private function _parseQuery(): void
    {
        $masque = '#\s*(?:FROM|JOIN)\s+(.+?)\s?(?:ON(?:\s|\()|CROSS\s|LEFT\s|RIGHT\s|INNER\s|OUTER\s|NATURAL\s|JOIN\s|USING\s|GROUP\s|HAVING\s|WHERE|ORDER\s|LIMIT\s|\z)#im';
        preg_replace_callback($masque, "self::_blocTablesSplit", $this->_query);
    }

    /**
     * Ajouter une autre requête SELECT à la collection.
     * @param    String    $query Requête SQL SELECT
     */
    public function setNewQuery(string $query): void
    {
        $this->_init($query);
    }

    /**
     * Récupération des informationssous la forme d'un tableau associatif.
     * Le tableau aura la forme suivante :
     * [03331a7a9b378d8b696015cf122dd3e4] => Array(
     *      [query] => SELECT pay_id,pay_nom FROM t_pays_pay
     *      [tables] => Array(
     *          [0] => t_pays_pay
     *      )
     *  )
     * L'index racine est un hachage md5 de la requête, et le tableau
     * contient la requête elle-même et la liste des tables affectées.
     *
     * Le paramètre facultatif permet, s'il est défini à TRUE, de ne récupérer
     * que la liste des tables mise en oeuvre dans la vue et non l'ensemble
     * des informations.
     *
     * @param    Boolean $tablesOnly Ne récupérer QUE les tables (true) ou toutes les informations (false, valeur par défaut)
     */
    public function getTables(bool $tablesOnly = false): array
    {
        $aResult = array();
        foreach(self::$_aSqlQueries AS $hash => $infos)
        {
            if(true === $tablesOnly)
            {
                $aResult = array_unique($infos['tables']);
            }
            else
            {
                $aResult[$hash] = array(
                    'query' => $infos['query'],
                    'tables' => array_unique($infos['tables'])
                );
            }
        }
        $this->reset();
        return($aResult);
    }

    /**
     * Remise à vide des propriétés de l'instance.
     */
    public function reset(): void
    {
        $this->_query       = null;
        self::$_hash        = null;
        self::$_aSqlQueries = array();
    }
}