<?php
/**
 *  This code was inspired by MudNames by Ragnar Hojland Espinosa. <ragnar (at) ragnar-hojland (dot) com>
 *  This php version was written by Xrogaan. <xrogaan - gmail - com>
 *
 * @author xrogaan <xrogaan - gmail - com> (c) 2009
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 1.2
 * 
 * 
 */

class mudnames_dico {

	protected $particle = array(
		'PRE' => 'P',  'MID' => 'M',  'SUF'  => 'S',
		'NOUN' => 'N', 'ADJ' => 'A',  'NADJ' => 'X',
	);

	/**
	 * Capacitées : associations des parties de nom disponible.
	 */
	protected $caplist = array(
		"PS", "PMS", "PM", "N", "X",
		"NA", "XA" , "NX"
	);
	
	protected $capabilities = array();
	
	protected $file;
	protected $directory;
	protected $forcedcap;
	protected $file_particles;
	public $debug;
	
	/**
	 * Va charger le contenu du dictionnaire de nom '$file', classifier ses différentes parties ($particles)
	 * dans $file_particles et va détecter des différentes capacités du dictionnaire de nom (association possible
	 * de différentes parties).
	 *
	 *
	 */
	function __construct($file, $directory = './data/') {
		$this->file = $file;
		$this->directory = realpath($directory) . '/';
		
		if (!file_exists($this->directory . $this->file)) {
			trigger_error('File <em>'.$this->directory . $this->file.'</em> not found',E_USER_ERROR);
		}

		$handle = fopen($this->directory . $this->file,'r');

		if ($handle) {
			$line = 0;
			while (!feof($handle))
			{
				$line++;
				$buffer = fgets($handle, 4096);

				if (strpos($buffer,'#') === 0) {
					$current_part = str_replace(array("\n",'#'),'',$buffer);
					if (array_key_exists($current_part,$this->particle)) {
						$this->file_particles[$current_part] = array();
					} else {
						trigger_error ($this->directory.$this->file . ":$line: Unknow particle '$current_part'.",E_USER_WARNING);
					}
				}
				elseif (strlen($buffer) > 1 && strpos($buffer,'-----') === false)
					$this->file_particles[$current_part][] = str_replace("\n",'',$buffer);
				elseif (strlen($buffer) === 1)
					continue;

			}
			fclose($handle);
			
		}
		
		$check_particle = array_keys($this->file_particles);
		foreach($check_particle as $k => $v) { $check_particle[$k] = self::pfull2lite($v); }
		$parts = implode('',$check_particle);
		// génère un liste de capacité selon les différentes parties de nom présente dans le fichier
		foreach ($this->caplist as $capability)
		{
			if (strpos($parts, $capability) !== false) {
				$this->capabilities[] = $capability;
			}
		}
	}
	
	/**
	 * Détecte si un fichier doit avoir une génération statique de noms.
	 */
	function force_capability($cap) {
		if (in_array($cap,$this->capabilities)) {
			$this->currentcap = $cap;
			$this->forcedcap = true;
			return true;
		}
		return false;
	}
	
	/**
	 * Sélectionne une association de capacité de façons aléatoire et la retourne.
	 */
	public function select_capability() {
		
		self::check_cap_file();
		
		// If a forced capability exists, we apply it.
		if (!empty($this->forced_capability)) {
			foreach ($this->forced_capability as $fnct => $args) {
				if ($fnct == 'void') {
					self::force_capability($args);
				} else {
					call_user_func_array(array($this,$fnct),$args);
				}
			}
		}

		if (!$this->forcedcap) {
			if (count($this->capabilities)>1) {
				$this->currentcap = $this->capabilities[rand(0, count($this->capabilities) - 1)];
			} elseif (count($this->capabilities) == 1) {
				$this->currentcap = $this->capabilities[0];
			} else {
				trigger_error ($this->directory.$this->file . ":select_capability: No capability found.",E_USER_WARNING);
			}
		}
		
		return $this->currentcap;
	}
	
