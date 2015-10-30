# jemdev\dbrm : DataBase Relational Mapping
- Auteur : Jean Mollin�
- Licence : [CeCILL V2][]
- Pr�-requis :
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
# Pr�sentation et principe de fonctionnement.
Ce package permet un acc�s aux donn�es d'une base de donn�es relationnelle.
L'id�e fondatrice part du principe qu'on peut faire des lectures sur des tables multiples mais que l'�criture ne peut se faire que sur une seule table � la fois.
Par cons�quent, il devenait envisageable de cr�er des objets dynamiques pour chacune des tables sur lesquelles on souhaitait effectuer des op�rations en �criture.

Des m�thodes relativement simples permettent d'ex�cuter des requ�tes pr�par�es pour la collecte de donn�es. Pour l'�criture, d'autres m�thodes permettent de cr�er une instance pour initialiser une ligne d'une table donn�e et d'affecter les valeurs souhait�es aux diff�rentes colonnes de la table pour cette ligne.
Selon que l'identifiant de la ligne est fourni ou non, l'�criture sera une cr�ation ou une modification, voire une suppression.

Pour pouvoir cr�er ces instances dynamiques, un syst�me permet d'�tablir une sorte de cartographie du sch�ma de donn�es, d�taillant la liste des tables, des tables relationnelles et des vues qui sont pr�sentes. Sur la base de ces informations, une instance pour une table donn�e d�finit les propri�t�s en lisant la liste des colonnes, leur types et d'autres informations pratiques.

Lors de la connexion, si le fichier de configuration n'existe pas, il est automatiquement cr��. Par la suite, si on modifie la structure du sch�ma, m�me si ce n'est que pour ajouter, modifier ou retirer une colonne dans une table, une m�thode permet de r�g�n�rer ce fichier de configuration. Il m'est apparu comme tr�s peu pratique de devoir cr�er une classe pour chacune des tables, ces modifications de structure induisant la r�-�criture partielle de certaines de ces classes � chaque fois. Ces classes sont donc g�r�es dynamiquement et sont, en r�alit�, des classes virtuelles.
## R�cup�rer un objet de connexion
### Configurer la connexion
Il est imp�ratif de cr�er un fichier contenant les param�tres de connexion au SGBDR. Ce fichier doit �tre nomm� *dbCnxConf.php* et �tre format� de la mani�re suivante :

