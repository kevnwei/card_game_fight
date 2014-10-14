<?php

/**
 * 英雄类
 * 职业：1步兵(可动)，2骑兵(可动)，3弓兵，4医师，5谋士，6策士，7都尉
 * 
 *
 *
 */
include_once "skill.cfg.php";

class Hero {
    public $id         = '';// 英雄ID
    public $side       = '';// 阵地：己方下方，对方上方
    public $other_side = '';// 对方阵地
    public $face       = '';// 朝向
    public $job        = '';// 职业
    public $hp         = 0; // 生命
    public $phy_attack = 0; // 物理攻击
    public $phy_defend = 0; // 物理防御
    public $mag_attack = 0; // 法术攻击
    public $mag_health = 0; // 法术治疗
    public $mag_defend = 0; // 法术防御
    public $fcr_rate   = 0; // 暴击比率
    public $acr_rate   = 0; // 反暴击率（韧性）
    public $hit_rate   = 0; // 命中比率
    public $mis_rate   = 0; // 闪避比率

    public $hurt_rate  = 0; // 最终伤害倍率x%, 即 final_hurt * (1 + x%)
    public $fcr_hurt_rate = 0; // 暴击伤害提高x%, 即 1.5 + x%
    public $sub_defend_rate = 0; // 忽视对方防御x%(对方防御*(1-x%))


    public $map_pos    = '';
    public $friends    = array();//友军列表
    public $enemies    = array();//敌人列表
    public $skills     = array();//技能列表
    public $sk_now     = array();//当前技能
    public $buffs      = array();//buff状态
    public $default_enemy = null;//默认敌人（第一目标）

    public $weapon     = array();//武器
    public $armor      = array();//防具

    public function __construct($info = array(), $side, $map_pos, $common_buff = array()) {
        global $config;
        $_id = $info['id'];
        $_lv = $info['lv'];

        if (isset($info['weapon'])) $this->weapon = $info['weapon'];
        if (isset($info['armor'])) $this->armor = $info['armor'];

        $this->id         = $_id;
        $this->side       = $side;
        $this->other_side = $side == DOWN ? UPON    : DOWN;
        $this->face       = $side == DOWN ? FACE_UP : FACE_DOWN;
        $this->map_pos    = $map_pos;

        $hero_conf        = $config['hero'][$_id];
        $this->job        = $hero_conf['p'];
        $this->hp         = $hero_conf['bh'] + $hero_conf['ih'] * ($_lv - 1);
        $this->phy_attack = $hero_conf['ba'] + $hero_conf['ia'] * ($_lv - 1);
        $this->phy_defend = $hero_conf['bf'] + $hero_conf['if'] * ($_lv - 1);
        $this->mag_attack = $hero_conf['bp'] + $hero_conf['ip'] * ($_lv - 1);
        $this->mag_health = $hero_conf['be'] + $hero_conf['ie'] * ($_lv - 1);
        $this->mag_defend = $hero_conf['bs'] + $hero_conf['is'] * ($_lv - 1);
        $this->fcr_rate   = $hero_conf['bc'];
        $this->acr_rate   = 0;
        $this->hit_rate   = $hero_conf['bi'];
        $this->mis_rate   = $hero_conf['bm'];

        $this->initEquipments($this->weapon);
        $this->initEquipments($this->armor);
        $this->initSkills(array($hero_conf['ns'], $hero_conf['as'], $hero_conf['ps']));
        $this->addBuffs($common_buff);
    }

    /**
     * 公共buff，装备Buff
     */
    public function addBuffs($buffs) {
        $types = array(
            '1' => 'hp',
            '2' => 'phy_attack',
            '3' => 'mag_attack',
            '4' => 'mag_health',
            '5' => 'phy_defend',
            '6' => 'mag_defend',
            '7' => 'fcr_rate',
            '8' => 'mis_rate',
            '9' => 'hit_rate',
            '10'=> 'hurt_rate',
            '11'=> 'fcr_hurt_rate',
            '12'=> 'sub_defend_rate',
        );
        foreach ($buffs as $id => $val) {
            if (!isset($types[$id])) continue;
            if ($id < 7) {// 具体值
                $this->{$types[$id]} += $val;
            } else if($id < 10) {// 百分比值
                $this->{$types[$id]} += $this->{$types[$id]} * $val/100;
            } else {
                $this->{$types[$id]} += $val / 100;
            }
        }
    }

