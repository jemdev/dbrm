# jemdev\dbrm : DataBase Relational Mapping
- Auteur : Jean Molliné
- Licence : [CeCILL V2][]
- Pré-requis :
  - PHP >= 5.4
- Utilise : [Hoa\Registry][]
- Contact : [Message][]
- Github : [github.com/jemdev/dbrm][]
- Packagist : [packagist.org/packages/jemdev/dbrm][]

-----------------------------------
# Installation
Avec Composer, ajouter ce qui suit dans la partie require de votre composer.json:

```
{
  "jemdev/dbrm": "dev-master"
}
```

-----------------------------------
# Présentation et principe de fonctionnement.
Ce package permet un accès aux données d'une base de données relationnelle.
L'idée fondatrice part du principe qu'on peut faire des lectures sur des tables multiples mais que l'écriture ne peut se faire que sur une seule table à la fois.
Par conséquent, il devenait envisageable de créer des objets dynamiques pour chacune des tables sur lesquelles on souhaitait effectuer des opérations en écriture.

Des méthodes relativement simples permettent d'exécuter des requêtes préparées pour la collecte de données. Pour l'écriture, d'autres méthodes permettent de créer une instance pour initialiser une ligne d'une table donnée et d'affecter les valeurs souhaitées aux différentes colonnes de la table pour cette ligne.
Selon que l'identifiant de la ligne est fourni ou non, l'écriture sera une création ou une modification, voire une suppression.

Pour pouvoir créer ces instances dynamiques, un système permet d'établir une sorte de cartographie du schéma de données, détaillant la liste des tables, des tables relationnelles et des vues qui sont présentes. Sur la base de ces informations, une instance pour une table donnée définit les propriétés en lisant la liste des colonnes, leur types et d'autres informations pratiques.

Lors de la connexion, si le fichier de configuration n'existe pas, il est automatiquement créé. Par la suite, si on modifie la structure du schéma, même si ce n'est que pour ajouter, modifier ou retirer une colonne dans une table, une méthode permet de régénérer ce fichier de configuration. Il m'est apparu comme très peu pratique de devoir créer une classe pour chacune des tables, ces modifications de structure induisant la ré-écriture partielle de certaines de ces classes à chaque fois. Ces classes sont donc gérées dynamiquement et sont, en réalité, des classes virtuelles.
## Récupérer un objet de connexion
### Configurer la connexion
Il est impératif de créer un fichier contenant les paramètres de connexion au SGBDR. Ce fichier doit être nommé *dbCnxConf.php* et être formaté de la manière suivante :

```php
<?php
/**
 * Fichier de configuration des paramètres de connexion à la base de données.
 * Ce fichier est généré automatiquement lors de la phase initiale d'installation.
 */
$db_app_server  = 'localhost';          // Adresse du serveur de base de données
$db_app_schema  = 'testjem';            // Schéma à cartographier (base de données de l'application)
$db_app_user    = 'testjem';            // Utilisateur de l'application pouvant se connecter au SGBDR
$db_app_mdp     = 'testjem';            // Mot-de-passe de l'utilisateur de l'application
$db_app_type    = 'pgsql';              // Type de SGBDR, MySQL, PostGreSQL, Oracle, etc..
$db_app_port    = '5432';               // Port sur lequel on peut se connecter au serveur
$db_meta_schema = 'INFORMATION_SCHEMA'; // Schéma où pourront être collectées les informations sur le schéma de travail
/**
 * Création des constantes globales de l'application
 * NE PAS MODIFIER LES LIGNES SUIVANTES
 */
defined("DB_ROOT_SERVER")       || define("DB_ROOT_SERVER",         $db_app_server);
defined("DB_ROOT_USER")         || define("DB_ROOT_USER",           $db_app_user);
defined("DB_ROOT_MDP")          || define("DB_ROOT_MDP",            $db_app_mdp);
defined("DB_ROOT_SCHEMA")       || define("DB_ROOT_SCHEMA",         $db_meta_schema);
defined("DB_ROOT_TYPEDB")       || define("DB_ROOT_TYPEDB",         $db_app_type);
defined("DB_ROOT_DBPORT")       || define("DB_ROOT_DBPORT",         $db_app_port);
defined("DB_APP_SCHEMA")        || define("DB_APP_SCHEMA",          $db_app_schema);
defined("DB_APP_SERVER")        || define("DB_APP_SERVER",          $db_app_server);
defined("DB_APP_USER")          || define("DB_APP_USER",            $db_app_user);
defined("DB_APP_PASSWORD")      || define("DB_APP_PASSWORD",        $db_app_mdp);
```