```php
<?php
/**
 * Fichier de configuration des param�tres de connexion � la base de donn�es.
 * Ce fichier est g�n�r� automatiquement lors de la phase initiale d'installation.
 */
$db_app_server  = 'localhost';          // Adresse du serveur de base de donn�es
$db_app_schema  = 'testjem';            // Sch�ma � cartographier (base de donn�es de l'application)
$db_app_user    = 'testjem';            // Utilisateur de l'application pouvant se connecter au SGBDR
$db_app_mdp     = 'testjem';            // Mot-de-passe de l'utilisateur de l'application
$db_app_type    = 'pgsql';              // Type de SGBDR, MySQL, PostGreSQL, Oracle, etc..
$db_app_port    = '5432';               // Port sur lequel on peut se connecter au serveur
$db_meta_schema = 'INFORMATION_SCHEMA'; // Sch�ma o� pourront �tre collect�es les informations sur le sch�ma de travail
/**
 * Cr�ation des constantes globales de l'application
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

#### Les types de SGBDR support�s
� ce jour, ce n'est utilisable qu'avec MySQL et PostGreSQL. Je n'ai pas test� avec les forks de MySQL (MariaDb, percona et autres) mais dans la mesure o� ils sont compatibles, �a ne devrait pas pr�senter de blocage.
La valeur � utiliser pour la variable *$db_app_type* :

- MySQL : *mysql*
- PostGreSQL : *pgsql*

Ce fichier devra �tre plac� dans le r�pertoire o� sont situ�s vos �ventuels autres fichiers de configuration selon l'architecture de votre application.
Il conviendra par la suite de pouvoir fournir en temps voulu le chemin absolu vers ce fichier. D�s le d�part, s'il n'existe pas, un autre fichier de configuration sera g�n�r� automatiquement et sera indispensable au bon fonctionnement du package. Ce fichier sera g�n�r� en deux version, le premier nomm� *dbConf.php* pourra �tre assez facilement lu par n'importe quel d�veloppeur, le second qui sera privil�gi� pour l'utilisation par l'application sera nomm� *dbConf_compact.php* et correspondra strictement au m�me contenu mais compact� et ramen� sur une seule ligne.
Ce fichier d�crit en d�tail l'ensemble de la structure de donn�es, tables, tables relationnelles et vues, colonnes, cl�s et autres informations d�taill�es. Il sera utilis� par les objets destin�s � toutes les op�rations en �criture, insertion, mises � jour ou suppression.

### M�thodes globales accessibles
Deux m�thodes de base seront indispensables :

- setRequete($sql, $aParams = array(), $cache = true)
D�finit la requ�te � ex�cuter, en option on peut indiquer des param�tres dans un tableau associatif o� chaque index est le nom de la colonne vis�e associ� � sa valeur qui doit y �tre affect�e, et un troisi�me param�tre permet de d�sactiver la mise en cache du r�sultat si ce cache est globalement activ� par d�faut;
- execute()
Cette m�thode permet d'ex�cuter directement une m�thode d�finie avec setRequete(). On peut alors envoyer une requ�te, un appel de proc�dure stock�e ou une fonction utilisateur voire m�me une requ�te en �criture bien que cette derni�re option ne soit pas recommand�e (Voir plus loin l'�criture de donn�es)


Deux autres m�thodes nous int�ressent principalement ici et ne seront utilis�es que lorsqu'on devra enregistrer des cr�ation ou modifications de donn�es :

- startTransaction()
D�marre une transaction si les tables utilisent un moteur transactionnel. Toutes les requ�tes suivantes seront alors incluses dans une transaction jusqu'� ce qu'on appelle la m�thode finishTransaction();
- finishTransaction($bOk)
Termine une transaction : le param�tre attendu est un booleen, TRUE ex�cutera un COMMIT, FALSE ex�cutera un ROLLBACK;

Une autre m�thode pourra se r�v�ler pratique lors de la phase de d�veloppement de votre application :

- getErreurs()
Retourne la liste des erreurs rencontr�es sous la forme d'un tableau


# Lecture de donn�es
Il n'y a pas de g�n�rateur de requ�tes, � tout le moins pour l'instant. On devra �crire soi-m�me les requ�tes en lecture qui devront �tre ex�cut�es pour la collecte de donn�es.

Chaque requ�te peut �tre param�tr�e, sera ex�cut�e avec PDO et retournera une donn�e unique, une ligne de donn�es ou bien un tableau de donn�es voire m�me un objet.
On s'appuiera sur une instance de la classe jemdev\dbrm\vue qu'on d�finira au pr�alable.

Exemple : par convention, l'instance de connexion sera la variable � $oVue � et aura �t� d�finie en amont (entendez le mot de � vue � au sens SQL du terme).

```php
<?php
/* D�finition de la requ�te */
$sql = "SELECT utl_id, utl_nom, utl_prenom, utl_dateinscription ".
       "FROM t_utilisateur_utl ".
       "WHERE utl_dateinscription > :p_utl_dateinscription ".
       "ORDER BY utl_nom, utl_prenom";