    /**
     * 装备属性
     */
    public function initEquipments($equipments) {
        $buffs = array();
        if ($equipments) {
            foreach ($equipments as $e_id => $buff) {
                if (!is_array($buff)) continue;
                foreach ($buff as $id => $val) {
                    if (!isset($buff[$id])) $buff[$id] = $val;
                    else $buff[$id] += $val;
                }
            }
        }

        $this->addBuffs($buffs);
    }

    /**
     * 装备技能
     */
    public function initSkills($skills) {
        global $config;

        if (is_array($skills) && $skills) {
            foreach ($skills as $sk_id) {

                $sk_conf = $sk_id < 1000 ? $config['skill'][$sk_id] : $config['passive_skill'][$sk_id];
                //被动技能（只修改英雄属性）
                if (isset($sk_conf['ty']) && $sk_conf['ty'] == 3) {
                    for ($i = 1; $i <= 3; $i++) {
                        $eff_id = isset($sk_conf['ep'.$i]) ? $sk_conf['ep'.$i] : 0;
                        $eff_val= isset($sk_conf['ev'.$i]) ? $sk_conf['ev'.$i] : 0;
                        if (!$eff_id || !$eff_val) continue;
                        $this->passiveSkill($eff_id, $eff_val);
                    }

                    continue;
                }

                //主动技能（普通技能，特殊技能）
                $this->skills[$sk_id] = array(
                    'id' => $sk_id,
                    'cd' => $sk_conf['sf'] ? --$sk_conf['sf'] : 0, //当前剩余CD
                    'ty' => $sk_conf['ty'], //类型：1普通，2特殊，3被动
                    'lv' => $sk_conf['lv'], //等级
                    'at' => $sk_conf['at'], //攻击类型：1攻击，2增益
                    'ap' => $sk_conf['ap'], //攻击属性：1物理，2法术
                    'tt' => $sk_conf['tt'], //目标选择类型：0无特殊目标，根据攻击规定，1横向，2纵向，3前->后，4后->前，5随机，6全体
                    'tn' => $sk_conf['tn'], //目标数量：1-6
                    'sf' => $sk_conf['sf'], //技能开始释放回合
                    'sc' => $sk_conf['sc'] ? ++$sk_conf['sc'] : 0, //技能CD（回合数）
                    'rt' => $sk_conf['rt'],  //伤害倍率
                    'isco' => $sk_conf['isco']  //是否近战技能（需要移动为1，原地为0）
                );
            }
        }
    }

    /**
     * 被动技能（作用）
     * 1.生命,2.物理攻击,3.法术攻击,4.治疗,5.物理防御,6.法术防御,7.暴击,8.韧性,9.命中,10.闪避
     */
    public function passiveSkill($id, $val) {
        $types = array(
            '1' => 'hp',
            '2' => 'phy_attack',
            '3' => 'mag_attack',
            '4' => 'mag_health',
            '5' => 'phy_defend',
            '6' => 'mag_defend',
            '7' => 'fcr_rate',
            '8' => 'acr_rate',
            '9' => 'hit_rate',
            '10'=> 'mis_rate',
        );
        if (!isset($types[$id])) return;
        $this->{$types[$id]} += $this->{$types[$id]} * floatval($val);
    }

    /**
     * 是否存活
     */
    public function isAlive() {
        return $this->hp > 0;
    }

    /**
     * 能否移动
     */
    public function isMoveAble() {
        return ($this->job == 1 || $this->job == 2) && $this->hp > 0;
    }

    /**
     * 改变朝向 : 近战转向+移动
     */
    public function changeFace($new_pos, $enemy_pos) {
        if ($this->sk_now['isco'] != 1) return false; //非近战不转向

        $distance = $new_pos - $enemy_pos;
        $face_map = array(
            '-10' => FACE_UP, '10' => FACE_DOWN, '1' => FACE_LEFT, '-1' => FACE_RIGHT
        );

        if (!isset($face_map[$distance])) return false;

        $this->face = $face_map[$distance];
    }

    /**
     * 添加敌人
     */
    public function addEnemy($e) {
        $this->enemies[] = $e;
    }

    /**
     * 添加友军
     */
    public function addFriend($f) {
        $this->friends[] = $f;
    }

