<?php
define('DEBUG', 1);
define('UPON', '1');
define('DOWN', '0');
define('FACE_UP', 1);
define('FACE_DOWN', 2);
define('FACE_LEFT',3);
define('FACE_RIGHT', 4);

include_once "hero.cfg.php";
include_once "logic.php";

class Fight {

	private static $__instance = null;
    private $result  = array('win' => '','rounds' => '','roles' => '','msg' => '');//1-win,0-lose,2-draw

    public function __construct() {

    }

    public static function getInstance() {
    	if (!self::$__instance instanceof self) {
    		self::$__instance = new self;
    	}
    	return self::$__instance;
    }

    /**
     * Start Here...
     * @param array $players
     * @param array $enemies
     * @return array
     */
    public function start($players = array(), $enemies = array(), $p_buf, $e_buf) {
        $buf1 = $buf2 = array();
        foreach($p_buf as $buf) {
            foreach ($buf as $id => $val) {
                if (!isset($buf1[$id]))$buf1[$id] = $val;
                else $buf1[$id] += $val;
            }
        }
        foreach($e_buf as $buf) {
            foreach ($buf as $id => $val) {
                if (!isset($buf2[$id]))$buf2[$id] = $val;
                else $buf2[$id] += $val;
            }
        }
        
        return $this->buildRoles($players, $enemies)
                     ->buildRounds($players, $enemies, $buf1, $buf2)
                     ->result;
    }

    public function buildRoles($players, $enemies) {
        global $config;
        foreach ($players as $p) {
            $this->result['roles']['0'][] = array(
                $p['id'], $config['hero'][$p['id']]['n']
            );
        }
        foreach ($enemies as $e) {
            $this->result['roles']['1'][] = array(
                $e['id'], $config['hero'][$e['id']]['n']
            );
        }

        return $this;
    }

    public function buildRounds($players, $enemies, $p_buf, $e_buf) {
        $flg = new Logic($players, $enemies, $p_buf, $e_buf);
        $ret = $flg->fight();
        $this->result['win'] = $ret['win'];
        $this->result['rounds'] = $ret['rounds'];

        return $this;
    }
}
