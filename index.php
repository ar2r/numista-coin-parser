<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
<head>
<meta http-equiv="Expires" content="Fri, Jan 01 1900 00:00:00 GMT">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Cache-Control" content="no-cache">
<meta http-equiv="Content-Type" content="text/html; charset=Windows-1251">
<meta http-equiv="Lang" content="en">
<meta name="author" content="">
<meta http-equiv="Reply-to" content="@.com">
<meta name="generator" content="PhpED 5.8">
<meta name="description" content="">
<meta name="keywords" content="">
<meta name="creation-date" content="01/01/2009">
<meta name="revisit-after" content="15 days">
<title>Untitled</title>
<link rel="stylesheet" type="text/css" href="my.css">
</head>
<body>
<?
include("lib/phpQuery-onefile.php");
include("lib/s_http.php");

require_once "lib/config.php";
require_once "DbSimple/Generic.php";

// Подключаемся к БД.
$DB = DbSimple_Generic::connect('mysql://coin:coin@localhost/coin');

// Устанавливаем обработчик ошибок.
$DB->setErrorHandler('databaseErrorHandler');

// Код обработчика ошибок SQL.
function databaseErrorHandler($message, $info)
{
    // Если использовалась @, ничего не делать.
    if (!error_reporting()) return;
    // Выводим подробную информацию об ошибке.
    echo "SQL Error: $message<br><pre>"; 
    print_r($info);
    echo "</pre>";
    exit();
}

function decode_entities($text) {
    $text= html_entity_decode($text,ENT_QUOTES,"ISO-8859-1"); #NOTE: UTF-8 does not work!
    $text= preg_replace('/&#(\d+);/me',"chr(\\1)",$text); #decimal notation
    $text= preg_replace('/&#x([a-f0-9]+);/mei',"chr(0x\\1)",$text);  #hex notation
    return $text;
}

//$user = array('user_id' => 101, 'user_name' => 'Rabbit', 'user_age' => 30);
//$newUserId = $DB->query(
//    'INSERT INTO user(?#) VALUES(?a)', 
//    array_keys($row), 
//    array_values($row)
//);

// http://habrahabr.ru/blogs/jquery/69149/
// http://www.samborsky.com/php/1010/

?>
<a href="index.php">Index</a> | <a href="?action=download">Download</a> | <a href="?action=parse">Parse</a> | <a href="?action=stat">Show  stat</a> | <a href="?action=debug">Debug</a>
<hr>
<?
// DEFINE CONFIG

$MISTA_HOST = "http://en.numista.com/";


