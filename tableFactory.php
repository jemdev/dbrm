<?php
namespace jemdev\dbrm;

use jemdev\dbrm\abstr\execute;
use jemdev\dbrm\jemdevDbrmException;

/**
 * Classe implémentant un objet pour une table.
 *
 * Basée sur un multiton, on peut utiliser cette classe pour
 * autant de tables que nécessaire. Si on a besoin de plusieurs
 * instances pour une même table, on créera une instance en
 * précisant un alias différent pour chaque occurence de la table.
 *
 * Créé le 17/08/2021
 * @author      Jean Molliné <jmolline@gmail.com>
 * @since       PHP 7.4.x
 * @package     jemdev
 * @subpackage  dbrm
 * @todo Implémentation d'une méthode permettant d'affecter une fonction SQL en valeur à
 *       une colonne, par exemple, pouvoir faire :
 *       $instanceLigne->nom_colonne = "AES_ENCRYPT('valeur', 'Clé de chiffrement')";
 */
class tableFactory extends execute
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
     * La structure du tableau sera donc ainsi, exemple avec la description d'une
     * table, les colonnes sont listées sous l'index « fields » :
     * 't_categorietype_cty' => array(
     *     'fields' => array(
     *         'cty_id' => array(
     *             'type'   => TYPE_INTEGER,
     *             'length' => 10,
     *             'null'   => false,
     *             'attr'   => array(
     *                 'extra' => 'auto_increment'
     *             )
     *         ),
     *         'cty_type' => array(
     *             'type'   => TYPE_ENUM,
     *             'length' => null,
     *             'null'   => false,
     *             'attr'   => array(
     *                 'default' => 'collaborateur',
     *                 'vals' => array('collaborateur','entreprise')
     *             )
     *         ),
     *         'cty_libelle' => array(
     *             'type'   => TYPE_VARCHAR,
     *             'length' => 255,
     *             'null'   => false,
     *             'attr'   => null
     *         ),
     *         'cty_description' => array(
     *             'type'   => TYPE_VARCHAR,
     *             'length' => 255,
     *             'null'   => true,
     *             'attr'   => array(
     *                 'default' => NULL
     *             )
     *         )
     *     ),
     *     'key' => array(
     *         'pk' => array(
     *             'cty_id'
     *         )
     *     )
     * )
     *
     * ATTENTION : ce tableau est automatiquement généré via la classe jemdev\dbrm\init\genereconf, il
     * n'est donc nul besoin de créer soi-même ce tableau décrivant les détails du schéma de données.
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


}

