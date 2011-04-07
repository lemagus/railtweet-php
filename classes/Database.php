<?php

	class Database {

		//
		private $configurations = array(
			"prod" => array(
				"host" 		=> "localhost",
				"login"		=> "railtweet",
				"password"	=> "",
				"dbname"	=> "railtweet"
			)
		);
		
		protected $link = null;
		
		// -->
		
		static private $addColumnMode = FALSE;
		static public function setAddColumnMode($bol){
			self::$addColumnMode = $bol;
		}
		
		// -->
		
		public $isConnected = false;
				
		public function __construct($env = "prod"){
			$this->isConnected = $this->connect($env);
		}
		
		private function connect($env){
			$this->link = mysql_connect($this->configurations[$env]["host"], $this->configurations[$env]["login"], $this->configurations[$env]["password"]);
			if (!$this->link) return false;
			$db_selected = mysql_select_db($this->configurations[$env]["dbname"], $this->link);
			if (!$db_selected) return false;
			return true;
		}
		
		private function createColumns($table,$datas){
		
			$defaultType = "VARCHAR( 255 )";
			
			$cols = array();
			$rst = $this->query("SHOW COLUMNS FROM ".$table);
			while($row = mysql_fetch_row($rst)){
				$cols[] = $row[0];
			}
			
			$type = $defaultType;
			
			$keys = array_keys($datas);
			foreach($keys as $column){
				if(!in_array($column,$cols)){
					$sql = "ALTER TABLE `".$table."` ADD `".$column."` ".$type." NOT NULL";
					$this->query($sql);
				}
			}
			
			
		}
		
		
		// 
		
		public function query($sql){
			return mysql_query($sql);
		}
		
		public function insert($table,$datas,$checkForDuplicate = false){
			if(self::$addColumnMode) $this->createColumns($table,$datas);
			
			if($checkForDuplicate){
				$sql = "SELECT `id` FROM `".$table."` WHERE ";
				foreach($datas as $k=>$v){
					$sql .= "`".$k."` = ".$this->quote($v)." AND";
				}
				if(!empty($datas)) $sql = substr($sql,0,-3);
				$id = $this->fetchOne($sql);
				if(!empty($id)) return $id;
			}			
			
			$sql = "INSERT INTO `".$table."` SET ";
			foreach($datas as $k=>$v){
				$sql .= "`".$k."`=". $this->quote($v).", ";
			}	
			$sql = substr($sql, 0, -2);
			$this->query($sql);
			return $this->getLastInsertID();		
		}
		
		public function update($table,$datas,$where){
			if(self::$addColumnMode) $this->createColumns($table,$datas);
			$sql = "UPDATE `".$table."` SET ";
			foreach($datas as $k=>$v){
				$sql .= "`".$k."`=". $this->quote($v).", ";
			}	
			$sql  = substr($sql, 0, -2);
			$sql .= " WHERE ".$where;
			$this->query($sql);
			return $this->getLastInsertID();
		}
		
		public function delete($table,$where){
			$sql = "DELETE FROM `".$table."` WHERE ".$where;
			return $this->query($sql);
		}
		
		public function fetchOne($sql){
			$row = $this->fetchRow($sql);
			if(!$row) return false;
			return current($row);
		}
		
		public function fetchCol($sql){
			$outputs = array();
			$datas = $this->fetchAll($sql);
			foreach($datas as $data){
				$outputs[] = current($data);
			}
			return $outputs;
		}
		
		public function fetchRow($sql){
			$datas = $this->fetchAll($sql);
			return $datas[0];
		}
		
		public function fetchPairs($sql){
			$outputs = array();
			$datas = $this->fetchAll($sql);
			foreach($datas as $data){
			
				$key = current($data);
				next($data);
				$value = current($data);
				
				$outputs[$key] = $value;
			}
			return $outputs;
		}
		
		public function fetchAll($sql){
			$datas = array();
			$results = $this->query($sql);
			if(!$results) return $datas;
			while($arr = mysql_fetch_assoc($results)){
				$obj = new stdClass();
				foreach($arr as $k => $v){
					$obj->$k = $v;
				}
				$datas[] = $obj;
			}
			return $datas;
		}
		
		public function quote($expr){
			return "'".mysql_real_escape_string($expr)."'";
		}
	
		public function getLastInsertID(){
			$link = !is_null($this->link) ? $this->link : null;
			return $link ? mysql_insert_id($link) : mysql_insert_id() ;
		}
	
	}