	/**
	 * Vérifie si un fichier .cap est présent pour le dictionnaire chargé dans l'objet.
	 *
	 * Si un tel fichier existe, cette fonction charge les actions prédéterminées du fichier.
	 * le .cap sert en général a forcer la capacité du dictionnaire.
	 */
	private function check_cap_file() {
		if (file_exists($this->directory . $this->file . '.cap')) {
			
			$this->forced_cap = true;
			$handle = fopen($this->directory . $this->file . '.cap','r');

			if ($handle) {
				while (!feof($handle))
				{
					$buffer = fgets($handle, 4096);

					if (!empty($buffer))
					{
						if (ctype_lower($buffer[0])) {
							list($function, $args) = explode(':',$buffer);
						} else {
							$function = 'void';
							$args = $buffer;
						}

						if ($function != 'void' && !method_exists($this,$function)) {
							trigger_error('Method `<em>'.$function.'</em>` does\'nt exists',E_USER_WARNING);
						} else {
							$this->forced_capability[$function] = explode(',',str_replace("\n",'',trim($args)));
						}

					}
				} // -- end while
				fclose($handle);
			} // -- end if
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Sors une partie de nom aléatoire selon le tag et le fichier associé.
	 */
	public function random_particle_from($particle) {
		if (!isset($this->file_particles[$particle]))
			trigger_error ($this->directory.$this->file . ":load_random_particle: Unknow particle '$particle'.",E_USER_WARNING);
		
		if (empty($this->file_particles[$particle]))
			return '';
		
		do {
			$randomId = rand(0,count($this->file_particles[$particle])-1);
			if (isset($this->file_particles[$particle][$randomId]))
				break;
		} while(1);
		
		$this->debug['particles_used'][$particle] = $this->file_particles[$particle][$randomId];
		
		return $this->file_particles[$particle][$randomId];
		
	}
	
	/**
	 * retourne la liste des parties présente dans le fichier dictionnaire
	 *
	 * @return array
	 */
	public function get_file_particle_list() {
		return array_keys($this->file_particles);
	}
	
	public function get_file_capability_list() {
		return $this->capabilities;
	}
	
	public function toString() {
		return $this->file;
	}
	
	/**
	 * Retourne la version courte d'un tag
	 */
	public function pfull2lite($par) {
		return $this->particle[$par];
	}

	/**
	 * Retourne la version longue d'un tag
	 */
	public function plite2full($par) {
		return array_search($par,$this->particle);
	}
	
	public function is_forced() {
		return $this->forcedcap;
	}
}

class mudnames {
	private $directory;
	private $files = array();
	private $dictionnary;
	private $particle_used = array();
	private $capability;
	
	private $info = array(
		'dictionnaries' => '',
		'particles_used' => array(),
		'capability' => '',
		'is_forced' => false,
	);
	
	function __construct($directory = './data') {
		$this->directory = realpath($directory) . '/';
		
		if ( !$dir = opendir($this->directory))
			return false;
		$r = array();

		while (($file = readdir($dir)) !== false) {

			// On ignore les fichiers caché et les fichier de capacités
			if ($file[0] == '.' || substr($file,-4) == '.cap') {
				continue;
			}

			if (is_file($this->directory.$file)) {
				$this->files[] = $file;
			}
		}
		sort($this->files);
	}
	
	public function generate_name_from($file='') {
		if (empty($file) || $file == "random") {
			$file = $this->files[rand(0,count($this->files)-1)];
		} else {
			if (!in_array($file,$this->files)) {
				trigger_error('file '.$file." doesn't exists", E_USER_ERROR);
			}
		}
		
		$this->info['dictionnaries'][] = $file;
		$this->current_file = $file;
		
		$this->dictionnary[$file] = new mudnames_dico($file);
		
		$current_cap = $this->dictionnary[$file]->select_capability();
		
		switch ($current_cap) {
			case 'N':
				$capability = 'NN';
				break;
			case 'X':
				$capability = 'XX';
				break;
			case 'XA':
				$capability = 'N' . (rand(0,1) ? 'A' : 'X' );
				break;
			case 'NX':
				$capability = (rand(0, 1) ? 'N' : 'A') . 'A';
				break;
			default:
				$capability = $current_cap;
		}
		
		$this->info['capability'][$file] = $capability;
		
		$name = '';
		for ($i = 0, $len = strlen($capability); $i < $len; $i++) {
			$particle = $this->dictionnary[$file]->plite2full($capability[$i]);
			$this->info['particles_used'][] = $particle;
			$name.= $this->dictionnary[$file]->random_particle_from($particle);
		}

		return ucfirst($name);
	}
	
	/**
	 * for debug purpose
	 */
	public function get_info($tag) {
		$tag = strtr($tag,' ','_');
		switch ($tag) {
			case 'file_used':
				$tag = 'dictionnaries';
			case 'capability':
				return $this->info['capability'][$this->current_file];
				break;
			case 'is_forced':
				return $this->dictionnary[$this->current_file]->is_forced();
				break;
			case 'capability_list':
				return implode(', ', $this->dictionnary[$this->current_file]->get_file_capability_list());
				break;
			case 'particles_used':
				return $this->dictionnary[$this->current_file]->debug['particles_used'];
			default:
				return "info not found";
		}
	}
	
	public function get_file_list() {
		return $this->files;
	}
}