#### Les types de SGBDR supportés
À ce jour, ce n'est utilisable qu'avec MySQL et PostGreSQL. Je n'ai pas testé avec les forks de MySQL autres que MariaDb (percona et autres) mais dans la mesure où ils sont compatibles, ça ne devrait pas présenter de blocage.
La valeur à utiliser pour la variable *$db_app_type* :

- MySQL : *mysql* (fonctionne avec ce type pour MariaDB)
- PostGreSQL : *pgsql*

Ce fichier devra être placé dans le répertoire où sont situés vos éventuels autres fichiers de configuration selon l'architecture de votre application.
Il conviendra par la suite de pouvoir fournir en temps voulu le chemin absolu vers ce fichier. Dès le départ, s'il n'existe pas, un autre fichier de configuration sera généré automatiquement et sera indispensable au bon fonctionnement du package. Ce fichier sera généré en deux version, le premier nommé *dbConf.php* pourra être assez facilement lu par n'importe quel développeur, le second qui sera privilégié pour l'utilisation par l'application sera nommé *dbConf_compact.php* et correspondra strictement au même contenu mais compacté et ramené sur une seule ligne.
Ce fichier décrit en détail l'ensemble de la structure de données, tables, tables relationnelles et vues, colonnes, clés et autres informations détaillées. Il sera utilisé par les objets destinés à toutes les opérations en écriture, insertion, mises à jour ou suppression.

### Méthodes globales accessibles
Deux méthodes de base seront indispensables :

- setRequete($sql, $aParams = array(), $cache = true)
Définit la requête à exécuter, en option on peut indiquer des paramètres dans un tableau associatif où chaque index est le nom d'une variable sql associée à la valeur qui doit y être affectée, et un troisième paramètre permet d'activer la mise en cache du résultat, cache qui est désactivé par défaut;
- execute()
Cette méthode permet d'exécuter directement une méthode définie avec setRequete(). On peut alors envoyer une requête, un appel de procédure stockée ou une fonction utilisateur voire même une requête en écriture bien que cette dernière option ne soit pas recommandée (Voir plus loin l'écriture de données)


Deux autres méthodes nous intéressent principalement ici et ne seront utilisées que lorsqu'on devra enregistrer des création ou modifications de données :

- startTransaction()
Démarre une transaction si les tables utilisent un moteur transactionnel. Toutes les requêtes suivantes seront alors incluses dans une transaction jusqu'à ce qu'on appelle la méthode finishTransaction();
- finishTransaction($bOk)
Termine une transaction : le paramètre attendu est un booleen, TRUE exécutera un COMMIT, FALSE exécutera un ROLLBACK;

Une autre méthode pourra se révéler pratique lors de la phase de développement de votre application :

- getErreurs()
Retourne la liste des erreurs rencontrées sous la forme d'un tableau


# Lecture de données
Il n'y a pas de générateur de requêtes, à tout le moins pour l'instant. On devra écrire soi-même les requêtes en lecture qui devront être exécutées pour la collecte de données.

Chaque requête peut être paramétrée, et sera exécutée avec PDO : elle retournera une donnée unique, une ligne de données ou bien un tableau de données voire même un objet.
On s'appuiera sur une instance de la classe jemdev\dbrm\vue qu'on définira au préalable.

Exemple : par convention, l'instance de connexion sera la variable « $oVue » et aura été définie en amont (entendez le mot de « vue » au sens SQL du terme).

```php
<?php
/* Définition de la requête */
$sql = "SELECT utl_id, utl_nom, utl_prenom, utl_dateinscription ".
       "FROM t_utilisateur_utl ".
       "WHERE utl_dateinscription > :p_utl_dateinscription ".
       "ORDER BY utl_nom, utl_prenom";
/* Initialisation de paramètre(s) */
$params = array(':p_utl_dateinscription' => '2015-10-15');
/* Initialisation de la requête */
$oVue->setRequete($sql, $params);
/* Récupération des données */
$infosUtilisateur = $oVue->fetchAssoc();
```