function RenderPropTable($data){
    echo "<table border=1>";
    
    foreach($data as $key => $value){
        echo "<tr>";
        echo "<td>";
        echo nl2br($key);
        echo "</td>";
        
        echo "<td>";
        echo nl2br($value);
        echo "</td>";
        echo "</tr>";
    }    
    echo "</table>";
    
}
if($_REQUEST['source']){
    show_source("index.php");
}
if($_REQUEST['action']=='download'){
    $arLinks = array();
    
    $http = new s_http();
    // Инициализируем
    $http->init();
    
    $http->get('http://en.numista.com');
    
    //login    
    if( $http->post('http://en.numista.com/connexion/connecter.php','connexion_pseudo=LOGIN&connexion_mdp=PASSWORD&fuseau_horaire=240&bouton=Sign+in') ){
        // Все ок, выводим скачанную информацию
        //echo $http->data();
        if($http->get('http://en.numista.com/vous/vos_pieces.php')){
            $data = $http->data();    
            $document = phpQuery::newDocument($data);
  
            $hrefs = $document->find('a[href*=catalogue/pieces]');
            
            
            
            foreach ($hrefs as $el) {
                $pq = pq($el);
                $link = $pq->attr('href');
                $arLinks[]= str_replace('..',$MISTA_HOST,$link);
            }
            
            $arLinks = array_unique($arLinks);
            echo "<pre>";
            print_r($arLinks);
            echo "<hr>";
            foreach($arLinks as $row){
                //echo "\nLoad url:".$row;
                $arParams = array();
                if($http->get($row)){
                    $coin_doc = phpQuery::newDocument($http->data()); 

                    $arInsertCoin = array();
                    
                    // Get Coin ID
                    if (preg_match('/pieces([0-9]+).html/six', $row, $regs)) {
                        $arInsertCoin['coin_id'] = $regs[1];
                        $arInsertCoin['url'] = $row;
                    }
                    $arInsertCoin['title']='';
                    $arInsertCoin['subtitle']='';
                    $arInsertCoin['comm']='';
                    $arInsertCoin['description']='';
                    $arInsertCoin['dt_update']=date('Y-m-d H:i:s');
                    
                    
                    
                    /**
                    *       TITLE AND SUBTITLE
                    */
                    
                    $h1 = $coin_doc->find('h1:first')->html();
                    $h1_sub = $coin_doc->find('h1:first')->find('span')->html();
                    $h1 = str_replace($h1_sub,"",$h1);
                    $arInsertCoin['subtitle'] = trim(strip_tags($h1_sub));
                    $arInsertCoin['title'] = trim(strip_tags($h1));
                    
                    echo "<hr><a href='{$row}'>";
                    echo "<h1>";
                    echo $arInsertCoin['title'];
                    echo "</h1>";
                    
                    echo "</a>";
                    echo $arInsertCoin['subtitle']; // Подзаголовок
                    echo "<br />";
                    
                      
                    /**
                    *       PHOTOS
                    */

                    $DB->query('DELETE FROM img WHERE coin_id='.$arInsertCoin['coin_id']);

                    $imgs = $coin_doc->find('a[href*=photos]');
                    $arImg = array();
                    foreach ($imgs as $img) {
                        $pq = pq($img);
                        $img_tmp = array();
                        
                        $img_tmp['coin_id'] = $arInsertCoin['coin_id'];
                        $img_tmp['dt_update']=date('Y-m-d H:i:s');
                        $img_tmp['thumb'] = $MISTA_HOST."catalogue/".$pq->find('img[src*=photos]')->attr("src");
                        $img_tmp['full'] = $MISTA_HOST."catalogue/".pq($img)->attr("href");

                        $newYearID = $DB->query('INSERT IGNORE INTO img(?#) VALUES(?a)',array_keys($img_tmp), array_values($img_tmp)); 
                        
                        echo "<a href='".$img_tmp['full']."' target='_blank'>";
                        echo "<img src='".$img_tmp['thumb']."'>";
                        echo "</a>";
                    } 
                    
                    /**
                    *       Commemorative issue
                    */
                    $strCom = "<h3>Commemorative issue</h3>";
                    $strObv = "<h4>Obverse</h4>";
                    $strRev = '<h3 id="collec">';
                    $p0 = strpos($coin_doc,$strCom);
                                           
                       
                    /**
                    *       Obverse - reverse   
                    */
                    $p1 = strpos($coin_doc,$strObv);
                    $p3 = strpos($coin_doc,$strRev);
                    
                    if($p0 && $p1){ // Commerative and Obverese pos
                        echo $arInsertCoin['comm'] = substr($coin_doc,$p0,$p1-$p0);    
                    }
                    
                    if($p1 && $p3){
                        // Оба поля есть
                        echo "<h3>[Desc]</h3><div style='border:1px solid #585858; background-color: #FFFFC0; padding: 10px;'>";
                        echo $arInsertCoin['description'] = substr($coin_doc,$p1,$p3-$p1);
                        echo "</div>";
                    }
                    
                    $newCoinID = $DB->query('INSERT IGNORE INTO coin(?#) VALUES(?a)',array_keys($arInsertCoin), array_values($arInsertCoin));
                    if(!$newCoinID){
                        $DB->query('UPDATE coin SET ?a WHERE coin_id='.$arInsertCoin['coin_id'], $arInsertCoin);
                    }
                        
                    /**
                    *       CATNUM
                    */
                    $catnum = $coin_doc->find('div[class=infos_techniques]')->find('div');
                    $catnum = trim(strip_tags($catnum));
                     
                    /**
                    *       FEATURES
                    */
                    $tables = $coin_doc->find('table:first');
                    $arFeatures = array(); // Массив для вставки в БД
                    $arFeatures['coin_id'] = $arInsertCoin['coin_id'];
                    $arFeatures['catnum'] = $catnum;
                    foreach ($tables as $table) {
                        $pq = pq($table);
                        $trs = $pq->find('tr');

                        foreach($trs as $tr){
                            $pq_tr = pq($tr);

                            $param = array();
                            $param['NAME'] = trim(strip_tags($pq_tr->find('th')->html()));
                            $param['VALUE'] = trim(strip_tags($pq_tr->find('td')->html()));
                            
                            if($param['NAME'] && $param['VALUE']){
                                $row_name = strtolower(str_replace(" ","_",$param['NAME']));
                                $arFeatures[ $row_name ] = $param['VALUE'];
                                
                                if($row_name == 'value'){
                                    $param['VALUE'] = str_replace("\n","",$param['VALUE']);
                                    $param['VALUE'] = str_replace("\t\t","\t",$param['VALUE']);
                                    $param['VALUE'] = str_replace("\t\t","\t",$param['VALUE']); // trololo :-)
                                    $arVal = explode("\t", $param['VALUE']);
                                    
//                                    echo "<hr color red>";
//                                    var_dump($arVal);
//                                    echo "<hr color red>";
                                    if(sizeof($arVal)!=2)continue;

                                    $tmpVal = explode(" ",$arVal[0]);
                                    $arFeatures['value'] = $tmpVal[0];
                                    $arFeatures['wordvalue'] = substr($arVal[0], strlen($tmpVal[0]));
                                    $arFeatures['currcode'] = $arVal[1];
                                    $arFeatures['currency'] = trim(strip_tags($pq_tr->find('td')->find('abbr')->attr('title')));;
                                        
                                }
                                $arParams[]=$param;
                            }
                        }
                    }
                    
                    // Insert feature in table
                    $newFeature = $DB->query('INSERT IGNORE INTO feature(?#) VALUES(?a)',array_keys($arFeatures), array_values($arFeatures));
                    if(!$newFeature){
                        echo "UPDATE FEATURE!!!!!!!!!";
                        $DB->query('UPDATE feature SET ?a WHERE coin_id='.$arInsertCoin['coin_id'], $arFeatures);
                    }
                    
                    echo "\n<h3>[Features]</h3>";
                    RenderPropTable($arFeatures); // Функция, где рендерим
                    
                    /**
                    *       TABLE years               
                    */
                    $arYears = array();
                    $tables = $coin_doc->find('table[class=collection]');
                    foreach ($tables as $table) {
                        $pq = pq($table);
                        $trs = $pq->find('tr');
                        
                        $DB->query('DELETE FROM year WHERE coin_id='.$arInsertCoin['coin_id']);
                        
                        foreach($trs as $tr){
                            $pq_tr = pq($tr);
                            $year_row = array();
                            $year_row['coin_id'] = $arInsertCoin['coin_id'];
                            $year_row['year'] = trim(strip_tags($pq_tr->find('td:eq(0)')->html()));
                            $year_row['mintage'] = trim(strip_tags($pq_tr->find('td:eq(1)')->html()));
                            $year_row['vg'] = intval(strip_tags($pq_tr->find('td:eq(2)')->find('input')->attr('value')));
                            $year_row['f'] = intval(strip_tags($pq_tr->find('td:eq(3)')->find('input')->attr('value')));;
                            $year_row['vf'] = intval(strip_tags($pq_tr->find('td:eq(4)')->find('input')->attr('value')));
                            $year_row['xf'] = intval(strip_tags($pq_tr->find('td:eq(5)')->find('input')->attr('value')));
                            $year_row['unc'] = intval(strip_tags($pq_tr->find('td:eq(6)')->find('input')->attr('value')));
                            $year_row['exchange'] = intval(strip_tags($pq_tr->find('td:eq(7)')->find('input')->attr('value')));
                            $year_row['comment'] = $pq_tr->find('td:eq(9)')->html();
                            $year_row['mycomment'] = $pq_tr->find('td:eq(9)')->find('div')->html();
                            
                            // Чистим значения
                            $year_row['comment'] = trim(strip_tags(str_replace($year_row['mycomment'],"", $year_row['comment'])));
                            $year_row['mycomment'] = trim(strip_tags($year_row['mycomment']));
                            
                            // Get cat num
                            if (preg_match('/(KM#[0-9.]+)/i', $year_row['comment'], $regs)) {
                                $year_row['catnum'] = $regs[0];
                                $year_row['comment'] = trim(str_replace($year_row['catnum'],"",$year_row['comment']));
                            } else {
                                $year_row['catnum'] = null;
                            }
                            
                            // get alt year
                            if (preg_match('/\(([0-9]+)\)/six', $year_row['year'], $regs)) {
                                $year_row['altyear'] = $regs[1];
                                $year_row['year'] = trim(str_replace("(".$year_row['altyear'].")","",$year_row['year']));
                            } else {
                                $year_row['altyear'] = null;
                            }
                            
                            // try to find Mint Mark
                            $year_row['mintmark'] = (preg_replace('/^[0-9]+/six', '', $year_row['year']));
                            
                            //$year_row['mintmark'] = decode_entities($year_row['mintmark']); 
                            $year_row['year'] = trim(str_replace($year_row['mintmark'],"",$year_row['year']));

                            
                            if($year_row['year']){
                                $arYears[] = $year_row;    
                                $newYearID = $DB->query('INSERT IGNORE INTO year(?#) VALUES(?a)',array_keys($year_row), array_values($year_row));    
                            } 
                            
                        }
                    }
                    
                    print_r($arYears);
                    
                }
            }
            echo "</pre>";
        }
    }
    else{
        // Покажем последнюю ошибку
        echo $http->error();
    }
}
?>
</body>
</html>

