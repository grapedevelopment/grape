<?php
	/**********************************************************************
	*  ezSQL initialisation for mySQL
	*/
	// Include ezSQL core
	include_once "external-libraries/ezSQL/shared/ez_sql_core.php";
	// Include ezSQL database specific component
	include_once "external-libraries/ezSQL/mysqli/ez_sql_mysqli.php";
	// Initialise database object and establish a connection
	// at the same time - db_user / db_password / db_name / db_host
	
	class sql_caching extends ezSQL_mysqli
	{
		/**
		 * @param string $sql
		 * @param int $max_age Minutes
		 */
		public function cache_query($sql,$max_age=0)
		{
			$cache_query = "SELECT * FROM `grape_caches` WHERE `cache_key` = \"$sql\" AND `timestamp` > DATE_ADD(NOW(), INTERVAL $max_age MINUTE)";
			//echo $cache_query;
			//exit;
			$this->query($cache_sql);
			if($this->num_rows > 0){
				$results = $this->get_results();
				return unserialize($results[0]->result);
			}
			else{
				$result = $this->query($sql);
				$serialized = serialize($result);
				$cache_sql = "SELECT * FROM `grape_caches` WHERE `cache_key` = \"$sql\"";
				$this->query($cache_sql);
				if($this->num_rows > 0){
					$sql = "UPDATE `grape_caches` SET `result` = \"$serialized\" WHERE `cache_key` = \"$sql\"";
				}
				else{
					$sql = "INSERT `grape_caches` (`cache_key`,`result`) VALUES(\"$sql\",\"$serialized\")";
				}
				//echo $sql;
				//exit;
				$this->query($sql);
				return $result;
			}
		}
	}
	
?>