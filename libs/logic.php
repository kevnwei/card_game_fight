<?php

include_once "hero.php";

class Logic {
    private $_result = array('win' => '', 'rounds' => array());
    private $_heroes = array();
    private $_orders = array();
    private $_warmap = array();

	public function __construct($players, $enemies, $p_buf, $e_buf) {
        $this->createWarMap();
        $this->createHeroes($players, DOWN, $p_buf);
        $this->createHeroes($enemies, UPON, $e_buf);
        $this->createOrder();
        $this->createEnemies();
        $this->createFriends(DOWN);
        $this->createFriends(UPON);
	}

    /**
     * 创建地图
     */
    private function createWarMap() {
        $this->_warmap = array(
            UPON.'4' => 51, UPON.'5' => 52, UPON.'6' => 53,
            UPON.'1' => 41, UPON.'2' => 42, UPON.'3' => 43,
            '--xx--' => 31, '--yy--' => 32, '--zz--' => 33,
            DOWN.'1' => 21, DOWN.'2' => 22, DOWN.'3' => 23,
            DOWN.'4' => 11, DOWN.'5' => 12, DOWN.'6' => 13,
        );
    }

    /**
     * 创建英雄
     */
    private function createHeroes($data, $side, $common_buf) {
        foreach ($data as $pos => $info) {
            if (!isset($this->_warmap[$side.$pos])) continue;
            $map_pos = $this->_warmap[$side.$pos];
            $this->_heroes[$side][$map_pos] = new Hero($info, $side, $map_pos, $common_buf);
        }
    }

    /**
     * 排列英雄
     */
    private function createOrder() {
        $war_map_order = array(
            21, 41, 22, 42, 23, 43, 11, 51, 12, 52, 13, 53
        );

        foreach ($war_map_order as $o) {
            if (isset($this->_heroes[DOWN][$o])) $this->_orders[] = $this->_heroes[DOWN][$o];
            if (isset($this->_heroes[UPON][$o])) $this->_orders[] = $this->_heroes[UPON][$o];
        }
    }

    /**
     * 圈定敌人
     */
    private function createEnemies() {
        $enemy_map = array(
            //上方英雄 -〉下方敌人位置
            51 => array(21, 22, 23, 11, 12, 13),
            52 => array(22, 21, 23, 12, 11, 13),
            53 => array(23, 22, 21, 13, 12, 11),
            41 => array(21, 22, 23, 11, 12, 13),
            42 => array(22, 21, 23, 12, 11, 13),
            43 => array(23, 22, 21, 13, 12, 11),
            //下方英雄 -〉上方敌人位置
            21 => array(41, 42, 43, 51, 52, 53),
            22 => array(42, 41, 43, 52, 51, 53),
            23 => array(43, 42, 41, 53, 52, 51),
            11 => array(41, 42, 43, 51, 52, 53),
            12 => array(42, 41, 43, 52, 51, 53),
            13 => array(43, 42, 41, 53, 52, 51),
        );

        foreach ($this->_heroes as $side => $heroes) {
            $other_side = $side == UPON ? DOWN : UPON;
            foreach ($heroes as $map_pos => $hero) {
                foreach ($enemy_map[$map_pos] as $e_pos) {
                    if (isset($this->_heroes[$other_side][$e_pos])) {
                        $hero->addEnemy($this->_heroes[$other_side][$e_pos]);
                    }
                }
            }
        }
    }

    /**
     * 圈定友军
     */
    private function createFriends($side) {
        foreach ($this->_heroes[$side] as $hero) {
            foreach ($this->_heroes[$side] as $friend) {
                //if ($hero->map_pos != $friend->map_pos) {
                    $hero->addFriend($friend);
                //}
            }
        }
    }

    /**
     * 开始战斗
     */
    public function fight() {
        $rounds = 0;
        while (true) {
            if (++$rounds > 100) {echo '100+';break;} //TODO

            if ($this->isLose(DOWN)) {
                $this->_result['win'] = 0;
            }
            if ($this->isLose(UPON)) {
                $this->_result['win'] = $this->_result['win'] === 0 ? 2 : 1;
            }
            if ($this->_result['win'] !== '') {
                break;
            }

            $this->_result['rounds'][] = $this->round();
            if (DEBUG) $this->debug($rounds);//TODO
            $this->afterRound();
        }

        return $this->_result;
	}