    public function getDefaultTargets() {
        $target = '';
        if (!($this->fireSkill())) {
            return '';
        }

        // 攻击类型
        switch ($this->sk_now['at']) {
            case 1:
                $target = $this->getDefaultEnemy();
                break;
            case 2:
                $target = $this->getFriends();
                break;
            default:
                break;
        }

        return $target;
    }

    /**
     * 获取目标
     */
    public function getEnemyTargets() {
        $targets = array();

        // 攻击类型
        switch ($this->sk_now['at']) {
            case 1:
                $targets = $this->getEnemies();
                break;
            default:
                break;
        }
        
        return $targets;
    }

    /**
     * 释放技能
     */
    public function fireSkill() {
        $normal_skills = array();

        // 优先特殊技能（大招）
        foreach ($this->skills as &$skill) {
            if ($skill['ty'] == 2 && $skill['cd'] == 0) {
                $this->sk_now = $skill;
                $skill['cd'] = $skill['sc'];
                return $skill;
            } else if ($skill['ty'] == 1) {
                $normal_skills[] = $skill;
            }
        }

        // 使用普通技能
        if (!$this->sk_now && $normal_skills) {
            $selected = array_rand($normal_skills, 1);
            $this->sk_now = $normal_skills[$selected];
            return $this->sk_now;
        }

        return array();
    }

    /**
     * 是否有敌人在位置上
     */
    public function hasEnemyInPos($map_pos) {
        foreach ($this->enemies as $enemy) {
            if ($enemy->isAlive() && $enemy->map_pos == $map_pos) {
                return $enemy;
            }
        }

        return false;
    }

    /**
     * 是否有友军在位置上
     */
    public function hasFriendInPos($map_pos) {
        foreach ($this->friends as $friend) {
            if ($friend->isAlive() && $friend->map_pos == $map_pos) {
                return $friend;
            }
        }

        return false;
    }

    /**
     * 选择敌人
     */
    public function getEnemies() {
        $target_types = array(
            '0' => 'Normal',
            '1' => 'Horizontal',
            '2' => 'Vertical',
            '3' => 'FrontToBack',
            '4' => 'BackToFront',
            '5' => 'Random',
            '6' => 'All',

        );
        $skill = $this->sk_now;
        $tt = isset($target_types[$skill['tt']]) ? $target_types[$skill['tt']] : 'Normal';
        $tn = isset($skill['tn']) ? $skill['tn'] : 1;
        $enemies = $this->{'get'.$tt.'Enemies'}($tn);

        return $enemies;
    }

    /**
     * 首选敌人
     */
    public function getDefaultEnemy() {
        foreach ($this->enemies as $enemy) {
            if ($enemy->isAlive()) {
                $this->default_enemy = $enemy;
                $this->changeFace($this->map_pos, $enemy->map_pos);
                return $enemy;
            }
        }

        return array();
    }

    /**
     * 默认敌人
     */
    public function getNormalEnemies($tn) {
        $default_enemy = $this->getDefaultEnemy();
        return $default_enemy ? array($default_enemy) : array();
    }

    /**
     * 目标竖排，只选对面的目标（$tn个）
     */
    public function getVerticalEnemies($tn) {
        $default_enemy = $this->getDefaultEnemy();
        if (!$default_enemy) {
            return array();
        }

        $y0 = intval(substr($default_enemy->map_pos, 0, 1));
        $x0 = intval(substr($default_enemy->map_pos, 1, 1));
        $map_pos = array();
        switch ($this->face) {
            case FACE_UP:
            {
                for ($y = $y0; $y <= 5; $y++) {
                    if ($this->hasEnemyInPos($y.$x0)) {
                        $map_pos[] = $y.$x0;
                    }
                }
                break;
            }
            case FACE_DOWN:
            {
                for ($y = 1; $y <= $y0; $y++) {
                    if ($this->hasEnemyInPos($y.$x0)) {
                        $map_pos[] = $y.$x0;
                    }
                }
                break;
            }
            case FACE_LEFT:
            {
                for ($x = 1; $x <= $x0; $x++) {
                    if ($this->hasEnemyInPos($y0.$x)) {
                        $map_pos[] = $y0.$x;
                    }
                }
                break;
            }
            case FACE_RIGHT:
            {
                for ($x = $x0; $x <= 3; $x++) {
                    if ($this->hasEnemyInPos($y0.$x)) {
                        $map_pos[] = $y0.$x;
                    }
                }
                break;
            }
        }

        // 优先离自己最近的目标
        if (count($map_pos) > $tn) {
            $order = array();
            foreach ($map_pos as $pos) {
                $order[] = abs($pos - $this->map_pos);
            }
            array_multisort($order, $map_pos);
            $map_pos = array_slice($map_pos, 0, $tn);
        }

        $enemies = array();
        foreach ($map_pos as $pos) {
            if ($enemy = $this->hasEnemyInPos($pos)) {
                $enemies[] = $enemy;
            }
        }

        return $enemies;
    }