#### Note:
Le nom de l'objet $oVue dans cet exemple n'est pas anodin, il faut entendre le mot *vue* au sens SQL du terme. Une vue dans une base de données est une synthèse d'une requête d'interrogation de la base. On peut la voir comme une table virtuelle, définie par une requête.
## Les méthodes accessibles
Le nom des méthodes est inspiré de celles qu'on emploie avec l'extension MySQL. Le retours sont bien entendu similaires dans leur forme.

- fetchAssoc()
Retourne un tableau associatif de résultats où les index sont les noms des colonnes ou alias déterminés dans la requête;
- fetchArray()
Retourne un tableau où chaque colonne est présentée avec deux index, l'un numérique, l'autre associatif avec le nom de la colonne;
- fetchObject()
Retourne un objet où chaque colonne est une propriété;
- fetchLine($out = 'array')
Retourne une seule ligne de données. On peut préciser en paramètre quelle forme doit prendre le résultat en passant une des constantes suivantes :
    - vue::JEMDB\_FETCH\_OBJECT  = 'object' : indiquera un retour sous forme d'un objet;
    - vue::JEMDB\_FETCH\_ARRAY   = 'array' : indiquera un retour sous forme d'un tableau avec double index numérique et associatif;
    - vue::JEMDB\_FETCH\_ASSOC   = 'assoc' : indiquera un retour sous forme d'un tableau associatif;
    - vue::JEMDB\_FETCH\_NUM     = 'num' : indiquera un retour sous forme d'un tableau indexé numériquement;
- fetchOne()
Retourne une donnée unique;

# Écriture de données
### Les méthodes accessibles
Sur une instance donnée, vous disposez des méthodes suivantes :

- init($aPk = null) : Initialise l'instance de la ligne. En arrière plan, l'objet sera construit dynamiquement avec comme propriétés les colonnes de la table visée;
- sauvegarder() : Enregistre les modifications apportées aux propriétés par une requête INSERT ou UPDATE selon qu'on a déterminé ou non la clé primaire;
- supprimer() : Supprime la ligne de la table par une requête DELETE. Dans le cas où un moteur transactionnel serait utilisé et que des clauses d'intégrité référentielles auraient été définies (CONSTRAINT), la méthode pourra retourner une erreur si des données faisant références à la ligne devant être supprimée existent encore dans les tables liées;
- startTransaction() : Démarre une transaction SQL;
- finishTransaction($bOk) : Termine une transaction SQL, le paramètre attendu est un booléen indiquant si on doit effectuer un COMMIT ou un ROLLBACK;

## Mise en pratique
On écrit des données, comme mentionné en introduction, que sur une seule table à la fois. Pour ce faire, on crée un objet représentant une ligne de ladite table.
Voici d'abord un exemple schématique :
Les tables sur lesquelles s'appuient notre exemple auront la forme suivante :

 1. Table t_interlocuteur_int

```
+---------------------+-------------------------+------+-----+---------+----------------+
| Field               | Type                    | Null | Key | Default | Extra          |
+---------------------+-------------------------+------+-----+---------+----------------+
| int_id              | int(10) unsigned        | NO   | PRI | NULL    | auto_increment |
| adr_id              | int(10) unsigned        | YES  | MUL | NULL    |                |
| int_nom             | varchar(255)            | NO   |     | NULL    |                |
| int_prenom          | varchar(255)            | NO   |     | NULL    |                |
| int_dateinscription | date                    | YES  |     | NULL    |                |
+---------------------+-------------------------+------+-----+---------+----------------+
```
 2. Table t_adresse_adr
```
+-------------------+------------------+------+-----+---------+----------------+
| Field             | Type             | Null | Key | Default | Extra          |
+-------------------+------------------+------+-----+---------+----------------+
| adr_id            | int(10) unsigned | NO   | PRI | NULL    | auto_increment |
| adr_numero        | varchar(16)      | YES  |     | NULL    |                |
| adr_libelle_1     | varchar(128)     | NO   |     | NULL    |                |
| adr_libelle_2     | varchar(128)     | YES  |     | NULL    |                |
| adr_codepostal    | varchar(16)      | NO   |     | NULL    |                |
| adr_commune       | varchar(128)     | NO   |     | NULL    |                |
+-------------------+------------------+------+-----+---------+----------------+
```

