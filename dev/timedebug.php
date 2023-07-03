<?php
namespace jemdev\dbrm\dev;

/**
 * Cette classe permet de mesurer les temps d'exécution des requêtes.
 *
 * À des fins de débogage d'application, on définira un temps limite au-delà
 * duquel une requête sera enregistrée dans un journal d'exécution de façon à
 * ce qu'on puisse l'isoler et l'optimiser.
 *
 * @author jem-dev
 */
class timedebug
{
    /**
     * Enregistrement via le gestionnaire de journalisation intégré de PHP
     * @var integer
     */
    const LOG_PHP       = 0;
    /**
     * Envoi d'un courriel signalant le message
     * @var integer
     */
    const LOG_COURRIEL  = 1;
    /**
     * Enregistrement vers un fichier journal
     * @var integer
     */
    const LOG_FICHIER   = 3;
    /**
     * Durée minimum d'exécution en secondes à partir de laquelle sera enregistré un message d'avertissement
     * @var integer
     */
    private $_maxtime    = 5;
    /**
     * Type de journalisation.
     * @var integer
     */
    private $_typelog    = self::LOG_PHP;
    /**
     * Chemin absolu vers le fichier journal
     * @var string
     */
    private $_cheminjournal;
    /**
     * Nom de la base de données surveillée
     * @var string
     */
    private $_lognomdb;
    /**
     * Adresse de courriel de l'administrateur à laquelle pourront être envoyés les messages
     * @var string
     */
    private $_admincourriel;
    /**
     *
     * @var array<int, string>  $_types Types de journalisation supportés
     */
    protected static $_types = [
        'php'       => self::LOG_PHP,
        'courriel'  => self::LOG_COURRIEL,
        'fichier'   => self::LOG_FICHIER,
    ];
    /**
     * Constructeur
     * @param   string  $type
     * @param   number  $maxtime
     * @param   string  $fichier
     * @param   string  $courriel
     * @throws \InvalidArgumentException
     */
    public function __construct($type='php', $maxtime=5, $fichier = null, $courriel = null)
    {
        if(in_array($type, static::$_types))
        {
            $this->_typelog = self::$_types[$type];
        }
        else
        {
            throw new \InvalidArgumentException("Type « ". $type ." » non défini, utilisez l'un de ceux-ci : ". implode(',', array_keys(static::$_types)));
        }
        $this->_maxtime = $maxtime;
        if(!is_null($fichier))
        {
            if(is_writable($fichier))
            {
                $this->_cheminjournal = $fichier;
            }
            else
            {
                throw new \InvalidArgumentException("Fichier journal ". $fichier ." est introuvable ou inaccessible en écriture", E_USER_ERROR);
            }
        }
        if(!is_null($courriel))
        {
            $this->_admincourriel = $courriel;
        }
    }

    /**
     * Retourne la valeur de $_maxtime
     * @return Int
     */
    public function getMaxtime(): Int
    {
        return $this->_maxtime;
    }

    /**
     * Retourne la valeur de $_typelog
     * @return string
     */
    public function getTypelog(): string
    {
        return $this->_typelog;
    }

    /**
     * Retourne la valeur de $_cheminjournal
     * @return string
     */
    public function getLogfilepath(): string
    {
        return $this->_cheminjournal;
    }

    /**
     * Retourne la valeur de $_lognomdb
     * @return string
     */
    public function getLogdbname(): string
    {
        return $this->_lognomdb;
    }

    /**
     * Retourne la valeur de $_admincourriel
     * @return string
     */
    public function getAdminmail(): string
    {
        return $this->_admincourriel;
    }

    /**
     * Liste des types de journalisations supportés
     * @return array
     */
    public function getTypes():array
    {
        return array_keys(self::$_types);
    }

    /**
     * Définit la durée minimum à partir de laquelle l'exécution de la requête sera journalisée
     * @param Int $maxtime
     * @return self
     */
    public function setMaxtime(Int $maxtime): self
    {
        $this->_maxtime = $maxtime;
        return $this;
    }

    /**
     *
     * Définit le type de journalisation
     * @param   string  $typelog    Type de journal, valeurs possibles : « php » (défaut), « fichier » ou « courriel ».
     * @throws  \InvalidArgumentException
     * @return  self
     */
    public function setTypelog(string $typelog): self
    {
        if(array_key_exists($typelog, static::$_types))
        {
            $this->_typelog = self::$_types[$typelog];
        }
        else
        {
            throw new \InvalidArgumentException("Type « ". $typelog ." » non défini, utilisez l'un de ceux-ci : ". implode(',', array_keys(static::$_types)));
        }
        return $this;
    }

    /**
     * Définit le chemin absolu vers le fichier de journalisation
     * @param string $logfilepath
     * @return self
     */
    public function setLogfilepath(string $logfilepath): self
    {
        $this->_cheminjournal = $logfilepath;
        return $this;
    }

    /**
     * Définit la base de données surveillée.
     * @param   string $logdbname
     * @return  self
     */
    public function setLogdbname(string $logdbname): self
    {
        $this->_lognomdb = $logdbname;
        return $this;
    }

    /**
     * @param field_type $_admincourriel
     */
    public function setAdminmail($adminmail): self
    {
        $this->_admincourriel = $adminmail;
        return $this;
    }

    public function verifTime(float $t1, float $t2, string $sql, $params):void
    {
        $duree = $t2 - $t1;
        if($duree >= $this->_maxtime)
        {
            if(is_null($params))
            {
                $params = array();
            }
            $enreg = $this->_enregistrerEvemenent($duree, $sql, $params);
            if(true != $enreg)
            {
                throw new \ErrorException("Erreur lors de l'enregistrement d'un avertissement de requête lente");
            }
        }
    }

    private function _enregistrerEvemenent(float $duree, string $sql, array $params)
    {
        $message = "Durée d'exécution de la requête supérieure au maximum défini de ". $this->_maxtime ." secondes :". PHP_EOL;
        $message .= "\t# Durée : ". $duree ." secondes;". PHP_EOL;
        $message .= "\t# Base : ". $this->_lognomdb .";". PHP_EOL;
        $message .= "\t# Requête : « ". $sql ." »;". PHP_EOL;
        $message .= "\t# Parametres : ". print_r($params, true) .";". PHP_EOL;
        $txt = base64_encode($message);
        switch ($this->_typelog)
        {
            case 1:
                $paramdest = $this->_admincourriel;
                break;
            case 3:
                $paramdest = $this->_cheminjournal;
                break;
            default:
                $paramdest = null;
        }
        $bLog = error_log($txt, $this->_typelog, $paramdest);
        return $bLog;
    }
}