/* Initialisation de param�tre(s) */
$params = array(':p_utl_dateinscription' => '2015-10-15');
/* Initialisation de la requ�te */
$oVue->setRequete($sql, $params);
/* R�cup�ration des donn�es */
$infosUtilisateur = $oVue->fetchAssoc();
```

#### Note:
Le nom de l'objet $oVue dans cet exemple n'est pas anodin, il faut entendre le mot *vue* au sens SQL du terme. Une vue dans une base de donn�es est une synth�se d'une requ�te d'interrogation de la base. On peut la voir comme une table virtuelle, d�finie par une requ�te.
## Les m�thodes accessibles
Le nom des m�thodes est inspir� de celles qu'on emploie avec l'extension MySQL. Le retours sont bien entendu similaires dans leur forme.

- fetchAssoc()
Retourne un tableau associatif de r�sultats o� les index sont les noms des colonnes ou alias d�termin�s dans la requ�te;
- fetchArray()
Retourne un tableau o� chaque colonne est pr�sent�e avec deux index, l'un num�rique, l'autre associatif avec le nom de la colonne;
- fetchObject()
Retourne un objet o� chaque colonne est une propri�t�;
- fetchLine($out = 'array')
Retourne une seule ligne de donn�es. On peut pr�ciser en param�tre quelle forme doit prendre le r�sultat en passant une des constantes suivantes :
    - vue::JEMDB\_FETCH\_OBJECT  = 'object' : indiquera un retour sous forme d'un objet;
    - vue::JEMDB\_FETCH\_ARRAY   = 'array' : indiquera un retour sous forme d'un tableau avec double index num�rique et associatif;
    - vue::JEMDB\_FETCH\_ASSOC   = 'assoc' : indiquera un retour sous forme d'un tableau associatif;
    - vue::JEMDB\_FETCH\_NUM     = 'num' : indiquera un retour sous forme d'un tableau index� num�riquement;
- fetchOne()
Retourne une donn�e unique;

# �criture de donn�es
### Les m�thodes accessibles
Sur une instance donn�e, vous disposez des m�thodes suivantes :

- init($aPk = null) : Initialise l'instance de la ligne. En arri�re plan, l'objet sera construit dynamiquement avec comme propri�t�s les colonnes de la table vis�e;
- sauvegarder() : Enregistre les modifications apport�es aux propri�t�s par une requ�te INSERT ou UPDATE selon qu'on a d�termin� ou non la cl� primaire;
- supprimer() : Supprime la ligne de la table par une requ�te DELETE. Dans le cas o� un moteur transactionnel serait utilis� et que des clauses d'int�grit� r�f�rentielles auraient �t� d�finies (CONSTRAINT), la m�thode pourra retourner une erreur si des donn�es faisant r�f�rences � la ligne devant �tre supprim�e existent encore dans les tables li�es;
- startTransaction() : D�marre une transaction SQL;
- finishTransaction($bOk) : Termine une transaction SQL, le param�tre attendu est un bool�en indiquant si on doit effectuer un COMMIT ou un ROLLBACK;

## Mise en pratique
On �crit des donn�es, comme mentionn� en introduction, que sur une seule table � la fois. Pour ce faire, on cr�e un objet repr�sentant une ligne de ladite table.
Voici d'abord un exemple sch�matique :

```php
<?php
/* On cr�e l'instance de la ligne � partir du nom de la table cible */
$oInterlocuteur = $oDbrm->getLigneInstance('t_interlocuteur_int');
/*
 * On d�termine si on dispose ou non de la cl� primaire de la ligne
 * et on stocke �a dans un tableau associatif
 */
$aPk = (!empty($int_id)) ? array('int_id' => $int_id) : null;
/* On initialise l'instance */
$oInterlocuteur->init($aPk);
/*
 * D�s cet instant, notre objet pr�sente chaque colonne de la
 * table t_interlocuteur_int comme des propri�t�s qu'on peut modifier
 */
$oInterlocuteur->int_nom    = $int_nom;
$oInterlocuteur->int_prenom = $int_prenom;
if(!is_null($int_dateinscription))
{
    $oInterlocuteur->int_dateinscription = $int_dateinscription;
}
/* On peut maintenant sauvegarder ces informations */
$enreg = $oInterlocuteur->sauvegarder();
/*
 * Termin�, les �critures pour cette ligne sont termin�es.
 * On peut r�cup�rer la valeur de la cl� primaire si n�cessaire et s'il
 * s'agissait d'une cr�ation. Cette cl� primaire est automatiquement g�r�e
 * et initialis�e dans l'instance.
 * S'il y a eu une erreur, la m�thode sauvegarder retournera l'erreur, sinon
 * elle retournera TRUE
 */