**Note importante** : vous ne pourrez pas définir vous-même la valeur de la clé primaire en insérant une nouvelle ligne de données. Cette valeur sera générée automatiquement par le SGBD si cette colonne est bien en `auto-increment`. Lorsqu'on initialise une ligne de données, on peut en option indiquer la valeur de cette clé primaire si on la connaît : le cas échéant, les colonnes seront alors alimentées avec les valeurs correspondant à cette ligne, sinon, nous aurons une ligne vide prête à compléter.
À présent, écrivons une ligne de données :
```php
<?php
/* On crée l'instance de la ligne à partir du nom de la table cible */
$oAdresse = $oDbrm->getLigneInstance('t_adresse_adr');
/*
 * On détermine si l'on dispose ou non de la clé primaire de la ligne
 * et on stocke ça dans un tableau associatif
 */
$aPk = (!empty($adr_id)) ? array('adr_id' => $adr_id) ? null;
/* On initialise l'instance */
$oAdresse->init($aPk);
/*
 * Dès cet instant, notre objet présente chaque colonne de la
 * table t_interlocuteur_int comme des propriétés qu'on peut modifier
 */
$oAdresse->adr_numero       = $adr_numero;
$oAdresse->adr_libelle_1    = $adr_libelle_1;
// ... etc...

/* On peut maintenant sauvegarder ces informations */
$enreg = $oAdresse->sauvegarder();
/*
 * Terminé, les écritures pour cette ligne sont terminées.
 * On peut récupérer la valeur de la clé primaire si nécessaire et s'il
 * s'agissait d'une création. Cette clé primaire est automatiquement gérée
 * et initialisée dans l'instance.
 * S'il y a eu une erreur, la méthode sauvegarder retournera l'erreur, sinon
 * elle retournera TRUE
 */
if(true == $enreg)
{
    /*
     * Ici, si par exemple vous avez d'autres données à enregistrer, données qui
     * dépendent la la réussite de ce premier enregistrement, vous continuez
     * sur l'enregistrement suivant, exemple :
     */
    $adr_id         = $oAdresse->adr_id;
    $oInterlocuteur = $oDbrm->getLigneInstance('t_interlocuteur_int');
    /*
     * On détermine si l'on dispose ou non de la clé primaire de la ligne
     * et on stocke ça dans un tableau associatif
     */
    $aPk = (!empty($int_id)) ? array('int_id' => $int_id) : null;
    /* On initialise l'instance */
    $oInterlocuteur->init($aPk);
    /*
     * Dès cet instant, notre objet présente chaque colonne de la
     * table t_interlocuteur_int comme des propriétés qu'on peut modifier
     */
    $oInterlocuteur->adr_id     = $adr_id;      // Ici, on alimente la clé étrangère définie en enregistrant l'adresse.
    $oInterlocuteur->int_nom    = $int_nom;
    $oInterlocuteur->int_prenom = $int_prenom;
    if(!is_null($int_dateinscription))
    {
        $oInterlocuteur->int_dateinscription = $int_dateinscription;
    }
    /* On peut maintenant sauvegarder ces informations */
    $enreg = $oInterlocuteur->sauvegarder();
    
    // etc... suite selon les besoins.
}
else
{
    // Ici, le code permettant la gestion de l'erreur selon vos propres manières de faire.
}
```
### En résumé : l'instance d'une table
Lorsque vous créez une instance pour une table donnée, chaque colonne de cette table devient une propriété de cette instance. Ainsi, la colonne `int_nom` est une propriété de l'objet `$oInterlocuteur` et peut donc être invoquée comme une propriété publique de l'objet.
Si vous essayez d'affecter une valeur sur une colonne qui n'existe pas dans la table considérée, une exception sera levée. De même que vous ne pourrez pas affecter une valeur arbitraire sur une clé primaire.
Cependant, il existe un cas où vous pourrez définir vous-même la valeur d'une clé primaire lorsqu'il s'agit d'une clé composite sur une table relationnelle. Ainsi, si au lieu d'un lien direct entre interlocuteur et adresse dans notre exemple nous avions eu une table relationnelle entre les deux, par exemple `r_int_has_adr_iha`, nous aurions les colonnes `int_id` et `adr_id` composant la clé primaire de cette table relationnelle. Dans un tel cas, nous modifierons le code précédent dans la manière suivante:
```php
<?php
/* Définition des valeurs de bases des identifiants */
$int_id = null;
$adr_id = null;

/* On crée l'instance de la ligne à partir du nom de la table cible */
$oAdresse = $oDbrm->getLigneInstance('t_adresse_adr');
/*
 * On détermine si l'on dispose ou non de la clé primaire de la ligne
 * et on stocke ça dans un tableau associatif
 */
$aPk = (!empty($adr_id)) ? array('adr_id' => $adr_id) ? null;
/* On initialise l'instance */
$oAdresse->init($aPk);
/*
 * Dès cet instant, notre objet présente chaque colonne de la
 * table t_interlocuteur_int comme des propriétés qu'on peut modifier
 */
$oAdresse->adr_numero       = $adr_numero;
$oAdresse->adr_libelle_1    = $adr_libelle_1;
// ... etc...

/* On peut maintenant sauvegarder ces informations */
$enreg = $oAdresse->sauvegarder();
/*
 * Terminé, les écritures pour cette ligne sont terminées.
 * On peut récupérer la valeur de la clé primaire si nécessaire et s'il
 * s'agissait d'une création. Cette clé primaire est automatiquement gérée
 * et initialisée dans l'instance.
 * S'il y a eu une erreur, la méthode sauvegarder retournera l'erreur, sinon
 * elle retournera TRUE
 */
if(true == $enreg)
{
    $adr_id         = $oAdresse->adr_id;
    // etc... suite selon les besoins.
}
else
{
    // Ici, le code permettant la gestion de l'erreur selon vos propres manières de faire.
}
$oInterlocuteur = $oDbrm->getLigneInstance('t_interlocuteur_int');
/*
 * On détermine si l'on dispose ou non de la clé primaire de la ligne
 * et on stocke ça dans un tableau associatif
 */
$aPk = (!empty($int_id)) ? array('int_id' => $int_id) : null;
/* On initialise l'instance */
$oInterlocuteur->init($aPk);
/*
 * Dès cet instant, notre objet présente chaque colonne de la
 * table t_interlocuteur_int comme des propriétés qu'on peut modifier
 */
$oInterlocuteur->adr_id     = $adr_id;      // Ici, on alimente la clé étrangère définie en enregistrant l'adresse.
$oInterlocuteur->int_nom    = $int_nom;
$oInterlocuteur->int_prenom = $int_prenom;
if(!is_null($int_dateinscription))
{
    $oInterlocuteur->int_dateinscription = $int_dateinscription;
}
/* On peut maintenant sauvegarder ces informations */
$enreg = $oInterlocuteur->sauvegarder();
if(true == $enreg)
{
    $int_id         = $oInterlocuteur->int_id;
    // etc... suite selon les besoins.
}
else
{
    // Ici, le code permettant la gestion de l'erreur selon vos propres manières de faire.
}
if(!is_null($int_id) && !is_null($adr_id))
{
    /* Maintenant, on peut alimenter la tablea relationnelle */
    $oAdresseInt = $oDbrm->getLigneInstance('r_int_has_adr_iha');
    /* On définit les éléments de la clé primaire composite */
    $aPk = array(
        'int_id' => $int_id,
        'adr_id' => $adr_id
    );
    /* On initialise l'instance de l'objet */
    $oAdresseInt->init($aPk);
    /* On peut sauvegarder */
    $enreg = $oAdresseInt->sauvegarder();
    if(true !== $enreg)
    {
        // Ici, le code permettant la gestion de l'erreur selon vos propres manières de faire.
    }
}
```
Et là, pas de blocage lors de l'affectation des valeurs pour la clé primaire.
### En pratique
Une utilisation pratique vous amènera sans doute à répartir les requêtes en écriture sur différentes tables dans différentes fonctions/méthodes appelées à partir d'un endroit unique, ce qui vous permettra d'utiliser au besoin le mode transactionnel. En démarrant la transaction au départ, vous exécutez chaque enregistrement, et si une des méthodes retourne FALSE à cause d'une erreur, vous pourrez interrompre la succession des enregistrements et terminer la transaction par un ROLLBACK, évitant ainsi de pourrir vos tables avec des données orphelines ou incohérentes.

