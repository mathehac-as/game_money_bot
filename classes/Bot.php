<?php

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Client;
use TelegramBot\Api\Types\Message;

/**
 * Description of bot
 *
 * @author семья
 */
class Bot {
    private $bot;
    private $config;
    private $model;
    private $sender;
    
    public function __construct($config){
        $this->config = $config;
        $this->bot = new Client($this->config['botToken']);
        $this->model = new Model($this->config['db']);
        $this->sender = new Sender($this->config['telegram_url'], $this->config['botToken'], $this->config['telegram_receiver_id']);
    }
    
    public function setCommands(){ 
        // регистрируем команду start и указываем для нее callback
        $bot = $this->bot;
        $model = $this->model;
        $sender = $this->sender;
               
        $bot->command("start", function ($message) use ($bot, $model) {
            $result = false;
            $user = $model->getUser($message->getChat()->getId());
            if(isset($user[0]) && isset($user[0]['tid']))
            {
                $result = true;
            }
            elseif(!empty($message->getText()) && preg_match('/^\/start\s(\d+?)$/', $message->getText(), $matches) 
                    && !empty($matches[1]))
            {
                $referal = $matches[1];
                $res = $model->getUser($referal);
                if(isset($res[0]) && isset($res[0]['tid']))
                {
                    if($model->registration($message->getChat()->getUsername(), $message->getChat()->getFirstName(), $message->getChat()->getLastName(), $message->getChat()->getId(), $res[0]['tid']))
                    {
                        $result = true;
                    }
                    else
                    {
                        $bot->sendMessage($message->getChat()->getId(), "Вы не авторизованы в боте!");
                    }
                }
                else
                {
                    $bot->sendMessage($message->getChat()->getId(), "Вы не авторизованы в боте: реферал не найден!");
                }
            }
            else
            {
                $bot->sendMessage($message->getChat()->getId(), "Вы не авторизованы в боте: не указан реферал!");
            } 
            
            if($result)
            {
                $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[
                                    ['text' => "\xF0\x9F\x92\xB0 Личный кабинет"], ['text' => "\xF0\x9F\x8E\xAE Играть"] 
                            ], [
                                    ['text' => "\xE2\x84\xB9 Об игре"], ['text' => "\xF0\x9F\x93\x9D Тех.поддержка"] 
                            ]], false, true);
                $bot->sendMessage($message->getChat()->getId(), "Меню", false, null,null,$keyboard);
            }
            else
            {
                $bot->sendMessage($message->getChat()->getId(), "Вы не авторизованы в боте!");
            }
        });
               
        $bot->on(function($update) use ($bot, $model, $sender){
            $callback = $update->getCallbackQuery();
            $data = '';
            $mtext = '';
            if (!is_null($callback) && strlen($callback->getData()))
            {
                $message = $callback->getMessage();
                $data = $callback->getData();
            }
            else 
            {
                $message = $update->getMessage();
                $mtext = $message->getText();
            }
            $chatId = $message->getChat()->getId();
            if(mb_stripos($mtext, "Личный кабинет") !== false){
                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                    [
                        [['callback_data' => 'purse', 'text' => 'Кошелек']],
                        [['callback_data' => 'statistics', 'text' => 'Статистика']],
                        [['callback_data' => 'partner_prog', 'text' => 'Партнерская программа']]
                    ]
                );
                $msg = "<strong>Личный кабинет</strong>";
                $bot->sendMessage($chatId, $msg, 'HTML', null, null, $keyboard);
            }
            elseif(mb_stripos($mtext, "Играть") !== false){
                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                    [
                        [['callback_data' => 'random_questions', 'text' => 'Рандомные вопросы']],
                        [['callback_data' => 'procces_make', 'text' => 'В процессе создания']],
                    ]
                );
                $msg = "<strong>Выберите тип игры</strong>";
                $bot->sendMessage($chatId, $msg, 'HTML', null, null, $keyboard);
            }
            elseif($data == "random_questions"){
                $res_group = $model->getGroupLeague();
                foreach ($res_group as $val)
                {
                    $keyboard[] = [['callback_data' => $val['strcode'], 'text' => $val['name']]];
                }
                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup($keyboard);
                $bot->sendMessage($chatId, "Выберите лигу!", false, null, null, $keyboard);
            }
            elseif(in_array($data, array("group_league_new", "group_league_lover", "group_league_professional", "group_league_stars"))){
                $res = $model->getBalance($chatId);
                $balance = 0;
                if(isset($res[0]) && isset($res[0]['balance']))
                {
                    $balance = $res[0]['balance'];
                }
                $res = $model->getLeagueForGroup($data);
                foreach ($res as $val)
                {
                    $league[] = [['callback_data' => $val['name'], 'text' => $val['description']]];
                }
                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup($league);
                $bot->sendMessage($chatId, "Выберите стоимость игры!\n<strong>Ваш баланс:</strong> ".$balance." руб.", 'HTML', null, null, $keyboard);
            }
            elseif(mb_stripos($mtext, "Об игре") !== false){
                $msg = "<strong>Правила игры:</strong>\nВыбрать лигу, ответить на 18 вопросов по одному из каждой темы.\n\n На каждый вопрос даётся 15 секунд.\n".
                      "Время не отображается, но учитывается в системе.\n По истечении 15 секунд ответ на вопрос считается неверным.\n".
                      "<b>Победа:</b>\nДать большее количество правильных ответов, чем ваш соперник.\nПри победе, вы получаете от 80 до 90 % выйгрыша. 80 % - это стартовый процент.\n".
                      "Вы можете повысить свой доход от 0,1 до 5 %  за счёт добавления уникальных вопросов в игру, 10 вопросов - 0,1 % дохода.\n".
                      "А также 5 % вы получаете с привлеченных Вами людей, более подробно об этом см. в Партнёрской программе.\n".
                      "<b>Ничья</b>\nЕсли Вы и Ваш соперник ответили правильно на одинаковое количество вопросов, деньги возвращаются обратно каждому игроку.\n".
                      "Для реальной игры нужно пополнить баланс на любую сумму не менее стоимости низшей лиги.\n".
                      "Вывод средств осуществляется через платежную систему (взимается комиссия при выводе).\n".
                      "Обработка запроса на вывод средств осуществляется в течение 3-х рабочих дней (обычно в течение часа).";
                $bot->sendMessage($chatId, $msg, 'HTML');
            }
            elseif(mb_stripos($mtext, "Тех.поддержка") !== false){
                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                    [
                        [['text' => 'Задать вопрос', 'url' => 'https://t.me/KnowMoneyinfo']],
                        [['text' => 'Задать вопрос на почту', 'url' => 'Info.KnowMoney@gmail.com']]
                    ]
                );
                $bot->sendMessage($chatId, "Тех.поддержка", false, null, null, $keyboard);
            }
            elseif($data == "purse"){
                $res = $model->getBalance($chatId);
                $balance = 0;
                if(isset($res[0]) && isset($res[0]['balance']))
                {
                    $balance = $res[0]['balance'];
                }
                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                    [
                        [['callback_data' => 'in_cash', 'text' => 'Пополнить баланс']],
                        [['callback_data' => 'out_cash', 'text' => 'Вывести денежные средства']],
                        [['callback_data' => 'history_cash', 'text' => 'История движения денежных средств']]
                    ]
                );
                $msg = "<strong>Кошелек!</strong>\n<strong>Личный счет:</strong> ".$balance." руб.\n";
                $msg .= "<strong>Всего пополнили:</strong> 0 руб.\n<strong>Всего вывели:</strong> 0 руб.";
                $bot->sendMessage($chatId, $msg, 'HTML', null, null, $keyboard);
                $bot->answerCallbackQuery($chatId);
            }
            elseif($data == "statistics"){
                $res_game = $model->getGamesForUser($chatId);
                $game_count = 0;
                if(isset($res_game[0]) && isset($res_game[0]['cnt']))
                {
                    $game_count = $res_game[0]['cnt'];
                }
                
                $win_money = 0;
                if(isset($res_game[0]) && isset($res_game[0]['summa']))
                {
                    $win_money = $res_game[0]['summa'];
                }
                
                $res_game_victory = $model->getCountVictoryGamesForUser($chatId);
                $game_count_victory = 0;
                if(isset($res_game_victory[0]) && isset($res_game_victory[0]['cnt']))
                {
                    $game_count_victory = $res_game_victory[0]['cnt'];
                }
                
                $game_count_convers = 0;
                if(isset($game_count_victory) && isset($game_count))
                {
                    $game_count_convers = ($game_count_victory/$game_count)*100;
                }
               
                $msg = "<strong>Статистика</strong>\n<strong>Общее количество игр:</strong> ".$game_count."\n";
                $msg .= "<strong>Побед:</strong> ".$game_count_victory."\n<strong>Конверсия побед:</strong> ".round($game_count_convers, 3)." %\n";
                $msg .= "<strong>Доход от игр:</strong> ".$win_money." руб.\n";
                $msg .= "<strong>Доход от рефералов:</strong> 0 руб.\n";
                $msg .= "<strong>Общий доход:</strong> ".$win_money." руб.\n";
                $bot->sendMessage($chatId, $msg, 'HTML');
                $bot->answerCallbackQuery($chatId);
            }
            elseif($data == "partner_prog"){
                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                    [
                        [['callback_data' => 'affiliate_program_description', 'text' => 'Описание программы']],
                        [['callback_data' => 'many_people_involved', 'text' => 'Сколько привлечено людей']],
                        [['callback_data' => 'earnings_from_partners', 'text' => 'Заработок с партнеров']],
                        [['callback_data' => 'getreferal', 'text' => 'Получить реферальную ссылку']]
                    ]
                );
                $bot->sendMessage($chatId, 'Партнерская программа', false, null, null, $keyboard);
            }
            elseif($data == "affiliate_program_description"){
                $msg = "<strong>Партнёрская программа:</strong>\n".
                       "Начисления происходят после победы привлечённого реферала\n".
                       "1 линия - 2 %\n".
                       "2 линия - 1 %\n".
                       "3 линия - 0,5 %\n".
                       "4 линия - 0,5 %\n".
                       "5 линия - 0,2 %\n".
                       "6 линия - 0,2 %\n".
                       "7 линия - 0,2 %\n".
                       "8 линия - 0,2 %\n".
                       "9 линия - 0,1 %\n".
                       "10 линия - 0,1 %\n".
                       "Данный раздел находится в разработке!";
                $bot->sendMessage($chatId, $msg, 'HTML');
            }
            elseif($data == "many_people_involved"){
                $res = $model->getCountPeopleInvolved($chatId);
                $count = 0;
                if(isset($res[0]) && isset($res[0]['cnt']))
                {
                    $count = $res[0]['cnt'];
                }
                $msg = "<strong>Сколько привлечено людей!</strong>\n<strong>Количество:</strong> ".$count." чел.\nОтображается только первый уровень, остальное в разработке";
                $bot->sendMessage($chatId, $msg, 'HTML');
            }
            elseif($data == "earnings_from_partners"){
                $res = $model->getSumPeopleEarnings($chatId);
                $sum = 0;
                if(isset($res[0]) && isset($res[0]['cnt']))
                {
                    $sum = $res[0]['sum_earnings'];
                }
                $msg = "<strong>Сколько заработано с партнеров!</strong>\n<strong>Сумма:</strong> ".$sum." руб.\nОтображается только первый уровень, остальное в разработке";
                $bot->sendMessage($chatId, $msg, 'HTML');
            }
            elseif($data == 'in_cash')
            {
                $res = $model->getUserId($chatId);
                $id = '';
                if(isset($res[0]) && isset($res[0]['id']))
                {
                    $id = $res[0]['id'];
                }
                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                    [
                        [['text' => 'Payeer', 'url' => 'https://payeer.com/03704598']],
                        /*[['text' => 'Яндекс деньги', 'url' => 'https://money.yandex.ru']],
                        [['text' => 'Киви', 'url' => 'https://qiwi.com']],
                        [['text' => 'Вебмани', 'url' => 'https://www.webmoney.ru']]*/
                    ]
                );                
                $msg = "<strong>Выберите платежную систему!</strong>\n<strong>Ваш ID: ".$id." - его необходимо указать при оплате</strong>\n<strong>Номер кошелька для перевода: P54174716</strong>\n";
                $sender->sendTelegram($msg);
                $bot->sendMessage($chatId, $msg, 'HTML', null, null, $keyboard);
                $bot->answerCallbackQuery($chatId);
            }
            elseif($data == 'out_cash')
            {
                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                    [
                        [['callback_data' => 'out_cash_payeer', 'text' => 'Payeer - комиссия 1 %']],
                        [['callback_data' => 'out_cash_bank_card', 'text' => 'Данный раздел находится в разработке']]
                    ]
                );                
                $msg = "Выберите платежную систему!\nМинимальная сумма для вывода 10 рублей";
                $bot->sendMessage($chatId, $msg, false, null, null, $keyboard);
                $bot->answerCallbackQuery($chatId);
            }
            elseif($data == 'out_cash_payeer')
            {              
                $msg = "Введите номер вашего кошелька Payeer!";
                $bot->sendMessage($chatId, $msg, false);
                $bot->answerCallbackQuery($chatId);
            }
            elseif(preg_match('/^(P\d+?)$/', $mtext, $matches))
            {
                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                    [
                        [['callback_data' => 'out_cash_payeer_yes', 'text' => 'Да']],
                        [['callback_data' => 'out_cash_payeer', 'text' => 'Нет']]
                    ]
                );    
                $payeer = $matches[1];
                $model->setUserCash($chatId, $payeer);
                $msg = "Вы ввели данный кошелек Payeer! <strong>".$payeer."</strong>\nВы верно ввели номер?\n";
                $bot->sendMessage($chatId, $msg, 'HTML', null, null, $keyboard);
                $bot->answerCallbackQuery($chatId);
            }
            elseif($data == 'out_cash_payeer_yes')
            {
                $model->setStatusUserCash($chatId, 1);              
                $msg = "Введите сумму для вывода!\nПример <strong>R100</strong> - R обязательный префикс (вывод 100 рублей)\nВыводятся только рубли без копеек";
                $bot->sendMessage($chatId, $msg, 'HTML');
                $bot->answerCallbackQuery($chatId);
            }
            elseif(preg_match('/^R(\d+?)$/', $mtext, $matches))
            {  
                $sum = $matches[1];
                $model->setCashOut($chatId, $sum);
                $msg = "Заявка на вывод принята! <strong>".$sum." руб</strong>\nОжидайте поступление в течение 3 дней\n";
                $sender->sendTelegram($msg);
                $bot->sendMessage($chatId, $msg, 'HTML');
                $bot->answerCallbackQuery($chatId);
            }
            elseif($data == 'history_cash')
            {
                $res = $model->getStatistic($chatId);
                $msg = "Данный раздел находится в разработке!\n";
                foreach ($res as $val)
                {
                    $msg .= $val['date_create'] .' - '. $val['description']."\n";
                }
                $bot->sendMessage($chatId, $msg);
                $bot->answerCallbackQuery($chatId);
            }
            elseif(mb_stripos($data, "confederacy") !== false)
            {
                $res = $model->getLeagueForName($data);
                if(isset($res[0]) && isset($res[0]['id']))
                {
                    $model->setUser($message->getChat()->getId(), $res[0]['id']);
                    $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                        [
                            [['callback_data' => 'real_game', 'text' => 'Реальная игра']],
                            [['callback_data' => 'test_game', 'text' => 'Тестовая игра']]
                        ]
                    );
                    $bot->sendMessage($chatId, "Выберите тип игры!", false, null, null, $keyboard);
                }
                $bot->answerCallbackQuery($chatId);
            }
            elseif($data == "real_game")
            {
                $sum = $model->getBalanceLeague($message->getChat()->getId());
                if($sum == 0)
                {
                    $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                        [
                            [['callback_data' => 'in_chash_game', 'text' => 'Пополнить кошелек']]
                        ]
                    );
                    $bot->sendMessage($chatId, "Пополнить кошелек для продолжения игры!", false, null, null, $keyboard);
                    $bot->answerCallbackQuery($callback->getId());
                }
                else 
                {
                    $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                        [
                            [['callback_data' => 'real_game_yes', 'text' => 'Начать игру']]
                        ]
                    );
                    $msg = "Вы можете начать игру";
                    $bot->sendMessage($chatId, $msg, false, null, null, $keyboard);                    
                    $bot->answerCallbackQuery($callback->getId());
                }
            }
            elseif($data == "in_chash_game")
            {
                $sum = $model->getBalanceLeague($message->getChat()->getId());
                if($sum == 0)
                {
                    $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                        [
                            [['callback_data' => 'in_chash_game_yes', 'text' => 'Проверить поступления денежных стредств']]
                        ]
                    );
                    $bot->sendMessage($chatId, "Переведите недежные средства на указаные номер карты: ".$this->config['numberCart'], false, null, null, $keyboard);
                    $bot->answerCallbackQuery($callback->getId());
                }
                else 
                {
                    $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                        [
                            [['callback_data' => 'real_game_yes', 'text' => 'Начать игру']]
                        ]
                    );
                    $msg = "Вы можете начать игру";
                    $bot->sendMessage($chatId, $msg, false, null, null, $keyboard);                    
                    $bot->answerCallbackQuery($callback->getId());
                }
            }
            elseif($data == "in_chash_game_yes")
            {
                $msg = "";
                $sum = $model->getBalanceLeague($message->getChat()->getId());
                if($sum > 0)
                {
                    $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                        [
                            [['callback_data' => 'real_game_yes', 'text' => 'Начать игру']]
                        ]
                    );
                    $msg = "Вы можете начать игру";
                }
                else 
                {
                    $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                        [
                            [['callback_data' => 'in_chash_game', 'text' => 'Пополнить кошелек']]
                        ]
                    );
                    $msg = "Вы не можете начать игру, пополните свой баланс";
                }
                $bot->sendMessage($chatId, $msg, false, null, null, $keyboard);
                $bot->answerCallbackQuery($callback->getId());
            }
            elseif($data == "real_game_yes")
            {
                $sum = $model->getBalanceLeague($message->getChat()->getId());
                if($sum > 0)
                {
                    $res_games = $model->setGames($message->getChat()->getId(), 'real');
                    if($res_games != 0)
                    {
                        $model->setUserGame($message->getChat()->getId(), $res_games, 18);
                        $res = $model->getQuestions($message->getChat()->getId());
                        if(isset($res[0]) && isset($res[0]['id']))
                        {
                            if($model->setQuestions($res[0]['id'], $message->getChat()->getId(), $res_games))
                            {
                                $res_game = $model->getUserGame($message->getChat()->getId());
                                if(isset($res_game[0]) && isset($res_game[0]['id']))
                                {
                                    $type = $model->getGameType($res_game[0]['gid']);
                                    if($type == 'real') 
                                    {
                                        $res_lid = $model->getGames($res_game[0]['gid']);
                                        if(isset($res_lid[0]) && isset($res_lid[0]['lid']))
                                        {
                                            $sum = $model->getCostLeague($res_lid[0]['lid']);
                                            $model->setUserBalance($message->getChat()->getId(), -$sum);
                                            $model->setProfitBufferSum($res_game[0]['gid'], $message->getChat()->getId(), $sum);
                                        }
                                    }
                                    $cntq = $res_game[0]['count_questions'];
                                    $cntqr = $res_game[0]['count_questions_result'];
                                    $answer = $model->getAnswers($res[0]['id']);
                                    $answers = [];
                                    foreach ($answer as $val)
                                    {
                                        $answers[] = [['callback_data' => $val['command'], 'text' => $val['name']]];
                                    }
                                    $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup($answers);
                                    $bot->sendMessage($chatId, "Вопрос - ".$cntq."/".($cntqr+1).": ".$res[0]['name'], false, null, null, $keyboard);
                                }
                            }
                            else
                            {
                                file_put_contents('error.log', print_r($res, true));
                                $bot->sendMessage($chatId, "Ошибка вопрос установка: ".$data);
                            }
                        }
                        else
                        {
                            file_put_contents('error.log', print_r($res, true));
                            $bot->sendMessage($chatId, "Ошибка нет вопросов для игры");
                        }
                    }
                    else
                    {
                        file_put_contents('error.log', print_r($res_time, true));
                        $bot->sendMessage($chatId, "Ошибка игры1: ".$data);
                    }
                    $bot->answerCallbackQuery($callback->getId());
                }
                else 
                {
                    $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                        [
                            [['callback_data' => 'in_chash_game', 'text' => 'Пополнить кошелек']]
                        ]
                    );
                    $msg = "Вы не можете начать игру, пополните свой баланс";
                    $bot->sendMessage($chatId, $msg, false, null, null, $keyboard);                    
                    $bot->answerCallbackQuery($callback->getId());
                }
            }
            elseif($data == "test_game")
            {
                $res_games = $model->setGames($message->getChat()->getId(), 'test');
                if($res_games != 0)
                {
                    $model->setUserGame($message->getChat()->getId(), $res_games, 18);
                    $res = $model->getQuestions($message->getChat()->getId());
                    if(isset($res[0]) && isset($res[0]['id']))
                    {
                        if($model->setQuestions($res[0]['id'], $message->getChat()->getId(), $res_games))
                        {
                            $res_game = $model->getUserGame($message->getChat()->getId());
                            if(isset($res_game[0]) && isset($res_game[0]['id']))
                            {
                                $cntq = $res_game[0]['count_questions'];
                                $cntqr = $res_game[0]['count_questions_result'];
                                $answer = $model->getAnswers($res[0]['id']);
                                $answers = [];
                                foreach ($answer as $val)
                                {
                                    $answers[] = [['callback_data' => $val['command'], 'text' => $val['name']]];
                                }
                                $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup($answers);
                                $bot->sendMessage($chatId, "Вопрос - ".$cntq."/".($cntqr+1).": ".$res[0]['name'], false, null, null, $keyboard);
                            }
                        }
                        else
                        {
                            file_put_contents('error.log', print_r($res, true));
                            $bot->sendMessage($chatId, "Ошибка вопрос установка: ".$data);
                        }
                    }
                    else
                    {
                        file_put_contents('error.log', print_r($res, true));
                        $bot->sendMessage($chatId, "Ошибка нет вопросов для игры");
                    }
                }
                else
                {
                    file_put_contents('error.log', print_r($res_time, true));
                    $bot->sendMessage($chatId, "Ошибка игры1: ".$data);
                }
                $bot->answerCallbackQuery($callback->getId());
            }
            elseif(mb_stripos($data, "answer") !== false)
            {
                $res_time = $model->getQuestionsTime($message->getChat()->getId());
                if(isset($res_time[0]) && isset($res_time[0]['id']))
                {
                    $status = 1;
                    $answer = $data;
                } 
                else
                {
                    $res_time = $model->getQuestionsNoTime($message->getChat()->getId());
                    $status = 2;
                    $answer = null;
                }
                if(isset($res_time[0]) && isset($res_time[0]['id']))
                {
                    $res_correct = $model->getAnswerCorrect($answer);
                    $res_game = $model->getUserGame($message->getChat()->getId());
                    if(isset($res_game[0]) && isset($res_game[0]['id']))
                    {
                        if($model->setUserGameResult($message->getChat()->getId(), $res_correct, $res_game[0]['id']))
                        {
                            if($model->setAnswers($answer, $message->getChat()->getId(), $res_game[0]['gid']))
                            {
                                if($model->setQuestionsStatus($res_time[0]['id'], $status))
                                {
                                    $cntq = $res_game[0]['count_questions'];
                                    $cntqr = $res_game[0]['count_questions_result'];
                                    $cntcr = $res_game[0]['count_correct_result'];
                                    if($cntq == $cntqr)
                                    {
                                        $model->setGamesResult($res_game[0]['gid'], $cntcr, 1);
                                        $res = $model->getGames($res_game[0]['gid']);
                                        if(isset($res[0]) && isset($res[0]['lid']))
                                        {
                                            $type = $model->getGameType($res_game[0]['gid']);
                                            $nowin_uid = $model->getGameResultWin($res[0]['lid'], $cntcr, $message->getChat()->getId(), $type);
                                            if($nowin_uid[0] && $nowin_uid[0]['uid'])
                                            {
                                                $sum = 0;
                                                $gid = null;
                                                if($cntcr > $nowin_uid[0]['result_count'])
                                                {
                                                    $model->setGameResultWin($message->getChat()->getId(), $res_game[0]['gid'], $nowin_uid[0]['uid'], $nowin_uid[0]['id']);
                                                    $win['cnt'] = $cntcr; 
                                                    $win['uid'] = $message->getChat()->getId();
                                                    $lose['cnt'] = $nowin_uid[0]['result_count']; 
                                                    $lose['uid'] = $nowin_uid[0]['uid'];
                                                    $gid = $res_game[0]['gid'];
                                                    $nowin_gid = $nowin_uid[0]['id'];
                                                }
                                                else
                                                {
                                                    $model->setGameResultWin($nowin_uid[0]['uid'], $nowin_uid[0]['id'], $message->getChat()->getId(), $res_game[0]['gid']);
                                                    $win['cnt'] = $nowin_uid[0]['result_count']; 
                                                    $win['uid'] = $nowin_uid[0]['uid'];
                                                    $lose['cnt'] = $cntcr; 
                                                    $lose['uid'] = $message->getChat()->getId();
                                                    $gid = $nowin_uid[0]['id'];
                                                    $nowin_gid = $res_game[0]['gid'];
                                                }
                                                if($type == 'real') 
                                                {
                                                    $sum = $model->getCostLeague($res[0]['lid']);  
                                                    $percent = $this->config['percent'];
                                                    $sum_percent = $sum * (1 - $percent);
                                                    $sum_percent_profit = $sum * $percent;
                                                    $model->setProfitBufferSum($gid, $win['uid'], 0);
                                                    $model->setProfitBufferSum($nowin_gid, $lose['uid'], 0);
                                                    $model->setUserBalance($win['uid'], $sum_percent + $sum);
                                                    $model->setProfitSum($gid, $sum_percent_profit);
                                                    $model->setGameSum($gid, $sum_percent);
                                                    $model->setGameSum($nowin_gid, -$sum);
                                                }
                                                $model->setGameStatus($res_game[0]['gid'], 2);
                                                $model->setGameStatus($nowin_uid[0]['id'], 2);
                                                $to_lose_res = $model->getUser($win['uid']);
                                                if(isset($to_lose_res[0]) && isset($to_lose_res[0]['login']))
                                                {
                                                    $to_lose = $to_lose_res[0]['login'];
                                                }
                                                $to_win_res = $model->getUser($lose['uid']);
                                                if(isset($to_win_res[0]) && isset($to_win_res[0]['login']))
                                                {
                                                    $to_win = $to_win_res[0]['login'];
                                                }
                                                $bot->sendMessage($win['uid'], "Игра окончена: результат - вы выиграли со счетом ".$win['cnt']."/".$lose['cnt'].", контакт соперника: @".$to_win);
                                                $bot->sendMessage($lose['uid'], "Игра окончена: результат - вы проиграли со счетом ".$lose['cnt']."/".$win['cnt'].", контакт соперника: @".$to_lose);
                                            }
                                            else 
                                            {
                                                //file_put_contents('error.log', print_r($win_uid, true));
                                                $bot->sendMessage($chatId, "Игра сыграна - ожидайте результата");
                                            }
                                            $model->setUserGameStatus($message->getChat()->getId(), $res_game[0]['gid'], 1);
                                        }
                                    }
                                    else
                                    {
                                        $res = $model->getQuestions($message->getChat()->getId());
                                        if(isset($res[0]) && isset($res[0]['id']))
                                        {
                                            $res_game = $model->getUserGame($message->getChat()->getId());
                                            if(isset($res_game[0]) && isset($res_game[0]['gid']))
                                            {
                                                file_put_contents('error.log', print_r($res_game, true));
                                                if($model->setQuestions($res[0]['id'], $message->getChat()->getId(), $res_game[0]['gid']))
                                                {
                                                    $answer = $model->getAnswers($res[0]['id']);
                                                    $answers = [];
                                                    foreach ($answer as $val)
                                                    {
                                                        $answers[] = [['callback_data' => $val['command'], 'text' => $val['name']]];
                                                    }
                                                    $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup($answers);
                                                    $bot->sendMessage($chatId, "Вопрос - ".$cntq."/".($cntqr+1).": ".$res[0]['name'], false, null, null, $keyboard);
                                                }
                                            }
                                        }
                                    }
                                    $bot->answerCallbackQuery($callback->getId());
                                }
                                else
                                {
                                    file_put_contents('error.log', print_r($res_time, true).'11');
                                    $bot->sendMessage($chatId, "Ошибка статуса: ".$data);
                                }
                            }
                            else
                            {
                                $bot->sendMessage($chatId, "Ошибка ответа: ".$data);
                            }
                        }                       
                    }
                }
            }
            elseif(mb_stripos($data, "getreferal") !== false)
            {
                $msg = '<a href="https://t.me/KnowMoneyBot?start='.$chatId.'">Вход в игру</a>';
                $bot->sendMessage($chatId, $msg, 'HTML');
            }
        },  function($message) use ($bot){
                return true; // когда тут true - команда проходит
        });
    }
    
    public function run(){
        try {
            $this->bot->run();
        } catch (\TelegramBot\Api\Exception $e) {
            file_put_contents('error.log', $e->getMessage());
        }
    }
}