if(true == $enreg)
{
    $int_id = $oInterlocuteur->int_id;
    /*
     * Ici, si par exemple vous avez d'autres donn�es � enregistrer, donn�es qui
     * d�pendent la la r�ussite de ce premier enregistrement, vous continuez
     * sur l'enregistrement suivant, exemple :
     */
    $oAdresse = $oDbrm->getLigneInstance('t_adresse_adr');
    $aPk = (!empty($adr_id)) ? array('adr_id' => $adr_id) ? null;
    $oAdresse->init($aPk);
    $oAdresse->int_id = $int_id;
    $oAdresse->adr_numero = $adr_numero;
    // ... etc...
    $enreg = $oAdresse->sauvegarder();
    // etc... suite selon les besoins.
}
else
{
    // Ici, le code permettant la gestion de l'erreur selon vos propres mani�res de faire.
}
```

Une utilisation pratique vous am�nera sans doute � r�partir les requ�tes en �criture sur diff�rentes tables dans diff�rentes fonctions/m�thodes appel�es � partir d'un endroit unique, ce qui vous permettra d'utiliser au besoin le mode transactionnel. En d�marrant la transaction au d�part, vous ex�cutez chaque enregistrement, et si une des m�thodes retourne FALSE � cause d'une erreur, vous pourrez interrompre la succession des enregistrements et terminer la transaction par un ROLLBACK, �vitant ainsi de pourrir vos tables avec des donn�es orphelines ou incoh�rentes.

### Ce qu'on ne peut pas faire (pour l'instant)

Actuellement, il reste quelques �l�ments en _TODO-LIST_ et en particulier, lors de l'�criture de donn�es, la possibilit� d'affecter non pas une valeur mais un appel de fonction SQL. Supposons par exemple que vous vouliez utiliser une fonction de chiffrement int�gr�e de MySQL pour affecter une valeur. Il n'est pour l'instant pas possible de faire ceci :

```ruby
$instanceLigne->nom_colonne = "AES_ENCRYPT('valeur', 'Cl� de chiffrement')";
```

#### Comment contourner le probl�me.
Pour une utilisation quotidienne, ce n'est pas un r�el probl�me, ce type de cas particulier �tant relativement rare. Si cependant vous devez pouvoir effectuer une telle op�ration, vous avez deux options.

- La premi�re consiste � envoyer une valeur en clair et ajouter un trigger sur la table avec un BEFORE INSERT qui ex�cutera alors la fonction SQL � appliquer sur la valeur pour l'affecter � la colonne;
- La seconde consiste � �crire vous-m�me la requ�te en �criture INSERT ou UPDATE et � la faire ex�cuter avec la m�thode execute() de l amani�re suivante :

```php
<?php
/* On a d'abord besoin d'une instance de jemdev\dbrm\vue */
$oVue = $oDb->getDbrmInstance();
/* On d�finit la requ�te SQL d'insertion */
$sql  = "INSERT INTO matable (col_login, col_motdepasse)".  
        "VALUES('Toto', AES_ENCRYPT('valeur', 'Cl� de chiffrement'))";  