### Ce qu'on ne peut pas faire (pour l'instant)

Actuellement, il reste quelques éléments en _TODO-LIST_ et en particulier, lors de l'écriture de données, la possibilité d'affecter non pas une valeur mais un appel de fonction SQL. Supposons par exemple que vous vouliez utiliser une fonction de chiffrement intégrée de MySQL pour affecter une valeur. Il n'est pour l'instant pas possible de faire ceci :
```php
$instanceLigne->nom_colonne = "AES_ENCRYPT('valeur', 'Clé de chiffrement')";
```
#### Comment contourner le problème.
Pour une utilisation quotidienne, ce n'est pas un réel problème, ce type de cas particulier étant relativement rare. Si cependant vous devez pouvoir effectuer une telle opération, vous avez deux options.

- La première consiste à envoyer une valeur en clair et ajouter un trigger sur la table avec un BEFORE INSERT qui exécutera alors la fonction SQL à appliquer sur la valeur pour l'affecter à la colonne. Mais cette méthode pourra être bloquée sur un serveur mutualisé où les fonctions utilisateurs, procédures stockées et triggers sont désactivés par défaut;
- La seconde consiste alors à écrire vous-même la requête en écriture INSERT ou UPDATE et à la faire exécuter avec la méthode ` execute()` de la manière suivante :