    /**
     * 地图调试
     */
    private function debug($rounds) {
        $i = 1;
        echo "\n-------------<回合{$rounds}结束地图>-------------\n";
        foreach ($this->_warmap as $w) {
            if ($h = $this->hasHeroInPos($w)) {
                $skid = str_pad(@$h->sk_now['id'], 3, '0', STR_PAD_LEFT);
                echo $h->id, "(技能{$skid})", ' ';
            }
            else {
                echo '------------- ';
            }
            if($i++%3 == 0) echo "\n";
        }
        echo "-------------<回合{$rounds}结束地图>-------------\n";
        echo "\n";
    }

    /**
     * 失败?
     */
    private function isLose($side) {
        foreach($this->_heroes[$side] as $hero){
			if($hero->isAlive()){
				return false;
			}
		}

		return true;
    }

    /**
     * 回合数据
     */
    public function round() {
        $round = array();
        foreach ($this->_orders as $hero) {
            if (!$hero->isAlive() || !($targets = $hero->getDefaultTargets())) {
                continue;
            }
            if ($hero->enemies) {
                $e_all_dead = true;
                foreach ($hero->enemies as $_e) {
                    if ($_e->isAlive()) {
                        $e_all_dead = false;
                        break;
                    }
                }
                if ($e_all_dead) {
                    break;
                }
            }
            $round[] = $this->action($hero, $targets);
        }

        return $round;

    }

    /**
     * 行动数据
     */
    public function action($hero, $targets) {
        return array(
            $hero->side,
            $hero->id,
            $this->move($hero),
            $hero->sk_now['id'],
            $this->attack($hero, $targets)
        );
    }

    /**
     * 英雄移动
     */
    public function move($hero) {
        if ($hero->sk_now['at'] == 2 || $hero->sk_now['isco'] != 1) {
            return '';
        }

        //没有默认敌人
        if (!$hero->isMoveAble() || !$hero->default_enemy) {
            return '';
        }

        //如果在默认目标隔壁，原地转向
        $neighbors = $this->getNeighbor($hero->default_enemy->map_pos);
        if (in_array($hero->map_pos, $neighbors)) {
            $hero->changeFace($hero->map_pos, $hero->default_enemy->map_pos);
            return '';
        }

        $empty_pos = array();
        foreach ($neighbors as $nb) {
            if (!$this->hasHeroInPos($nb)) {
                $empty_pos[] = $nb;
            }
        }

        if (!$empty_pos) return '';
        if (count($empty_pos) == 1) {
            $hero->moveTo($this->_heroes, $empty_pos[0], $hero->default_enemy->map_pos);
            return $empty_pos[0];
        }

        //多于1个，选择距离自己最近的点
        foreach ($empty_pos as $pos) {
            if (!isset($new_pos)) {
                $distance = abs($pos - $hero->map_pos);
                $new_pos = $pos;
                continue;
            }
            $new_distance = abs($pos - $hero->map_pos);
            if ($distance > $new_distance) {
                $distance = $new_distance;
                $new_pos = $pos;
            }
        }

        $hero->moveTo($this->_heroes, $new_pos, $hero->default_enemy->map_pos);

        return $new_pos;
    }

    /**
     * 获取邻位
     */
    public function getNeighbor($id) {
        $neighbors = array(
            51 => array(52, 41),     52 => array(51, 42, 53),     53 => array(52, 43),
            41 => array(31, 42, 51), 42 => array(32, 41, 43, 52), 43 => array(33, 42, 53),
            31 => array(21, 32, 41), 32 => array(22, 31, 33, 42), 33 => array(23, 32, 43),
            21 => array(11, 22, 31), 22 => array(12, 21, 23, 32), 23 => array(13, 22, 33),
            11 => array(12, 21),     12 => array(11, 13, 22),     13 => array(12, 23)
        );

        return isset($neighbors[$id]) ? $neighbors[$id] : array();
    }