    /**
     * 目标横排（全部）
     */
    public function getHorizontalEnemies($tn) {
        $default_enemy = $this->getDefaultEnemy();
        if (!$default_enemy) {
            return array();
        }

        $y0 = intval(substr($default_enemy->map_pos, 0, 1));
        $x0 = intval(substr($default_enemy->map_pos, 1, 1));
        $map_pos = array();
        switch ($this->face) {
            case FACE_UP:
            case FACE_DOWN:
            {
                for ($x = 1; $x <= 3; $x++) {
                    if ($this->hasEnemyInPos($y0.$x)) {
                        $map_pos[] = $y0.$x;
                    }
                }
                break;
            }
            case FACE_LEFT:
            case FACE_RIGHT:
            {
                for ($y = 1; $y <= 5; $y++) {
                    if ($this->hasEnemyInPos($y.$x0)) {
                        $map_pos[] = $y.$x0;
                    }
                }
                break;
            }
        }

        $enemies = array();
        foreach ($map_pos as $pos) {
            if ($enemy = $this->hasEnemyInPos($pos)) {
                $enemies[] = $enemy;
            }
        }

        return $enemies;
    }

    /**
     * 前排目标（前->后 最外层）
     */
    public function getFrontToBackEnemies($tn) {
        $y0 = intval(substr($this->map_pos, 0, 1));
        $x0 = intval(substr($this->map_pos, 1, 1));
        $map_pos = array();
        switch ($this->face) {
            case FACE_UP: {
                for ($x = 1; $x <= 3; $x++) {
                    for ($y = $y0 + 1; $y <= 5; $y++) {
                        if ($this->hasEnemyInPos($y.$x)) {
                            $map_pos[] = $y.$x;
                            break;
                        }
                    }
                }
                break;
            }
            case FACE_DOWN: {
                for ($x = 1; $x <= 3; $x++) {
                    for ($y = $y0 - 1; $y >= 1; $y--) {
                        if ($this->hasEnemyInPos($y.$x)) {
                            $map_pos[] = $y.$x;
                            break;
                        }
                    }
                }
                break;
            }
            case FACE_LEFT: {
                for ($y = 1; $y <= 5; $y++) {
                    for ($x = $x0 - 1; $x >= 1; $x--) {
                        if ($this->hasEnemyInPos($y.$x)) {
                            $map_pos[] = $y.$x;
                            break;
                        }
                    }
                }
                break;
            }
            case FACE_RIGHT: {
                for ($y = 1; $y <= 5; $y++) {
                    for ($x = $x0 + 1; $x <= 3; $x++){
                        if ($this->hasEnemyInPos($y.$x)) {
                            $map_pos[] = $y.$x;
                            break;
                        }
                    }
                }
                break;
            }
        }

        $enemies = array();
        foreach ($map_pos as $pos) {
            if ($enemy = $this->hasEnemyInPos($pos)) {
                $enemies[] = $enemy;
            }
        }

        return $enemies;
    }

