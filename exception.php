<?php
namespace jemdev\dbrm;
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
 * Gestion des exceptions du package d'accès aux données.
 *
 * @package     jemdev
 * @subpackage  dbrm
 * @author      Jean Molliné <jmolline@gmail.com>
 */
class Exception extends \Exception
{
    /**
     * Redéfinition de l'exception.
     *
     * Ainsi le message n'est pas facultatif.
     *
     * @param   String      $message        Message décrivant l'erreur
     * @param   Int         $code           Code de l'erreur
     * @param   \Exception  $precedente     Précédente exception
     */
    public function __construct($message = null, $code = 0, $precedente = null)
    {
        parent::__construct($message, $code, $precedente);
        /**
         * Enregistrement dans un log d'erreur
         */
        $this->_logException($message, $code);
    }

    /**
     * Chaîne personnalisée représentant l'objet
     *
     * @return String
     */
    public function __toString()
    {
        $instantJ   = date('d/m/Y');
        $instantH   = date('H:i:s');
        $numErreur  = (int) $this->getCode();
        $numLigne   = $this->getLine();
        $nomFichier = $this->getFile();
        $message    = $this->getMessage();
        $trace      = $this->getTraceAsString();
        if((int)$numErreur == E_USER_NOTICE)
        {
            $niveauErreur = ' de syntaxe';
        }
        elseif((int)$numErreur == E_USER_WARNING)
        {
            $niveauErreur = ' moyenne';
        }
        elseif((int)$numErreur == E_USER_ERROR)
        {
            $niveauErreur = ' grave';
        }
        else
        {
            $niveauErreur = null;
        }
        $retour = <<<CODE_HTML
<pre>  &bull; Erreur{$niveauErreur} le {$instantJ} &agrave; {$instantH} ;
  &bull; Erreur code {$numErreur} intercept&eacute;e dans le fichier {$nomFichier} &agrave; la ligne {$numLigne} :
    &bull; Message :
      <span style="color: #c00; background-color: inherit; font-weight: bold;">{$message};</span>
    &bull; Trace :
{$trace}
--------------------------------------------------------</pre>

CODE_HTML;
        return $retour;
    }

    /**
     * Traitement personnalisé : enregistrement de
     * l'exception dans un fichier journal.
     *
     * @param   String  $message
     * @param   Int     $code
     */
    private function _logException($message, $code = 0)
    {
        $fichier = "./logException.log";
        if(false !== ($f = fopen($fichier, "a")))
        {
            // date_default_timezone_set("Europe/Paris");
            $msg  = "Le ". date("d-m-Y") ." à ". date("H:i:s") ."\n". PHP_EOL;
            $msg .= $message ." : Code ". $code ."\n". PHP_EOL;
            $msg .= "Fichier : ". $this->getFile() .";\n". PHP_EOL;
            $msg .= "Ligne : ". $this->getLine() ."\n". PHP_EOL;
            $msg .= $this->getTraceAsString() ."\n";
            $msg .= "--------------------------------------------\n". PHP_EOL;
            fwrite($f, $msg);
            fclose($f);
        }
    }
}