```php
<?php
/* On a d'abord besoin d'une instance de jemdev\dbrm\vue */
$oVue = $oDb->getDbrmInstance();
/* On définit la requête SQL d'insertion */
$sql  = "INSERT INTO matable (col_login, col_motdepasse)".  
        "VALUES('Toto', AES_ENCRYPT('valeur', 'Clé de chiffrement'))";  
$oVue->setRequete($sql);  
/* Exécution de la requête. */
$enreg = $oVue->execute();
```

La suite du code ne change pas.
### Une instance = une ligne
Il n'a pas été prévu pour l'instant de pouvoir effectuer une mise à jour ou encore une suppression de lignes multiples dans la mesure où une mise à jour s'effectuera uniquement en fonction de la valeur d'une clé primaire. Pratiquant l'utilisation au quotidien de ce package depuis déjà de nombreuses années et ce sur une application de gestion, je n'ai en réalité jamais eu besoin d'implémenter cette possibilité. Et pour les rares fois où ça doit se produire, je peux contourner ce manque en collectant la liste des clé primaires à prendre en compte dans une mise à jour et chaque ligne sera traitée individuellement dans une boucle.
-----------------------------------
# Temps d'exécution des requêtes
Ce petit système s'appuie sur la méthode native `error_log` de PHP pour la journalisation de requêtes lentes. Au fil du développement d'une application, il peut être utile d'identifier des goulets d'étranglement qui ralentissent l'application. On peut alors simplement activer un système de mesure qui effectuera un chronométrage systématique de toutes les requêtes. On paramètre le système en lui indiquant :

- Le type de journalisation : « *php* », « *fichier* » ou « *courriel* »;
- La durée minimale en secondes à partir de laquelle on journalise une requête;
- En option le chemin absolu vers un fichier journal où seront enregistrées les informations si le mode « *fichier* » a été défini;
- En option une adresse de courriel où adresser les messages d'avertissement si le mode « *courriel* » a été défini.

La mise en oeuvre est très simple, exemple :

```php
$mode = 'fichier';
$maxtime = 1;
$fichier = 'app/tmp/logs/journaldb.log';
$this->_oDb->activerModeDebug($mode, $maxtime, $fichier);
```

C'est tout : une fois ceci lancé, il suffit alors de naviguer normalement dans l'application, spécialement dans les parties qui affichent des ralentissements apparents, puis de vérifier le fichier journal pour y trouver éventuellement des requêtes qui devraient alors être optimisées pour une accélération.