    /**
     * 后排目标（后->前 最外层）
     */
    public function getBackToFrontEnemies($tn) {
        $y0 = intval(substr($this->map_pos,0,1));
        $x0 = intval(substr($this->map_pos,1,1));
        $map_pos = array();
        switch ($this->face) {
            case FACE_UP: {
                for ($x = 1; $x <= 3; $x++) {
                    for ($y = 5; $y >= $y0 + 1; $y--) {
                        if ($this->hasEnemyInPos($y.$x)) {
                            $map_pos[] = $y.$x;
                            break;
                        }
                    }
                }
                break;
            }
            case FACE_DOWN: {
                for ($x = 1; $x <= 3; $x++) {
                    for ($y = 1; $y <= $y0 - 1; $y++) {
                        if ($this->hasEnemyInPos($y.$x)) {
                            $map_pos[] = $y.$x;
                            break;
                        }
                    }
                }
                break;
            }
            case FACE_LEFT: {
                for ($y = 1; $y <= 5; $y++) {
                    for ($x = 1; $x <= $x0 - 1; $x++) {
                        if ($this->hasEnemyInPos($y.$x)) {
                            $map_pos[] = $y.$x;
                            break;
                        }
                    }
                }
                break;
            }
            case FACE_RIGHT: {
                for ($y = 1; $y <= 5; $y++) {
                    for ($x = 3; $x >= $x0 + 1; $x--){
                        if ($this->hasEnemyInPos($y.$x)) {
                            $map_pos[] = $y.$x;
                            break;
                        }
                    }
                }
                break;
            }
        }
        
        $enemies = array();
        foreach ($map_pos as $pos) {
            if ($enemy = $this->hasEnemyInPos($pos)) {
                $enemies[] = $enemy;
            }
        }

        return $enemies;
    }

    /**
     * 随机敌人
     */
    public function getRandomEnemies($tn) {
        $alive_enemies = array();
        foreach ($this->enemies as $e) {
            if ($e->isAlive()) {
                $alive_enemies[] = $e;
            }
        }
        if (!$alive_enemies) {
            return array();
        }

        if ($tn > count($alive_enemies)) {
            $tn = count($alive_enemies);
        }

        if ($tn == 1) {
            return $alive_enemies;
        }

        $selected_keys = array_rand($alive_enemies, $tn);

        $enemies = array();
        foreach ($selected_keys as $_k) {
            $enemies[] = $alive_enemies[$_k];
        }
        return $enemies;
    }

    /**
     * 全部敌人
     */
    public function getAllEnemies($tn = 0) {
        $all_live = array();
        foreach ($this->enemies as $enemy) {
            if ($enemy->isAlive()) {
                $all_live[] = $enemy;
            }
        }

        return $all_live;
    }

    /**
     * 获取友军
     */
    public function getFriends() {
        $target_types = array(
            '0' => 'Normal',
            '6' => 'All',
        );
        $skill = $this->sk_now;
        $tt = isset($target_types[$skill['tt']]) ? $target_types[$skill['tt']] : 'Normal';
        $tn = isset($skill['tn']) ? $skill['tn'] : 1;
        $friends = $this->{'get'.$tt.'Friends'}($tn);

        return $friends;
    }

    /**
     * 有序友军(加血优先血少的)
     */
    public function getNormalFriends($tn) {
        $friends = $this->getAllFriends($tn);
        if (!$friends) {
            return array();
        }

        if ($tn >= count($friends)) {
            return $friends;
        }

        $order = array();
        foreach ($friends as $friend) {
            $order[] = $friend->hp;
        }
        array_multisort($order, $friends);
        $friends = array_slice($friends, 0, $tn);

        return $friends;
    }

    /**
     * 随机友军(未用)
     */
    public function getRandomFriends($tn) {
        $friends = array();
        foreach ($this->friends as $friend) {
            if ($friend->isAlive()) {
                $friends[] = $friend;
            }
        }

        if ($tn > count($friend)) $tn = count($friend);
        $keys = array_rand($friends, $tn);
        $random = array();
        foreach ($keys as $key) {
            $random[] = $friends[$key];
        }

        return $random;
    }

    /**
     * 全部友军（活的）
     */
    public function getAllFriends($tn) {
        $friends = array();
        foreach ($this->friends as $friend) {
            if ($friend->isAlive()) {
                $friends[] = $friend;
            }
        }

        return $friends;
    }

    /**
     * 受伤
     */
    public function subHp($hp, &$camp) {
        $this->hp -= $hp;
        $this->hp = round($this->hp, 2);//TODO
        if ($this->hp <= 0) {
            $this->onDead($camp);
        }
        return $this;
    }

    /**
     * 治疗
     */
    public function addHp($hp) {
        $this->hp += $hp;
        $this->hp = round($this->hp, 2);//TODO
    }

    /**
     * 阵亡
     */
    public function onDead(&$camp) {
        unset($camp[$this->map_pos]);
    }