$oVue->setRequete($sql);  
/* Ex�cution de la requ�te. */
$enreg = $oVue->execute();
```

La suite du code ne change pas.

Il n'a pas �t� pr�vu pour l'instant de pouvoir effectuer une mise � jour ou encore une suppression de lignes multiples dans la mesure o� une mise � jour s'effectuera uniquement en fonction de la valeur d'une cl� primaire. Pratiquant l'utilisation au quotidien de ce package depuis d�j� de nombreuses ann�es et ce sur une application de gestion, je n'ai en r�alit� jamais eu besoin d'impl�menter cette possibilit�. Et pour les rares fois o� �a doit se produire, je peux contourner ce manque en collectant la liste des cl� primaires � prendre en compte dans une mise � jour et chaque ligne sera trait�e individuellement dans une boucle.

-----------------------------------
# Une gestion de cache dynamique (exp�rimental)
Un probl�me d'acc�s � la configuration du serveur MySQL sur lequel je travaillais m'interdisait de param�trer le cache int�gr� et m�me tout simplement de l'activer par d�faut. Souhaitant pouvoir disposer d'un syst�me de gestion de cache de requ�tes, j'ai ajout� des classes permettant de g�rer cet aspect.

Globalement, chaque requ�te en lecture peut, si le cache est activ�, stocker le r�sultat en cache sur fichier voire m�me sur MemCache. Toute �criture sur une des table va r�g�n�rer le cache pour les requ�tes o� est impliqu�e la table en question. La dur�e de vie du cache est donc fonction de l'ex�cution de nouvelles �critures et non d'une dur�e de vie pr�-d�finie. Si le r�sultat d'une requ�te est valide pendant trois minutes et qu'une �criture intervient, le cache est renouvel�, si ce m�me r�sultat est toujours valide apr�s trois semaines, il est parfaitement inutile de le r�g�n�rer.

Certaines m�thodes permettent de r�g�n�rer manuellement le cache pour certaines tables. Par exemple, si lors d'une �criture sur une table un trigger va d�clencher l'ex�cution d'une proc�dure stock�e cr�ant des �critures sur d'autres tables, il sera important de r�g�n�rer le cache sur ces autres tables. Il n'est pas possible de d�tecter ces �critures en PHP dans la mesure o� c'est le SGBDR qui g�re �a directement. De m�me si des t�ches CRON d�clenchent des �critures sans passer par PHP, il n'est pas possible d'intercepter cette information pour mettre � jour le cache correspondant, il conviendra donc d'�crire un code PHP qui effectuera ce nettoyage, code qui devra �tre ex�cut� dans une t�che � ajouter au CronTab.

Par d�faut, le cache n'est pas activ�, et si vous avez la possibilit� de g�rer le cache int�gr� de votre SGBDR, ce sera alors une solution pr�f�rable et plus performante.

-----------------------------------
# Conclusion
Ce package se veut simple d'utilisation de fa�on � ne pas perdre le d�veloppeur dans les complications de l'impl�mentation, et ce sans avoir � se pr�occuper du type de serveur de base de donn�es utilis�, que ce soit MySQL, Oracle, SQL-Server ou tout autre serveur.
## � venir
Il reste cependant � d�velopper le code qui permettra d'utiliser des SGBDR autres que MySQL ou PostGreSQL, codes qui pour l'instant n'existent pas. Il s'agit de pouvoir construire le tableau de configuration d'un sch�ma de donn�es. MySQL et PostGreSQL impl�mentent INFORMATION_SCHEMA, ce qui facilite grandement ce travail, mais tous les SGBDR ne l'impl�mentent pas, comme par exemple Oracle. Il existe cependant d'autres mani�re de collecter ces informations pour aboutir au m�me r�sultat.

Par la suite, le fonctionnement s'appuyant sur PDO, l'int�gration de jemdev\dbrm pourra se faire dans n'importe quel projet.

## Les projets � plus long terme
L'id�e d'un g�n�rateur de requ�tes automatis� flotte dans l'air depuis pas mal de temps mais requiert un niveau de connaissances en math�matiques que je n'ai malheureusement pas.
Il est question de s'appuyer sur la th�orie des graphes pour d�terminer quelles jointures devront �tre �tablies pour n'avoir � d�finir que les colonnes de telle ou telle table est attendue pour que le moteur construise automatiquement le chemin appropri�.
Le fichier de configuration permet d'ores et d�j� de cr�er une matrice (le code n'est pas int�gr� dans le package mais est d�j� pr�t et op�rationnel), il reste � d�finir l'algorithme appropri� de fa�on � construire des requ�tes respectant les standards les plus exigeants.

Toute contribution en la mati�re sera bienvenue.

[CeCILL V2]: http://www.cecill.info/licences/Licence_CeCILL_V2-fr.html "Texte complet de la licence CeCILL version 2"
[Hoa\Registry]: https://github.com/hoaproject/Registry "Le package Hoa\Registry sur Github"
[Message]: http://jem-web.info/cv/message.html "Envoyer un message � Jean Mollin�"
[github.com/jemdev/dbrm]: https://github.com/jemdev/dbrm "Le package jemdev\dbrm sur Github"
[packagist.org/packages/jemdev/dbrm]: https://packagist.org/packages/jemdev/dbrm "La package jemdev\dbrm sur Packagist"
