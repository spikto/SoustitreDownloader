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
 
//ini_set("display_errors", "1");
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
	private $emailSend;
	private $emailSerie;
	private $subLng;
	private $defaultLng = "fr";
	private $createFolder=false;
	private $cleanName=false;
	private $forceDownload=false;
	private $recursive=false;

	public function __construct($argv) {
		$this->pathSearch = (!empty($argv[1]) ? $argv[1] : "");
		$this->pathMove = (!empty($argv[2]) ? $argv[2] : "");
		$this->emailSend = (!empty($argv[4]) ? $argv[4] : "");
		$this->emailSerie = (!empty($argv[5]) ? $argv[5] : "");
		$this->subLng = (!empty($argv[6]) ? $argv[6] : $this->defaultLng);
		if (!empty($argv[3])) {
			for($i=0;$i<strlen($argv[3]);$i++) {
				if ($argv[3][$i]=="f") $this->createFolder=true;
				if ($argv[3][$i]=="d") $this->forceDownload=true;
				if ($argv[3][$i]=="c") $this->cleanName=true;
				if ($argv[3][$i]=="r") $this->recursive=true;
				if ($argv[3][$i]=="u") $this->updateScript();
			}
		}
		$this->logicPath();
		$this->findFile();
		$this->findSubtitle();
	}	
	
	public function updateScript() {
		exec("git reset --hard HEAD");
		exec("git pull origin master");
	}	
	
	public function logicPath() {
		if ($this->pathSearch!="" && substr($this->pathSearch, -1)!="/") $this->pathSearch .= "/";
		if ($this->pathMove!="" && substr($this->pathMove, -1)!="/") $this->pathMove .= "/";
	}
	
	/**
	 * Recherche des sous-titres à télécharger
	 */
	public function findFile() {
		$path = $this->pathSearch;
		if ($path!="") {
			$list = glob_perso($path, array(), $this->recursive);
			foreach($list as $l) {
				$info = pathinfo($l);
				if (is_file($l) && in_array($info["extension"], $this->extFile) && !preg_match("#VOSTF|VOSTFR#i", $info["filename"])) {
					if (!file_exists($info["dirname"]."/".$info["filename"].".srt")) {
						$this->fileToCheck[] = new fileData($info);
					}
					else if ($this->pathMove!="") {
						$data = new fileData($info);
						$this->relocateEpisode($data);
					}
				}
				else if (is_dir($l)) {
					$data = new fileData($info);
					if ($data->isValid()) {
						$sublist = glob_perso($l."/");
						 foreach($sublist as $sl) {
							$info = pathinfo($sl);
							if (is_file($sl) && in_array($info["extension"], $this->extFile) && !preg_match("#VOSTF|VOSTFR#i", $info["filename"])) {
								rename($sl, $path.$info["basename"]);
								$info = pathinfo($path.$info["basename"]);
								$this->fileToCheck[] = new fileData($info);
							}
							elseif (is_file($sl)) {
								unlink($sl);
							}
						}
						rmdir($l."/");
					}
				}
			}
		}
	}	
	
	/**
	 * Déplace le fichier dans le dossier approprié : Série [ > Saison] > Episode
	 */
	public function relocateEpisode($data) {
		$comp = "";
		if (file_exists($this->pathMove.$data->serie)) {
			$comp .= $data->serie;
		}
		elseif ($this->createFolder && !file_exists($this->pathMove.$data->serie)) {
			mkdir($this->pathMove.$data->serie);
			$comp .= $data->serie;
		}
		if ($comp!="") {
			if (file_exists($this->pathMove.$data->serie."/Saison ".intval($data->saison))) $comp .= "/Saison ".intval($data->saison);
			elseif (file_exists($this->pathMove.$data->serie."/Season ".intval($data->saison))) $comp .= "/Season ".intval($data->saison);
		}
		rename($data->info["dirname"].'/'.$data->info["basename"], $this->pathMove.$comp."/".$data->info["basename"]);
		rename($data->info["dirname"].'/'.$data->info["filename"].".srt", $this->pathMove.$comp."/".$data->info["filename"].".srt");
		if ($this->cleanName) {
			rename($this->pathMove.$comp."/".$data->info["basename"], $this->pathMove.$comp."/".$data->getSimpleName(3).".".$data->info["extension"]);
			rename($this->pathMove.$comp."/".$data->info["filename"].".srt", $this->pathMove.$comp."/".$data->getSimpleName(3).".srt");
		}
	}
	
	/**
	 * Recherche du sous-titre
	 */
	public function findSubtitle() {
		if (count($this->fileToCheck)>0) {
			foreach($this->fileToCheck as $f) {
				$addicted = new addictedSubtitle($f, $this->forceDownload, $this->subLng);
				if ($addicted->findEpisode()) {
					if ($this->emailSend!="" && (($this->emailSerie!="" && strtolower($f->serie)==strtolower($this->emailSerie)) || $this->emailSerie=="")) {
						sendEmail($this->emailSend, $f->getSimpleName(1), $f->info["filename"], $this->pathSearch);
					}
					if ($this->pathMove!="") {
						$this->relocateEpisode($f);
					}
					echo $f->getSimpleName(1)." : Un sous-titre a été trouvé\n";
				}
				else {
					echo $f->getSimpleName(1)." : Aucun sous-titre trouvé\n";
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

		if (preg_match("#S([0-9]{2})E([0-9]{2})#msui", $file, $result)) {
			$this->saison = $result[1];
			$this->episode = $result[2];
			if (preg_match("#(.*)S".$this->saison."E".$this->episode."#msui", $file, $result2)) {
				$this->serie = $this->cleanSerie($result2[1]);
			}
		}
		else if (preg_match("#([0-9]{1,2})x([0-9]{2})#", $file, $result)) {
			$this->saison = $result[1];
			$this->episode = $result[2];
			if (preg_match("#(.*)".$this->saison."x".$this->episode."#", $file, $result2)) {
				$this->serie = $this->cleanSerie($result2[1]);
			}
		}
		else if (preg_match_all("#[. ]([0-9])([0-9]{2})[. ]#", $file, $result, PREG_SET_ORDER)) {
			$result = end($result);
			$this->saison = ($result[1]<10 ? "0".$result[1] : $result[1]);
			$this->episode = $result[2];
			if (preg_match("#(.*)".$result[1].$this->episode."#", $file, $result2)) {
				$this->serie = $this->cleanSerie($result2[1]);
			}
		}
		preg_match("#(LOL|AFG|FQM|ASAP|EVOLVE|IMMERSE|2HD|KILLERS)#msui", $file, $result3);
		$this->version = strtoupper(isset($result3[1]) ? $result3[1] : "");
	}
	
	public function cleanSerie($serie) {
		$tabReplace = array(
			"." => " ",
			"S H I E L D " => "S.H.I.E.L.D.",
			"SHIELD " => "S.H.I.E.L.D.",
			"Marvels" => "Marvel's"
		);
		foreach($tabReplace as $b => $f) {
			$serie = str_replace($b, $f, $serie);
		}
		$serie = ucwords(trim($serie));
		return $serie;
	}

	public function getSimpleName($type=0) {
		if ($type==0) {
			return $this->serie." ".$this->saison."x".$this->episode;
		}
		else if ($type==1) {
			return $this->serie." S".$this->saison."E".$this->episode;
		}
		else if ($type==2) {
			return $this->serie." ".$this->saison.$this->episode;
		}
		else if ($type==3) {
			return $this->serie." S".$this->saison." E".$this->episode;
		}
	}
	
	public function isValid() {
		return ($this->serie!="" && $this->saison!="" && $this->episode);
	}
}

/**
 * Base de source pour le téléchargement des sous-titres
 */
class sourceSubtitle {
	public $lng;
	public $base;
	public $referer;
	public $search;
	public $forceExistant;
	public $tabLng;

	public function __construct($search, $force = false, $lng = "fr") {
		$this->search = $search;
		$this->forceExistant = $force;
		$this->lng = $lng;
	}
	
	protected function getDataFromLink($link) {
		$cpt = 0;
		$return = false;
		while($return==false && $cpt<3) {
			$curl = curl_init(); 
			curl_setopt($curl, CURLOPT_URL, $this->base.$link); 
			//curl_setopt($curl, CURLOPT_HEADER, true);
			curl_setopt($curl, CURLOPT_COOKIESESSION, true); 
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
			curl_setopt($curl, CURLOPT_TIMEOUT, 240); 
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 240); 
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
			if ($this->referer!="") curl_setopt($curl, CURLOPT_REFERER, $this->referer);

			$return = curl_exec($curl);
			curl_close($curl); 
			$cpt++;
		}
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
	public $tabLng = array("fr" => 8, "en" => 1, "it" => 7, "de" => 17);
	
	public function __construct($search, $force = false, $lng = "fr") {
		parent::__construct($search, $force, $lng);
		// Verifie que la langue saisie existe
		if (isset($this->tabLng[$this->lng])) {
			$this->lng = $this->tabLng[$this->lng];
		}
		else {
			$this->lng = 8;
		}
	}

	public function findEpisode() {
		$shows = $this->getDataFromLink("shows.php");
		preg_match("#<option value=\"([0-9]*)\" >".$this->search->serie."</option>#i", $shows, $result);
		if (count($result)>0) {
			return $this->findSubtitle("ajax_loadShow.php?show=".$result[1]."&season=".$this->search->saison."&langs=|".$this->lng."|&hd=0&hi=0");
		}
		return false;
	}

	public function findSubtitle($link, $data=null) {
		$soustitres = ($data==null ? $this->getDataFromLink($link) : $data);
		$blocs = explode("<tr class=\"epeven completed\">", $soustitres);
		$completedLink = array();
		$linkSubtitle="";
		if (count($blocs)>1) unset($blocs[0]);
		foreach ($blocs as $b) {
			$valid = true;
			preg_match("#<td class=\"c\"><a href=\"([a-zA-Z0-9/-]*)\">Download</a></td>#", $b, $resultLink);
			if(!empty($resultLink) && 
				preg_match("#<td>".intval($this->search->saison)."</td><td>".intval($this->search->episode)."</td>#", $b) && 
				preg_match("#<td class=\"c\">Completed</td>#", $b)) {
				if ($this->search->version!="" && preg_match("#<td class=\"c\">".$this->search->version."</td>#", $b)) {
					$l = $resultLink[1];
				}
				else {
					$completedLink[] = $resultLink[1];
				}
			}
			else {
				$valid = false;	
			}
			if ($valid) {
				$linkSubtitle = $l;
				break;
			}
		}
		if ($linkSubtitle=="" && !empty($completedLink)) {
			$linkSubtitle = $completedLink[0];
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

function sendEmail($to, $subject, $file, $path) {
	$random_hash = md5(date('r')); 
	$headers = "From: script@gmail.com\r\nReply-To: script@gmail.com";
	$headers .= "\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"PHP-mixed-".$random_hash."\""; 
	$attachment = chunk_split(base64_encode(file_get_contents($path.$file.".srt"))); 
	$message = "
--PHP-mixed-".$random_hash."
Content-Type: multipart/alternative; boundary=\"PHP-alt-".$random_hash."\"

--PHP-alt-".$random_hash."
Content-Type: text/plain; charset=\"utf-8\"
Content-Transfer-Encoding: 7bit

Nouveau fichier !!

--PHP-alt-".$random_hash."
Content-Type: text/html; charset=\"utf-8\" 
Content-Transfer-Encoding: 7bit

<h2>Nouveau fichier !!</h2>

--PHP-alt-".$random_hash."--

--PHP-mixed-".$random_hash."
Content-Type: application/octet-stream; name=\"".$file.".srt\"
Content-Transfer-Encoding: base64  
Content-Disposition: attachment  

".$attachment."
--PHP-mixed-".$random_hash."-- 

";
	ob_clean();
	return @mail($to, $subject, $message, $headers);
}


function glob_perso($path, $folder=array(), $recursive=false) {
	$globalPath;
	$list = array();
	if (!empty($folder)) {
		if (!is_array($folder)) $folder = array($folder);
		foreach ($folder as $value) {
			if (file_exists($path.$value)) {
				$path = $path.$value."/";
			}
			else {
				$handle = opendir($path);
				if ($handle) {
					while (false !== ($entry = readdir($handle))) {
						if (strtolower($entry)==strtolower($value)) {
							$path = $path.$entry."/";
						}
					}
					closedir($handle);
				}
			}
		}
	}
	$list = glob_recursive($path, $recursive);
	return $list;
}

function glob_recursive($path, $recursive=false) {
	$list = array();
	if (file_exists($path)) {
		$path = substr($path, -1)!="/" ? $path."/" : $path;
		$handle = opendir($path);
		if ($handle) {
			while (false !== ($entry = readdir($handle))) {
				if ($entry != "." && $entry != "..") {
					$list[] = $path.$entry;
					if ($recursive && is_dir($path.$entry)) $list = array_merge($list, glob_recursive($path.$entry."/", $recursive));
				}
			}
			closedir($handle);
		}
	}
	return $list;
}