    /**
     * 是否有人
     */
    public function hasHeroInPos($map_pos = '') {
        foreach ($this->_heroes as $heroes) {
            if (isset($heroes[$map_pos])) {
                return $heroes[$map_pos];
            }
        }

        return false;
    }

    /**
     * 攻击动作
     */
    public function attack($hero, $targets) {
        $types = array(
            1 => 'hurt',
            2 => 'heal',
        );

        if (!($at = $hero->getAttackType()) || !isset($types[$at])) {
            return array();
        }
        
        return $this->{$types[$at]}($hero, $targets);
    }

    /**
     * 治疗
     */
    public function heal($hero, $targets) {
        $ret = array();
        $hp = $hero->getHealth();
        if (DEBUG) echo '加血=======: ',"\n", $hero->id, ' 释放治疗', "\n";
        foreach ($targets as $target) {
            $target->addHp($hp);
            if (DEBUG) echo '加血对象: id=',$target->id," +hp=",$hp," hp=",$target->getHp(),"\n";
            $ret[] = $target->side.','.$target->id.','.-$hp.',0,' . $target->getHp();
        }

        return $ret;
    }

    /**
     * 伤害
     */
    public function hurt($hero, $targets) {
        $ret = array();
        $targets = $hero->getEnemyTargets();// get other targets if have
        if (DEBUG) echo '攻击=======: ',"\n";
        foreach ($targets as $target) {
            $is_hit = $this->isHit($hero, $target);
            if ($is_hit) {
                $hurt = $this->getHurt($hero, $target);
                if (DEBUG) echo '受伤对象: id=',$target->id,' hurt=', $hurt['hurt'], ' crit=', $hurt['crit'],"\n";
                $target->subHp($hurt['hurt'], $this->_heroes[$target->side]);
                $ret[] = $target->side.','.$target->id.','.$hurt['hurt'].','.$hurt['crit'].','.$target->getHp();
            } else {
                if (DEBUG) echo '未中对象: id=',$target->id,"\n";
                $ret[] = $target->side.','.$target->id.',0'.',0'.','.$target->getHp();
            }
        }
        return $ret;
    }

    /**
     * 单体伤害
     */
    public function getHurt($hero, $enemy) {
        $ret = array('hurt' => 0, 'crit' => 0);
        $attack = $hero->getAttack();
        if (DEBUG) echo $hero->id, '的攻击力:',$attack,"\n";
        $defend = $enemy->getDefend($hero->getAttackProp());
        // 忽视防御
        $defend = (1 - $hero->getSubDefendRate()) * $defend;

        if ($attack / $defend < 1.05) {
            $hurt = 0.05 * $defend;
        } else {
            $hurt = $attack - $defend;
        }

        // 技能倍率
        if (isset($hero->sk_now['rt'])) {
            $hurt = $hurt * floatval($hero->sk_now['rt']);
        }

        // 暴击伤害
        if ($this->isCrit($hero, $enemy)) {
            $hurt = $hurt * $hero->getCritHurtRate();
            $ret['crit'] = 1;
        }

        // 最终伤害倍率
        $hurt *= $hero->getHurtRate();

        $ret['hurt'] = $hurt;

        return $ret;
    }

    /**
     * 是否命中
     */
    public function isHit($hero, $enemy) {
        $rate = $hero->getHitRate() - $enemy->getMisRate();
        if ($rate < 0.2) {
            $rate = 0.2;
        }
        return mt_rand(1, 10000) <= $rate * 10000;
    }

    /**
     * 是否暴击
     */
    public function isCrit($hero, $enemy) {
        $rate = $hero->getFcrRate() - $enemy->getAcrRate();
        return mt_rand(1, 10000) <= $rate * 10000;
    }

    /**
     * 回合清理
     */
    public function afterRound() {
        foreach ($this->_heroes as $heros) {
            foreach ($heros as $hero) {
                $hero->afterRound();
            }
        }
    }
}