<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Db
 *
 * @author семья
 */
class Model {
    private $con;
    
    public function __construct($config){
        $this->con = new DB($config['DBHost'], $config['DBPort'], $config['DBName'], $config['DBUser'], $config['DBPassword']);  
    }
    
    public function log($type, $description)
    {
        $params = array(
            "type" => $type,
            "description" => $description
        );
        return $this->con->query("INSERT INTO log (type, description) VALUES (:type, :description)", $params);
    }    
    
    public function registration($username, $firstname, $lastname, $id, $referal)
    {
        $res = 0;
        $params = array(
            "login" => $username,
            "firstname" => $firstname,
            "lastname" => $lastname,
            "tid" => $id, 
            "referal" => $referal
        );
        if(!$this->is_exists($username, $firstname, $lastname, $id))
        {
            $res = $this->con->query("INSERT IGNORE INTO users (login, firstname, lastname, tid, referal) VALUES (:login, :firstname, :lastname, :tid, :referal)", $params);
            $this->log('registration','Пользователь - '.implode(': ',$params).' авторизовался в боте.');
        }
        else
        {
           $res = 1; 
        }
        return $res;
    }
    
    public function getUser($tid)
    {
        $params = array(
            "tid" => $tid
        );
        $res = $this->con->query("select * from users where tid = :tid", $params);
        return isset($res) ? $res : null;
    }
    
    public function is_exists($username, $firstname, $lastname, $id)
    {
        $res = 0;
        $params = array(
            "login" => $username,
            "firstname" => $firstname,
            "lastname" => $lastname,
            "tid" => $id
        );
        $res = $this->con->query("select 1 from users where login = :login and firstname = :firstname and lastname = :lastname and tid = :tid", $params);
        return isset($res[0]) && isset($res[0][1]) ? $res[0][1] : 0;
    } 
    
    public function getLeague()
    {
        $res = $this->con->query("select * from league order by orderby");
        return isset($res) ? $res : null;
    }
    
    public function getGroupLeague()
    {
        $res = $this->con->query("select * from group_league");
        return isset($res) ? $res : null;
    }
    
    public function getLeagueForGroup($strcode_group)
    {
        $params = array(
            "strcode" => $strcode_group
        );
        $res = $this->con->query("select l.* from league l join group_league gl on gl.id = l.group_id where gl.strcode = :strcode order by l.orderby", $params);
        return isset($res) ? $res : null;
    }
      
    public function getUserBalance($uid)
    {
        $params = array(
            "uid" => $uid
        );
        $res = $this->con->query("select u.*, l.* from users u join league l on l.id = u.lid where u.tid = :uid", $params);
        return isset($res) ? $res : null;
    }
    
    public function getBalanceLeague($uid)
    {
        $params = array(
            "uid" => $uid
        );
        $res = $this->con->query("select 1 from users u join league l on l.id = u.lid where u.balance >= l.cost and u.tid = :uid", $params);
        return isset($res[0]) && isset($res[0][1]) ? $res[0][1] : 0;
    }
    
    public function getStatistic($uid)
    {
        $params = array(
            "uid" => $uid
        );
        $res = $this->con->query("select * from statistic where uid = :uid", $params);
        return isset($res) ? $res : null;
    }  
    
    public function getBalance($tid)
    {
        $params = array(
            "tid" => $tid
        );
        $res = $this->con->query("select * from users where tid = :tid", $params);
        return isset($res) ? $res : null;
    } 
    
