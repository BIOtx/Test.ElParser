<?php

class db
{

	private static $conn, $sql = array();
	public static $query = array();
    public static $sort = array();
    public static $plus = "";

	public static function conn($username, $pass, $host, $db_name, $encoding)
	{

		self::$conn = mysqli_connect($host, $username, $pass, $db_name);
        
        mysqli_query(self::$conn, "SET NAMES 'utf8mb4';");
        mysqli_query(self::$conn, "SET CHARACTER SET 'utf8mb4';");
        mysqli_query(self::$conn, "SET SESSION collation_connection = 'utf8mb4_general_ci';");

		if (!self::$conn) {
            exit('Error connect database!');
		}
            
	}
    
	private static function _getQuery($query)
	{
		$sql = call_user_func_array(array("db", "_getSql"), $query);
		$result = mysqli_query(self::$conn, $sql);
		self::$sql[] = $sql.' -> '.mysqli_error(self::$conn);
        if (is_string($result)) { 
            return $result;
        }
        if ($result instanceof mysqli_result) {
            $rows = array();
            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
                $rows[] = $row;
            }
            return $rows;
        }
	}
    
	private static function _getSql()
	{
		$args = func_get_args();
		$tmpl = &$args[0];
		$tmpl = str_replace( "%", "%%", $tmpl);
		$tmpl = str_replace( "?", "%s", $tmpl);
		foreach ($args as $key => $value) {
			if (!$key) continue;
			if (!is_array($value)) {
				$args[$key] = "'".mysqli_real_escape_string(self::$conn, $value)."'";
			} else {
				$parts = array();
				foreach ($value as $k=>$v) {
                	if (!is_int($k)) {
                        $parts[] = "`$k`="."'".mysqli_real_escape_string(self::$conn, $v)."'";
                    } else {
                    	$parts[] = $v;
                    }
                }
				$args[$key] = join(', ', $parts);
			}
		}
		return call_user_func_array("sprintf", $args);
	}


	public static function select()
	{
		$args = func_get_args();
		$rows = self::_getQuery($args);
        if ($rows == ""){
            return array();
        }
		return $rows;
	}

    
	public static function selectRow()
	{
		$args = func_get_args();
		$rows = self::_getQuery($args);
        /*
            $rows = array();
            while ($row = mysqli_fetch_array($rows1, MYSQLI_ASSOC)){
                $rows[] = $row;
            }
            reset($rows);
            return current($rows);
        */
		if (!is_array($rows)) return $rows;
        if (!count($rows)) return array();
        reset($rows);
        return current($rows);
	}
    
	public static function selectCell()
	{
		$args = func_get_args();
		$rows = self::_getQuery($args);
		if (!is_array($rows)) return $rows;
		if (!count($rows)) return null;
		reset($rows);
        $row = current($rows);
        if (!is_array($row)) return $row;
        reset($row);
        return current($row);
	}
    
	public static function selectColl()
	{
		$args = func_get_args();
		$rows = self::_getQuery($args);
		if (!is_array($rows)) return $rows;
		self::_ArrayDimension($rows);
		return $rows;
	}
    
	public static function query()
	{
		$args = func_get_args();
		$result = self::_getQuery($args);
		if ($result === false) return false;
		return mysqli_affected_rows(self::$conn);
	}
    
	public static function insert()
	{
		$args = func_get_args();
		$result = self::_getQuery($args);
		if ($result === false) return false;
		return mysqli_insert_id(self::$conn);
	}
    
	private static function _ArrayDimension(&$v)
    {
        if (!$v) return;
        reset($v);
        if (!is_array($firstCell = current($v))) {
            $v = $firstCell;
        } else {
            array_walk($v, array(self, '_ArrayDimension'));
        }
    }
    
	public static function debug()
    {
        ob_start();
		echo '<pre>';
		print_r(self::$sql);
		echo '</pre>';
		$o=ob_get_clean();
		return $o;
    }
    
	public static function prepare($val){
		return is_array($val) ? array_map("db::prepare", $val) : trim(htmlspecialchars(stripslashes($val)));
	}
    
	public static function toDate($date){
        if (empty($date))
            return;
		return date('Y-m-d', strtotime($date));
	}
    
	public static function getDate($date){
        if (empty($date))
            return;
		return date('d.m.Y', strtotime($date));
	}
    
	public static function close()
    {
       mysqli_close(self::$conn);
    }
    
    public static function filter($name, $val, $operator = '=', $and = false) {
        
        /*
        
            $val      - value
            $name     - column name
            $operator - = < > <= >= != LIKE
            $and      - true/false

            Example:
            
                filter('column', array(1,2,3))             =   column='1' OR column='2' OR column='3'

                filter('column', '%search str%', 'LIKE')   =   `column` LIKE '%search string%'

                filter('room', $_GET['val1'])
                filter('room', $_GET['val2'])              =    `room` = '$_GET['val1']' OR `room` = '$_GET['val2']'

                filter('display',1)                        =    `display`='1'

                filter('display',$_GET['val'])             =    if !isset($_GET['val']) return ''

                filter('date',$_GET['val'],'>=')           =    `date` >= $_GET['val']

                filter('date',$_GET['start'],'>=')
                filter('date',$_GET['end'],'<=',true)      =    `date` >= $_GET['start'] AND `date`<= $_GET['end']
                
        */

        if (isset($val) && $val != '') {

            if (is_array($val)) {

                $array = array();
                foreach ( $val as $k=>$v)
                    $array[] = "`".$name."` ".$operator." '".mysqli_real_escape_string(self::$conn, $v)."'";

                $sql = '('.implode(' OR ',$array).')';

            } else {

                $sql = "`".$name."` ".$operator." '".mysqli_real_escape_string(self::$conn, $val)."'";
            }

            self::$query[$name] = (isset(self::$query[$name]))
                ? self::$query[$name]." ".($and?"AND":"OR")." ".$sql
                : $sql
            ;
        }

        return empty(self::$query)
            ? ''
            : ' WHERE '. implode(' AND ', self::$query)
        ;
        
    }
    

    
    public static function _sort() {
        
        $column = func_get_args();
        
        if (in_array($_GET['sort'], $column)){
            $arr['sql'] = ' ORDER BY `'.$_GET['sort'].'` '.(isset($_GET['za']) ? 'DESC' : 'ASC');
            if (self::$plus == 1)
                $arr['sql'] = ', `'.$_GET['sort'].'` '.(isset($_GET['za']) ? 'DESC' : 'ASC');    
        }
        else{
            $arr['sql'] = '';
        }
        
		parse_str($_SERVER['QUERY_STRING'], $url);
        if (!isset($url['za']) || self::$sort[$name[0]] == 'asc')
            $url['za']='';
        else 
            unset($url['za']);    
        
        if (!isset($_GET['sort']) && !empty(self::$sort)) {
            $name = array_keys(self::$sort);
            $arr['sql'] = ' ORDER BY `'.$name[0].'` '.self::$sort[$name[0]];
            
            if (self::$plus == 1)
                $arr['sql'] = ', `'.$name[0].'` '.self::$sort[$name[0]];
            
            if (self::$sort[$name[0]] == 'asc')
                $url['za']='';
            else
                unset($url['za']); 
        }
        
        unset($url['sort']);
        $arr['url'] = '?'.http_build_query($url);
        return $arr;
        
	}    
    
    public static function getFilter() {
        
        return empty(self::$query)
            ? ''
            : ' WHERE '. implode(' AND ', self::$query)
        ;
        
    }
    
}

?>