<?php   

    function decodeUnicode($s, $output = 'utf-8'){
        return preg_replace_callback('#\\\\u([a-fA-F0-9]{4})#', function ($m) use ($output) {
            return iconv('ucs-2be', $output, pack('H*', $m[1]));
        }, $s);
    }

    require( $_SERVER['DOCUMENT_ROOT'].'/XAMPPparser/class/config.php');

    $ch = curl_init('https://etp.eltox.ru/registry/procedure?id=&procedure=&oos_id=&company=&inn=&type=1&price_from=&price_to=&published_from=&published_to=&offer_from=&offer_to=&status=');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $html = curl_exec($ch);
    curl_close($ch);

    $htmlParsed = str_get_html($html);

    $array = array();

    foreach ($htmlParsed->find('div.procedure-list > div.procedure-list-item') as $k=>$v){
        $z++;
        $documents = array();
        $docs_name_array = array();
        $docs_name_array_final = array();
        $docs_path_array = array();
        $docs_path_array_final = array();
        
        $id = trim($v->find('.descriptTenderTd', 0)->find('a', 0)->plaintext);
        $ooc = trim($v->find('.descriptTenderTd', 0)->find('span', 0)->plaintext);
        $link = "https://etp.eltox.ru".$v->find('.descriptTenderTd', 0)->find('a', 0)->href;
        
        $ch = curl_init($link);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $html = curl_exec($ch);
        curl_close($ch);

        $htmlParsedCard = str_get_html($html);
        
        foreach ($htmlParsedCard->find('.detail-view', 0)->find('tr') as $k2=>$v2){
            if (trim($v2->find('th', 0)->plaintext) == "Почта"){
                $email = trim($v2->find('td', 0)->plaintext);
            }
        }
        
        $html = decodeUnicode($html);
        preg_match_all('/"name":"([a-zA-Zа-яА-Я0-9\s\.\-\_\№]*)"/imu', $html, $docs_name_array);
        foreach ($docs_name_array[1] as $k2=>$v2){
            $docs_name_array_final[] = $v2;
        }
        preg_match_all('/"path":"([a-zA-Zа-яА-Я0-9\s\.\-\_\№]*)"/imu', $html, $docs_path_array);
        foreach ($docs_path_array[1] as $k2=>$v2){
            $docs_path_array_final[] = $v2;
        }
        foreach ($docs_name_array_final as $k2=>$v2){
            $ar = explode("_", $v2);
            $documents[] = array(
                 'name' => $ar[1]
                ,'path' => "https://storage.eltox.ru/".$docs_path_array_final[$k2]."/".$v2
            );
        }
        
        
        $array[] = array(
             'id' => preg_replace('/[^0-9]/', '', $id)
            ,'ooc' => preg_replace('/[^0-9]/', '', $ooc)
            ,'link' => $link
            ,'email' => $email
            ,'documents' => $documents
        );
        
    }

    db::query('DELETE FROM `procedures` WHERE `id`>0');

    foreach ($array as $k=>$v){
        db::insert(
            'INSERT INTO `procedures` (`p_id`, `ooc`, `link`, `email`, `documents`) VALUES(?,?,?,?,?)'
            ,$v['id']
            ,$v['ooc']
            ,$v['link']
            ,$v['email']
            ,serialize($v['documents'])
        );
    }

    echo "<pre>";
    print_r($array);
    echo "</pre>";

?>