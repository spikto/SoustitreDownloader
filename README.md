SoustitreDownloader
===================

Ce script PHP permet de télécharger des sous-titres automatiques à partir du site addic7ed.com


Comment ça marche ?
-------------------

    php subtitleDownloader.php arg1 [arg2 [arg3 [arg4 [arg5]]]]

**arg1** Chemin des fichiers pour lesquels il faut trouver un sous-titre.

**arg2** Chemin final où le fichier et son sous-titre seront déplacés.
 
**arg3** Option supplémentaire 
	
	f : Création du dossier de la série s'il n'existe pas
	d : Force le téléchargement d'un sous-titre d'une version différente
	c : Nettoie le nom du fichier pour ne garder que la serie, la saison et l'épisode
	r : Recherche récursive dans les sous-répertoires.

**arg4** Email auquel le fichier de sous-titre sera automatiquement envoyé en plus d'être enregistrer sur le disque.

**arg5** Filtrer les sous-titres envoyés sur une serie précise.
