<?php
namespace jemdev\dbrm\init;

/**
 * Définition des méthodes de récupération des informations sur le schéma.
 * Selon le type de SGBD utilisé, la manière de collecter ces information
 * pourra varier, cependant le retour devra se présenter strictement de
 * la même manière et sous la même forme.
 *
 * @author      Jean Molliné <jmolline@jem-dev.com>
 *
 */
interface getSchemaInfosInterface
{
    /**
     * On récupère la liste des tables.
     *
     * Les tables qui seront retournées ne comportent en
     * principe qu'une et une seule colonne en clé primaire.
     * @return array
     */
    public function getTables(): array;
    /**
     * On récupère la liste des tables relationnelles.
     *
     * Les tables récupérées ont au moins deux colonnes en clé primaire
     * composite.
     * @return array
     */
    public function getRelations(): array;
    /**
     * Liste les contraintes d'intégrité référentielles si elles existent
     * @return array
     */
    public function getConstraints(): array;
    /**
     * Liste les vues qui ont été définies
     * @return array
     */
    public function getVues(): array;
    /**
     * Liste le détail des informations sur les colonnes d'une table indiquée en paramètre.
     *
     * @param string $table
     * @return array
     */
    public function getInfosColonnes(string $table): array;
    /**
     * Liste les clés étrangères dans une table et les informations sur les tables référencées.
     * @param   string  $table Nom de la table vérifiée
     * @return  array
     */
    public function getReferencesFK(string $table): array;

    /**
     * Liste les tables référencées dans la construction de la VUE.
     * @param   string  $viewName Nom de la vue testée.
     * @return  array
     */
    public function getViewTables(string $viewName): array;
}

?>