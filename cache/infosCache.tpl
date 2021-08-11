<?php
/**
 * Gabarits de construction des différents éléments du fichier
 * d'information sur le stockage du cache SQL.
 */
$sContenu = <<<CODE_PHP
<?php
/**
 * Information de stockage du cache SQL
 */

CODE_PHP;

$sContenu = <<<CODE_PHP
\$aCacheRequetes = array(
%s);

CODE_PHP;

$sLigneTableau = <<<CODE_PHP
  "%s" => array(
    "sql"    => "%s",
    "tables" => array(
%s    )
  ),

CODE_PHP;

$sLigneTable = <<<CODE_PHP
      %d => "%s",

CODE_PHP;