Pour le mode « *fichier* », si le fichier n'existe pas, il sera créé.
-----------------------------------
# Une gestion de cache dynamique
Un problème d'accès à la configuration du serveur MySQL sur lequel je travaillais m'interdisait de paramétrer le cache intégré et même tout simplement de l'activer par défaut. Souhaitant pouvoir disposer d'un système de gestion de cache de requêtes, j'ai ajouté des classes permettant de gérer cet aspect.

Globalement, chaque requête en lecture peut, si le cache est activé, stocker le résultat en cache sur fichier voire même sur `MemCache` si cette extension est activée. Toute écriture sur une des table va régénérer le cache pour les requêtes où est impliquée la table en question. La durée de vie du cache est donc fonction de l'exécution de nouvelles écritures et non d'une durée de vie pré-définie. Si le résultat d'une requête est valide pendant trois minutes et qu'une écriture intervient, le cache est renouvelé, si ce même résultat est toujours valide après trois semaines, il est parfaitement inutile de le régénérer.

Certaines méthodes permettent de régénérer manuellement le cache pour certaines tables. Par exemple, si lors d'une écriture sur une table un trigger va déclencher l'exécution d'une procédure stockée créant des écritures sur d'autres tables, il sera important de régénérer le cache sur ces autres tables. Il n'est pas possible de détecter ces écritures en PHP dans la mesure où c'est le SGBDR qui gère ça directement. De même si des tâches CRON déclenchent des écritures sans passer par PHP, il n'est pas possible d'intercepter cette information pour mettre à jour le cache correspondant, il conviendra donc d'écrire un code PHP qui effectuera ce nettoyage, code qui devra être exécuté dans une tâche à ajouter au CronTab.

Par défaut, le cache n'est pas activé, et si vous avez la possibilité de gérer le cache intégré de votre SGBDR, ce sera alors une solution préférable et plus performante.

-----------------------------------
# Conclusion
Ce package se veut simple d'utilisation de façon à ne pas perdre le développeur dans les complications de l'implémentation, et ce sans avoir à se préoccuper du type de serveur de base de données utilisé, que ce soit MySQL/MariaDb, ou PostGreSQL.
## À venir
Il reste à développer le code qui permettra d'utiliser des SGBDR autres que MySQL ou PostGreSQL, codes qui pour l'instant n'existent pas. Il s'agit de pouvoir construire le tableau de configuration d'un schéma de données. MySQL et PostGreSQL implémentent INFORMATION_SCHEMA, ce qui facilite grandement ce travail, mais tous les SGBDR ne l'implémentent pas, comme par exemple Oracle. Il existe cependant d'autres manière de collecter ces informations pour aboutir au même résultat.

Par la suite, le fonctionnement s'appuyant sur PDO, l'intégration de jemdev\dbrm pourra se faire dans n'importe quel projet.

## Les projets à plus long terme
L'idée d'un générateur de requêtes automatisé flotte dans l'air depuis pas mal de temps mais requiert un niveau de connaissances en mathématiques que je n'ai malheureusement pas.
Il est question de s'appuyer sur la théorie des graphes pour déterminer quelles jointures devront être établies pour n'avoir à définir que les colonnes de telle ou telle table est attendue pour que le moteur construise automatiquement le chemin approprié.
Le fichier de configuration permet d'ores et déjà de créer une matrice (le code n'est pas intégré dans le package mais est déjà prêt et opérationnel), il reste à définir l'algorithme approprié de façon à construire des requêtes respectant les standards les plus exigeants.

Toute contribution en la matière sera bienvenue.

[CeCILL V2]: http://www.cecill.info/licences/Licence_CeCILL_V2-fr.html "Texte complet de la licence CeCILL version 2"
[Hoa\Registry]: https://github.com/hoaproject/Registry "Le package Hoa\Registry sur Github"
[Message]: https://jem-dev.com/contacter-jem-developpement/ "Envoyer un message à Jean Molliné"
[github.com/jemdev/dbrm]: https://github.com/jemdev/dbrm "Le package jemdev\dbrm sur Github"
[packagist.org/packages/jemdev/dbrm]: https://packagist.org/packages/jemdev/dbrm "La package jemdev\dbrm sur Packagist"
