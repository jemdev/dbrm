<?php
namespace jemdev\dbrm;

use jemdev\dbrm\ligneInstance;
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
 * On définit une constante raccourcie pour le séparateur de répertoires.
 */
if(!defined('DS'))
{
    define('DS', DIRECTORY_SEPARATOR);
}
$repDb = realpath(dirname(__FILE__));
/**
 * Classe de récupération des instances de dbTableInstance.
 *
 * @package     jemdev
 * @subpackage  dbrm
 * @author      Jean Molliné <jmolline@gmail.com>
 */
class table
{
    /**
     *
     * @var ligneInstance
     */
    private $_oLigneInstance;
    public function __construct($_schema, $_sNomTable, $aConfig, $_aliasTable = null)
    {
        $this->_oLigneInstance = ligneInstance::getInstance($_schema, $_sNomTable, $aConfig, $_aliasTable = null);
    }

    /**
     *
     * @return ligneInstance
     */
    public function getInstance(): ligneInstance
    {
        return $this->_oLigneInstance;
    }

    public function __toString()
    {
        $retour = sprintf('%s', $this->_oLigneInstance);
        return $retour;
    }
}
