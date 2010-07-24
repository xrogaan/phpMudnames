<?php
/**
 *  This code was inspired by MudNames by Ragnar Hojland Espinosa. <ragnar (at) ragnar-hojland (dot) com>
 *  This php version was written by Xrogaan. <xrogaan - gmail - com>
 *
 * @author xrogaan <xrogaan - gmail - com> (c) 2009
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 1.3
 * 
 * 
 */


class Mudnames_Dictionnaries {

    protected $_directory;
    protected $_dictionnaries = array();
    protected $_dictionnaries_instance = array();

	/**
	 * Capability : different possibilities of connection between several particles
	 */
	private $_caplist = array(
		"PS", "PMS", "PM", "N", "X",
		"NA", "XA" , "NX"
	);

    /**
     * Build a list of files as dictionnaries list.
     *
     * @param string $directory directory where the dictionnaries can be found
     */
    public function __construct($directory='./data/') {
        if (!file_exists($directory)) {
            throw new InvalidArgumentException ('Directory \''. $directory . '\' doesn\'t exists.');
        }

        if (!is_dir($directory)) {
            throw new InvalidArgumentException ('Directory \''. $directory . '\' isn\'t a directory.');
        }

        $this->_directory = $directory;

        $dh = opendir($this->_directory);
        while(false !== ($file = readdir($dh))) {
            $this->_dictionnaries[$file] = $this->_directory . $file;
        }
        closedir($dh);
    }

    /**
     * Return true if the dictionnary file exists. If not, return false.
     *
     * @param string $name dictionnary name
     * @return boolean
     */
    public function dictionnary_exists($name) {
        return array_key_exists($name, $this->_dictionnaries);
    }

    public function open_dictionnary($name='random') {
        switch ($name) {
            case 'random':
                $keys = array_flip($this->_dictionnaries);
                $name = $keys[rand(0,count($keys)-1)];
            default:
                if (!self::dictionnary_exists($name)) {
                    throw new UnexpectedValueException("Dictionnary '$name' doesn't exists.");
                }
        }
        $this->_dictionnaries_instance[$name] = new Mudnames_Dictionnaries($this->_dictionnaries[$name]);
        return $this->_dictionnaries_instance[$name];
    }
}

class Mudnames_Dictionnary {
    /**
	 * Name's Particles
	 * Currently used : PRE, MID, SUF.
	 */
	private $_particle = array(
		'PRE' => 'P',  'MID' => 'M',  'SUF'  => 'S',
		'NOUN' => 'N', 'ADJ' => 'A',  'NADJ' => 'X',
	);

    protected $capabilities = array();
    protected $dictionnary_name;

    /**
     * true if the capabilities isn't random
     * @var boolean
     */
    protected $forcedcap;

    /**
     * Capabilities if forced.
     */
    protected $forced_capability;
    
    /**
     * File's particles list.
     * @var array
     */
    protected $file_particles = array();

    public $debug;

    private $filename;

    public function __construct($filename) {
        if (file_exists($filename)) {
            $this->filename = $filename;
        } else {
            throw new InvalidArgumentException('File <em>' . $filename . '</em> not found');
        }

        $line = 0;
        $handle = @fopen($this->filename, 'r');

        if ($handle === false) {
            throw new RuntimeException('Can not open file ' . $this->filename . '.');
        }

        while (!feof($handle)) {
            $line++;
            $buffer = fgets($handle, 4096);
            if (strpos($buffer,'#') === 0) {
                $current_part = ltrim(rtrim($buffer,"\r\n"),'#');
                if (array_key_exists($current_part,$this->_particle)) {
                    $this->file_particles[$current_part] = array();
                } else {
                    trigger_error ($this->filename . ":$line: Unknow particle '$current_part'.", E_USER_WARNING);
                }
            } elseif (strlen($buffer) > 1 && strpos($buffer,'-----') === false) {
                $this->file_particles[$current_part][] = rtrim($buffer,"\r\n");
            } elseif (strlen($buffer) === 1) {
                continue;
            }
        }
        fclose($handle);

        self::set_capabilities();
    }

    public function set_capabilities() {
        // Build the acronym to know the capacity of the file.
        $check_particle = array_map(array($this, 'pfull2lite'), array_keys($this->file_particles));
        
        if (in_array('P', $check_particle) && in_array('S', $check_particle)) {
            $this->capabilities[] = 'PS';
            if (in_array('M', $check_particle)) {
                $this->capabilities[] = 'PMS';
            }
        }

        if (in_array('N', $check_particle) && in_array('A', $check_particle)) {
            $this->capabilities[] = 'NA';
        }

        if (in_array('N', $check_particle) && in_array('X', $check_particle)) {
            $this->capabilities[] = 'NX';
        }

        if (in_array('X', $check_particle) && in_array('A', $check_particle)) {
            $this->capabilities[] = 'XA';
        }

        if (in_array('N', $check_particle)) {
            $this->capabilities[] = 'N';
        }

        if (in_array('X', $check_particle)) {
            $this->capabilities[] = 'X';
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
	 * Check if a capability file is present for the current file.
	 *
     * If it's present, its content is loaded into the core. It is usefull
     * to force actions in the choice of particles. Some dictionnary have 3
     * set of particles like 'PMS', but if the object return only a name
     * containing the parts 'P' and 'S', the name could mean absolutly nothing.
     * So, the capability file is here to force the object to get the full
     * capacities of the dictionnary by triggering the method self::force_capability().
	 *
	 * .cap files format :
	 * [functionName][:arguments]
	 */
	private function check_cap_file() {
		// Non-lethal error here. If we can't open it, we leave it alone.
		if (file_exists($this->filename. '.cap') && is_readable($this->filename . '.cap')) {

			$this->forced_cap = true;
			$handle = @fopen($this->filename . '.cap', 'r');

			if ($handle) {
				while (!feof($handle)) {
					$buffer = fgets($handle, 4096);

					if (!empty($buffer)) {
						if (ctype_lower($buffer[0])) {
							list($function, $args) = explode(':',$buffer);
						} else {
							$function = 'void';
							$args = $buffer;
						}

						if ($function != 'void' && !method_exists($this,$function)) {
							trigger_error('Method `<em>'.$function.'</em>` does\'nt exists',E_USER_WARNING);
						} else {
							$this->forced_capability[$function] = explode(',',trim($args));
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
			trigger_error ($this->filename . ":load_random_particle: Unknow particle '$particle'.",E_USER_WARNING);

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

	public function __toString() {
		return $this->filename;
	}

	/**
	 * Retourne la version courte d'un tag
	 */
	public function pfull2lite($par) {
		return $this->_particle[$par];
	}

	/**
	 * Retourne la version longue d'un tag
	 */
	public function plite2full($par) {
		return array_search($par,$this->_particle);
	}

	public function is_forced() {
		return $this->forcedcap;
	}

}

class Mudnames {
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
		
		if (!isset($this->dictionnary[$file])) {
			$this->dictionnary[$file] = new mudnames_dico($file);
		}
		
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
	
	public function generates_several_names($number, $file='') {
		$number = (int) $number;
		
		$names = array();
		while($number-- > 0) {
			$names[] = self::generate_name_from($file);
		}
		return $names;
	}
	
	/**
	 * for debug purpose
	 */
	public function get_info($tag) {
		$tag = strtr($tag,' ','_');
		switch ($tag) {
			case 'file_used':
				$tag = 'dictionnaries';
				return $this->current_file;
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
