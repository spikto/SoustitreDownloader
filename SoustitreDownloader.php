<?php
/**
 * SoustitreDownlaoder
 *
 * Ce script PHP permet de télécharger des sous-titres automatiques à partir du site addic7ed.com
 *
 * PHP 5
 *
 * @copyright     Copyright 2013, Spikto, Thomas Buée
 */
 
set_time_limit(0);

$app = new getFileSubtitle($argv);

/**
 * Gestion des sous-titres à télécharger
 */
class getFileSubtitle {
	private $extFile = array("mp4","mkv","m4v","avi","mov","wmv","mpg");
	private $fileToCheck=array();
	private $pathSearch;
	private $pathMove;

	public function __construct($argv) {
		$this->pathSearch = (isset($argv[1]) ? $argv[1] : "");
		$this->pathMove = (isset($argv[2]) ? $argv[2] : "");
		$this->logicPath();
		$this->findFile();
		$this->findSubtitle();
	}	
	
	public function logicPath() {
		if (substr($this->pathSearch, -1)!="/") $this->pathSearch .= "/";
		if (substr($this->pathMove, -1)!="/") $this->pathMove .= "/";
	}
	
	/**
	 * Recherche des sous-titres à télécharger
	 */
	public function findFile() {
		$path = $this->pathSearch;
		if ($path!="") {
			$list = glob($path."*");
			foreach($list as $l) {
				$info = pathinfo($l);
				if (is_file($l) && in_array($info["extension"], $this->extFile) && !preg_match("#VOSTF|VOSTFR#", $info["filename"])) {
					if (!file_exists($path.$info["filename"].".srt")) {
						$this->fileToCheck[] = new fileData($info);
					}
					else if ($this->pathMove!="") {
						$data = new fileData($info);
						$this->relocateEpisode($data);
					}
				}
			}
		}
	}	
	
	/**
	 * Déplace le fichier dans le dossier approprié : Série [ > Saison] > Episode
	 */
	public function relocateEpisode($data) {
		if (!file_exists($this->pathMove.$data->serie)) mkdir($this->pathMove.$data->serie);
		if (file_exists($this->pathMove.$data->serie."/Saison ".intval($data->saison))) $comp = "/Saison ".intval($data->saison);
		elseif (file_exists($this->pathMove.$data->serie."/Season ".intval($data->saison))) $comp = "/Season ".intval($data->saison);
		else $comp = "";
		rename($this->pathSearch.$data->info["basename"], $this->pathMove.$data->serie.$comp."/".$data->info["basename"]);
		rename($this->pathSearch.$data->info["filename"].".srt", $this->pathMove.$data->serie.$comp."/".$data->info["filename"].".srt");
	}
	
	/**
	 * Recherche du sous-titre
	 */
	public function findSubtitle() {
		if (count($this->fileToCheck)>0) {
			foreach($this->fileToCheck as $f) {
				$addicted = new addictedSubtitle($f);
				if ($addicted->findEpisode()) {
					$this->relocateEpisode($f);
					echo "Un sous-titre a été trouvé\n";
				}
				else {
					echo "Aucun sous-titre trouvé\n";
				}
			}
		}
		else {
			echo "Aucun sous-titre à rechercher.\n";
		}
	}
}


/**
 * Recupère les infos importantes à partir du nom du fichier
 */
class fileData {
	public $saison;
	public $episode;
	public $serie;
	public $version;
	public $info;
	
	public function __construct($info) {
		$this->info = $info;
		$this->readName();
	}
	
	public function readName() {
		$file = $this->info["filename"];
		//preg_match("#([^0-9]+)([0-9]{2})E([0-9]{2})#", $file, $result2);

		if (preg_match("#S([0-9]{2})E([0-9]{2})#", $file, $result)) {
			$this->saison = $result[1];
			$this->episode = $result[2];
			if (preg_match("#(.*)S".$this->saison."E".$this->episode."#", $file, $result2)) {
				$this->serie = trim(str_replace(".", " ", $result2[1]));
			}
		}
		else if (preg_match("#([0-9]{2})x([0-9]{2})#", $file, $result)) {
			$this->saison = $result[1];
			$this->episode = $result[2];
			if (preg_match("#(.*)".$this->saison."x".$this->episode."#", $file, $result2)) {
				$this->serie = trim(str_replace(".", " ", $result2[1]));
			}
		}

		preg_match("#(LOL|AFG|FQM|ASAP|EVOLVE)#", $file, $result3);
		$this->version = (isset($result3[1]) ? $result3[1] : "");
	}
	