    /**
     * 移动 : 近战才转向+移动
     */
    public function moveTo(&$heroes, $new_pos, $enemy_pos) {
        if ($this->sk_now['isco'] != 1) return false;

        if ($heroes[$this->side][$this->map_pos]->map_pos != $this->map_pos
            || isset($heroes[$this->side][$new_pos])
            || isset($heroes[$this->other_side][$new_pos])) {
            return false;
        }
        if (DEBUG) echo $this->map_pos,"(ID:{$this->id})"  ,' 移动到 ', $new_pos,' 敌人在 ', $enemy_pos, "\n";
        $this->changeFace($new_pos, $enemy_pos);
        $heroes[$this->side][$new_pos] = $heroes[$this->side][$this->map_pos];
        unset($heroes[$this->side][$this->map_pos]);
        $this->map_pos = $new_pos;
        return true;
    }

    /**
     * 生命
     */
    public function getHp() {
        return $this->hp + $this->getBuff('hp');
    }

    /**
     * 命中
     */
    public function getHitRate() {
        return $this->hit_rate;
    }

    /**
     * 回避
     */
    public function getMisRate() {
        return $this->mis_rate;
    }

    /**
     * 暴击
     */
    public function getFcrRate() {
        return $this->fcr_rate + $this->getBuff('fcr_rate');
    }

    /**
     * 韧性
     */
    public function getAcrRate() {
        return $this->acr_rate + $this->getBuff('acr_rate');
    }

    /**
     * 物防
     */
    public function getPhyDefend() {
        return $this->phy_defend + $this->getBuff('phy_defend');
    }

    /**
     * 法防
     */
    public function getMagDefend() {
        return $this->mag_defend + $this->getBuff('mag_defend');
    }

    /**
     * 物攻
     */
    public function getPhyAttack() {
        return $this->phy_attack + $this->getBuff('phy_attack');
    }

    /**
     * 法攻
     */
    public function getMagAttack() {
        return $this->mag_attack + $this->getBuff('mag_attack');
    }

    /**
     * 伤害倍率
     */
    public function getHurtRate() {
        return 1 + $this->hurt_rate;
    }

    /**
     * 暴击倍率
     */
    public function getCritHurtRate() {
        return 1.5 + $this->fcr_hurt_rate;
    }

    /**
     * 减防倍率
     */
    public function getSubDefendRate() {
        return $this->sub_defend_rate;
    }

    /**
     * 产生buff
     */
    public function addBuff($id, $val, $rounds) {
        $this->buffs[$id]['value'] = $val;
        $this->buffs[$id]['rounds'] = $rounds;
    }

    /**
     * 读取buff
     */
    public function getBuff($id) {
        if (isset($this->buffs[$id]) && $this->buffs[$id]['rounds'] > 0) {
            return $this->buffs[$id]['value'];
        }

        return 0;
    }

    /**
     * 攻击属性
     */
    public function getAttackProp() {
        return isset($this->sk_now['ap']) ? $this->sk_now['ap'] : '';
    }

    /**
     * 攻击类型
     */
    public function getAttackType() {
        return isset($this->sk_now['at']) ? $this->sk_now['at'] : '';
    }

    /**
     * 攻击
     */
    public function getAttack() {
        $attack_prop = $this->getAttackProp();
        switch ($attack_prop) {
            case '1':
                $attack = $this->getPhyAttack();
                break;
            case '2':
                $attack = $this->getMagAttack();
                break;
            default:
                $attack = 0;
        }

        return $attack;
    }

    /**
     * 防御
     */
    public function getDefend($attack_prop) {
        switch ($attack_prop) {
            case '1':
                $defend = $this->getPhyDefend();
                break;
            case '2':
                $defend = $this->getMagDefend();
                break;
            default:
                $defend = 0;
        }

        return $defend;
    }

    /**
     * 治疗
     */
    public function getHealth() {
        return $this->mag_health;
    }

    /**
     * 回合清理
     */
    public function afterRound() {
        foreach ($this->skills as &$skill) {
            if ($skill['cd'] > 0) {
                $skill['cd']--;
            }
        }
        $this->sk_now = array();
        $this->default_enemy = null;
        foreach ($this->buffs as &$buff) {
            if ($buff['rounds'] > 0) {
                $buff['rounds']--;
            }
        }
    }
}