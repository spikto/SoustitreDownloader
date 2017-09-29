SoustitreDownloader
===================

Ce script PHP permet de télécharger des sous-titres automatiques à partir du site addic7ed.com


Prérequis
-------------------

	sudo apt-get install curl php5-curl

Comment ça marche ?
-------------------

    php subtitleDownloader.php arguments

**--path=path_to_folder** Chemin des fichiers pour lesquels il faut trouver un sous-titre.

**--move=path_to_destination** Chemin final où le fichier et son sous-titre seront déplacés.

**-f, --createFolder** Création du dossier de la série s'il n'existe pas
**-d, --createFolder** Force le téléchargement d'un sous-titre d'une version différente
**-c, --cleanName** Nettoie le nom du fichier pour ne garder que la serie, la saison et l'épisode
**-r, --recursive** Recherche récursive dans les sous-répertoires.
**-u, --update** Mise à jour du script automique (prérequis : commande git installé, recupération du script par git, ex : "git clone git://github.com/spikto/SoustitreDownloader.git")

**--email=adress_mail** Email auquel le fichier de sous-titre sera automatiquement envoyé en plus d'être enregistrer sur le disque.

**--email_filter=tvshow_name**  Filtrer les sous-titres envoyés sur une serie précise.

**--lang=language** Choisir la langue des sous-titres (fr, en, it, de)

**--date=days** Vérifie la date des fichiers, avec le nombre de jours avant la date actuelle.