	public function getSimpleName() {
		return $this->serie." ".$this->saison."x".$this->episode;
	}
	
}

/**
 * Base de source pour le téléchargement des sous-titres
 */
class sourceSubtitle {
	public $base;
	public $referer;
	public $search;

	public function __construct($search) {
		$this->search = $search;
	}
	
	protected function getDataFromLink($link) {
		$curl = curl_init(); 
		curl_setopt($curl, CURLOPT_URL, $this->base.$link); 
		//curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_COOKIESESSION, true); 
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
		if ($this->referer!="") curl_setopt($curl, CURLOPT_REFERER, $this->referer);

		$return = curl_exec($curl);
		curl_close($curl); 
		
		$this->referer = $this->base.$link;
		return $return;
	}
	
	public function findEpisode($nom) {
	
	}
	public function findSubtitle($link) {
	
	}
	public function saveSubtitle($lien) {
	
	}
	
}

/**
 * Source Addic7ed.com
 */
class addictedSubtitle extends sourceSubtitle {
	public $base = "http://www.addic7ed.com/";
	
	public function findEpisode() {
		$episodes = $this->getDataFromLink("search.php?search=".rawurlencode($this->search->getSimpleName())."&Submit=Search");
		
		preg_match("#<a href=\"([^\"]*)\"[^>]*>".$this->search->serie.".*".$this->search->saison."x".$this->search->episode.".*</a>#", $episodes, $result);

		if (count($result)>0) {
			return $this->findSubtitle($result[1]);
		}
		else {
			preg_match("#<a href=\"([^\"]*)\".*>.*".$this->search->saison."x".$this->search->episode.".*</a>#", $episodes, $result);
			if (count($result)>0) {
				return $this->findSubtitle($result[1]);
			}
		}
		return false;
	}

	public function findSubtitle($link) {
		$soustitres = $this->getDataFromLink($link);
		preg_match_all("#\/updated\/8\/([0-9/]*)#", $soustitres, $resultLink);
		$linkSubtitle="";
		var_dump($resultLink[1]);
		foreach($resultLink[1] as $l) {
			$valid = true;
			$resultVersion = array();
			$dec = explode("/", $l);
			preg_match_all("#Version([^<]*).*starttranslation.php\?id=".$dec[0]."&amp;fversion=".$dec[1].".*saveFavorite\(".$dec[0].",8,".$dec[1]."\)(.*Completed.*)\/updated\/8\/(".$dec[0]."\/".$dec[1].")#msu", $soustitres, $resultVersion, PREG_SET_ORDER);
			if (count($resultVersion) > 0) {
				preg_match("#([0-9]*\.?[0-9]*%? ?)Completed#", $resultVersion[0][2], $resultComplete);
				if (isset($resultComplete[1]) && $resultComplete[1]=="") {
					//$valid = true;
				}
				else {
					$valid = false;
				}
				if ($this->search->version!="") {
					if (strstr($resultVersion[0][1], $this->search->version)) {
						//$valid = true;
					}
					else {
						$valid = false;
					}
				}
					
			}
			if ($valid) {
				$linkSubtitle = "updated/8/".$l;
				break;
			}
		}
		if ($linkSubtitle!="") {
			return $this->saveSubtitle($linkSubtitle);
		}
		return false;
	}
	public function saveSubtitle($link) {
		$soustitre = $this->getDataFromLink($link);
		if ($soustitre!="") {
			$fp = fopen($this->search->info["dirname"]."/".$this->search->info["filename"].".srt", "a+");
			fwrite($fp, $soustitre);
			fclose($fp);
			return true;
		}
		return false;
	}
	
	
}