    public function getQuestions($uid)
    {
        $params = array(
            "uid" => $uid
        );
        $res = $this->con->query("select q.* from questions q
                                    left join questions_result qr on qr.qid = q.id and qr.uid = :uid
                                    where qr.id is null
                                    ORDER BY rand() LIMIT 1", $params);
        return isset($res) ? $res : null;
    } 
    
    public function getAnswers($qid)
    {
        $params = array(
            "qid" => $qid
        );
        $res = $this->con->query("select * from answers where qid = :qid order by orderby", $params);
        return isset($res) ? $res : null;
    }
    
    public function getAnswerCorrect($command)
    {
        $params = array(
            "command" => $command
        );
        $res = $this->con->query("select correct from answers where command = :command", $params);
        return isset($res[0]) && isset($res[0]['correct']) ? $res[0]['correct'] : 0;
    }
    
    public function setAnswers($command, $uid, $gid)
    {       
        $res = 0;
        $params = array(
            "command" => $command,
            "uid" => $uid,
            "gid" => $gid
        );
        if(!isset($command) || $this->is_exists_answers($command))
        {
            $res = $this->con->query("INSERT IGNORE INTO answers_result (command, uid, gid) VALUES (:command, :uid, :gid)", $params);
            $this->log('answers','Пользователь ответил на вопрос: '.implode(': ',$params).'.');
        }
        else
        {
           $res = 1; 
        }
        return $res;
    }
    
    public function getAnswersResult($uid)
    {
        $params = array(
            "uid" => $uid
        );
        $res = $this->con->query("SELECT count(1) cnt FROM (SELECT 1 FROM users u  
                                    join users_game ug on ug.uid = u.tid
                                    join games g on g.uid = u.tid and g.id = ug.gid and g.status = 1 
                                    join questions_result qr on qr.uid = u.tid and qr.gid = g.id and qr.status = 1 
                                    join answers a on a.qid = qr.qid and a.correct = 1 
                                    join answers_result ar on ar.command = a.command and ar.uid = u.tid and ar.gid = g.id
                                    where u.tid = :uid
                                    group by qr.id) d", $params);
        return isset($res) ? $res : null;
    }
    
    public function setQuestions($qid, $uid, $gid)
    {       
        $res = 0;
        $params = array(
            "qid" => $qid,
            "uid" => $uid,
            "gid" => $gid
        );
        if($this->is_exists_questions($qid))
        {
            $res = $this->con->query("INSERT INTO questions_result (qid, uid, gid) VALUES (:qid, :uid, :gid)", $params);
            $this->log('questions','Пользователь начал отвечать на вопрос: '.implode(': ',$params).'.');
        }
        return $res;
    }
    
    public function is_exists_answers($command)
    {
        $res = 0;
        $params = array(
            "command" => $command
        );
        $res = $this->con->query("select 1 from answers where command = :command", $params);
        return isset($res[0]) && isset($res[0][1]) ? $res[0][1] : 0;
    } 
    
    public function is_exists_questions($id)
    {
        $res = 0;
        $params = array(
            "id" => $id
        );
        $res = $this->con->query("select 1 from questions where id = :id", $params);
        return isset($res[0]) && isset($res[0][1]) ? $res[0][1] : 0;
    }

    public function is_exists_questions_result($id)
    {
        $res = 0;
        $params = array(
            "id" => $id
        );
        $res = $this->con->query("select 1 from questions where id = :id", $params);
        return isset($res[0]) && isset($res[0][1]) ? $res[0][1] : 0;
    }
    
    public function is_no_user_game_result($uid)
    {
        $res = 0;
        $params = array(
            "uid" => $uid
        );
        $res = $this->con->query("select 1 from users_game where uid = :uid and count_questions > count_questions_result", $params);
        return isset($res[0]) && isset($res[0][1]) ? $res[0][1] : 0;
    }

    public function getQuestionsTime($uid)
    {
        $params = array(
            "uid" => $uid
        );
        $res = $this->con->query("select * from questions_result where uid = :uid and status = 0 and date_add(`date_create`, interval 15 second) >= now()", $params);
        return isset($res) ? $res : null;
    }

    public function getQuestionsNoTime($uid)
    {
        $params = array(
            "uid" => $uid
        );
        $res = $this->con->query("select * from questions_result where uid = :uid and status = 0 limit 1", $params);
        return isset($res) ? $res : null;
    }

    public function setQuestionsStatus($id, $status)
    {       
        $res = 0;
        $params = array(
            "id" => $id,
            "status" => $status
        );
        $res = $this->con->query("UPDATE questions_result set status = :status where id = :id", $params);
        $this->log('questions','Пользователь ответил на вопрос: '.implode(': ',$params).'.');
        return $res;
    }

    public function setUserGame($uid, $gid, $count_questions)
    {       
        $res = 0;
        $params = array(
            "count_questions" => $count_questions,
            "uid" => $uid, 
            "gid1" => $gid
        );
        $this->con->query("INSERT INTO users_game (count_questions, gid, uid) VALUES (:count_questions, :gid1, :uid)", $params);
        return $res;
    }
    
    public function setUserGameStatus($uid, $gid, $status)
    {       
        $res = 0;
        $params = array(
            "status" => $status, 
            "gid" => $gid,
            "uid" => $uid
        );
        $this->con->query("UPDATE users_game set status = :status where gid = :gid and uid = :uid", $params);
        return $res;
    }

    public function setUserGameResult($uid, $correct, $gid)
    {       
        $res = 0;
        $params = array(
            "uid" => $uid,
            "correct" => $correct,
            "gid" => $gid
        );
        if($this->is_no_user_game_result($uid))
        {
            $res = $this->con->query("UPDATE users_game set count_questions_result = count_questions_result + 1, count_correct_result = count_correct_result + :correct where uid = :uid and gid = :gid", $params);
        }
        else
        {
            $res = 1;
        }
        return $res;
    }

    public function getUserGame($uid)
    {
        $params = array(
            "uid" => $uid
        );
        $res = $this->con->query("select * from users_game where uid = :uid and status = 0 limit 1", $params);
        return isset($res) ? $res : null;
    }
    
    public function getGames($id)
    {
        $params = array(
            "id" => $id
        );
        $res = $this->con->query("select * from games where id = :id", $params);
        return isset($res) ? $res : null;
    }
    
    public function getGamesForUser($uid)
    {
        $params = array(
            "uid" => $uid
        );
        $res = $this->con->query("select count(1) as cnt, sum(`sum`) as summa from games where uid = :uid", $params);
        return isset($res) ? $res : null;
    }
    
    public function getCountVictoryGamesForUser($uid)
    {
        $params = array(
            "uid" => $uid
        );
        $res = $this->con->query("select count(1) as cnt from game_result where win_uid = :uid", $params);
        return isset($res) ? $res : null;
    }
    
    public function getGameType($gid)
    {
        $params = array(
            "id" => $gid
        );
        $res = $this->con->query("select type from games where id = :id", $params);
        return isset($res[0]) && isset($res[0]['type']) ? $res[0]['type'] : null;
    }  

    public function setGames($uid, $type)
    {       
        $res = 0;
        $params = array(
            "type" => $type,
            "uid" => $uid
        );
        if($this->con->query("INSERT INTO games (uid, lid, type) select tid, lid, :type from users where tid = :uid", $params))
        {
            $res = $this->con->lastInsertId();
        }
        return $res;
    }
    
    public function setGamesResult($gid, $result_count, $status)
    {       
        $res = 0;
        $params = array(
            "gid" => $gid,
            "result_count" => $result_count,
            "status" => $status
        );
        $this->con->query("UPDATE games set result_count = :result_count, status = :status where id = :gid", $params);
        $res = 1;
        return $res;
    }
    
    public function getGameResultWin($lid, $result_count, $uid, $type)
    {
        $params = array(
            "lid" => $lid,
            "result_count" => $result_count,
            "uid" => $uid,
            "type" => $type
        );
        $res = $this->con->query("select * from games where lid = :lid and uid != :uid and result_count != :result_count and status = 1 and type = :type limit 1", $params);
        return isset($res) ? $res : null;
    }
    
    public function getCostLeague($lid)
    {
        $params = array(
            "id" => $lid
        );
        $res = $this->con->query("select cost from league where id = :id", $params);
        return isset($res[0]) && isset($res[0]['cost']) ? $res[0]['cost'] : 0;
    }  

    public function setGameResultWin($win_uid, $win_gid, $lose_uid, $lose_gid)
    {       
        $res = 0;
        $params = array(
            "win_uid" => $win_uid,
            "win_gid" => $win_gid,
            "lose_uid" => $lose_uid,
            "lose_gid" => $lose_gid
        );
        $this->con->query("INSERT INTO game_result (lose_gid, lose_uid, win_gid, win_uid) values (:lose_gid, :lose_uid, :win_gid, :win_uid)", $params);
        $res = 1;
        return $res;
    }
    
    public function setProfitBufferSum($gid, $uid, $sum)
    {       
        $res = 0;
        $params = array(
            "gid" => $gid,
            "uid" => $uid,
            "sum" => $sum,
            "update_sum" => $sum
        );
        $this->con->query("INSERT INTO profit_buffer (gid, uid, sum) values (:gid, :uid, :sum) ON DUPLICATE KEY UPDATE sum = :update_sum", $params);
        $res = 1;
        return $res;
    }
    
    public function setGameSum($gid, $sum)
    {       
        $res = 0;
        $params = array(
            "id" => $gid,
            "sum" => $sum
        );
        $this->con->query("UPDATE games set sum = :sum where id = :id", $params);
        $res = 1;
        return $res;
    }    
    
    public function setGameStatus($gid, $status)
    {       
        $res = 0;
        $params = array(
            "gid" => $gid,
            "status" => $status
        );
        $this->con->query("UPDATE games set status = :status where id = :gid", $params);
        $res = 1;
        return $res;
    }
    
    public function getLeagueForName($name)
    {
        $params = array(
            "name" => $name
        );
        $res = $this->con->query("select * from league where name = :name", $params);
        return isset($res) ? $res : null;
    }
    
    public function setUser($tid, $lid)
    {       
        $res = 0;
        $params = array(
            "tid" => $tid,
            "lid" => $lid
        );
        $this->con->query("UPDATE users set lid = :lid where tid = :tid", $params);
        return $res;
    }
    
    public function setUserBalance($tid, $sum)
    {       
        $res = 0;
        $params = array(
            "tid" => $tid,
            "sum" => $sum
        );
        $this->con->query("UPDATE users set balance = balance + :sum where tid = :tid", $params);
        return $res;
    }

    public function setProfitSum($gid, $sum)
    {       
        $res = 0;
        $params = array(
            "gid" => $gid,
            "sum" => $sum
        );
        $this->con->query("INSERT INTO profits (gid, sum) values (:gid, :sum)", $params);
        $res = 1;
        return $res;
    }

    public function getCountPeopleInvolved($tid)
    {
        $params = array(
            "tid" => $tid
        );
        $res = $this->con->query("select count(1) as cnt from users where referal = :tid", $params);
        return isset($res) ? $res : null;
    } 
    
    public function getUserId($tid)
    {
        $params = array(
            "tid" => $tid
        );
        $res = $this->con->query("select id from users where tid = :tid", $params);
        return isset($res) ? $res : null;
    }
    
    public function getSumPeopleEarnings($tid)
    {
        /*$params = array(
            "tid" => $tid
        );
        $res = $this->con->query("select count(1) as cnt from users where referal = :tid", $params);
        return isset($res) ? $res : null;*/
    } 
    
    public function setUserCash($tid, $cash)
    {       
        $res = 0;
        $params = array(
            "tid" => $tid,
            "cash" => $cash
        );
        $this->con->query("UPDATE users set cash = :cash where tid = :tid", $params);
        $res = 1;
        return $res;
    }
    
    public function setStatusUserCash($tid, $cash_status)
    {       
        $res = 0;
        $params = array(
            "tid" => $tid,
            "cash_status" => $cash_status
        );
        $this->con->query("UPDATE users set cash_status = :cash_status where tid = :tid", $params);
        $res = 1;
        return $res;
    }
    
    public function setCashOut($tid, $cash_sum)
    {       
        $res = 0;
        $params = array(
            "tid" => $tid,
            "cash_sum" => $cash_sum
        );
        $this->con->query("INSERT INTO cash (tid, cash_sum) values (:tid, :cash_sum)", $params);
        $res = 1;
        return $res;
    }
}