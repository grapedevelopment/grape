<?php
/**
 *
 */

// no direct call
if(!defined('GRAPE')) die('Direct access not permitted');

$grape->user = false;
if($grape->auth->isAuthenticated){
	grape_load_current_user();
}
/**
 *
 */
function grape_load_current_user(){
	global $grape;
	$sql = "SELECT 	`grape_users`.*,
					`grape_organization_units`.`name` AS ou_name,
					`grape_organization_unit_types`.`name` AS ou_type_name,
					`grape_communes`.`name` AS commune_name
			FROM `grape_users`
			LEFT JOIN `grape_organization_units` ON `grape_organization_units`.`ou_id` = `grape_users`.`ou_id`
			LEFT JOIN `grape_organization_unit_types` ON `grape_organization_unit_types`.`ou_type_id` = `grape_organization_units`.`ou_type_id`
			LEFT JOIN `grape_communes` ON `grape_communes`.`commune_id` = `grape_users`.`commune_id`
			WHERE `grape_users`.`username` = '".$grape->auth->username."'
			AND `grape_users`.`auth_id` = ".$grape->auth->auth_id;
	$grape->db->query($sql);
	if($grape->db->num_rows == 1){
		$db_results = $grape->db->get_results();
		$grape->user = $db_results[0];
	}
	elseif($grape->db->num_rows == 0){
		$grape->user = new stdClass();
		$grape->user->username = $grape->auth->username;
		$grape->user->auth_id = $grape->auth->auth_id;
		$auth_method = $grape->auth->get_auth_method_by_auth_id($grape->auth->auth_id);
		$grape->user->trusted = $auth_method->autotrust;
	}
	else{
		echo "Error: mehrere Benutzer*innendatensätze!";
		echo $sql;
		exit;
	}
	grape_user_update_last_login();
	$grape->output->users_online = grape_users_online();
}
/**
 *
 */
function grape_user_update_last_login(){
	global $grape;
	$sql = "UPDATE `grape_users` SET `last_login` = NOW() WHERE `user_id` = ".$grape->user->user_id;
	$grape->db->query($sql);
}
/**
 * @param string $user_capability
 * @param string $needed_capability
 * @return bool
 */
function grape_user_has_capability($user_capability,$needed_capability){
	if($user_capability == $needed_capability){
		return true;
	}
	elseif($user_capability == "admin"){
		return true;
	}
	elseif($needed_capability == "admin"){
		return false;
	}
	elseif($needed_capability == "editor" && $user_capability == "editor"){
		return true;
	}
	elseif($needed_capability == "user" && $user_capability != "none"){
		return true;
	}
	elseif($needed_capability == "none"){
		return true;
	}
	else{
		return false;
	}
}
/**
 * Check whether user is admin
 * @param int $user_id
 * @return bool True if admin
 */
function grape_user_is_admin($user_id = false){
	global $grape;
	if($user_id === false){
		$user_id = $grape->user->user_id;
	}
	$user_id = intval($user_id);
	$sql = "SELECT *
			FROM `grape_capabilities`
			WHERE `power` = 'admin'
			AND `user_id` = $user_id";
	$grape->db->query($sql);
	if($grape->db->num_rows > 0){
		return true;
	}
	else{
		return false;
	}
}
/**
 * looks for capability in every leaf of ou tree under given ou
 * @param int $ou_id
 * @param bool $start
 * @param string $capability
 * @return string Capability
 */
function grape_get_biggest_capability_within_ou($ou_id,$start=true,$capability="none"){
	global $grape;
	$ou_id = intval($ou_id);
	if($start === true){
		//$grape->html_output->content.= "<p>Start ou_id: ".$ou_id."</p>";
		// going up to the trunk
		$capability = grape_get_capability_of_current_user_for_ou($ou_id);
		//$grape->html_output->content.= "<p>trunk capability: ".$capability."</p>";
	}
	$new_capability = grape_get_capability_for_ou($ou_id,$grape->user->user_id);
	//$grape->html_output->content.= "<p>Old capability: ".$capability." Checking ".$ou_id.": ".$new_capability."</p>";
	$capability = grape_get_bigger_capability($new_capability,$capability);
	if($capability == "admin"){
		// there's nothing bigger than admin
		return $capability;
	}
	else{
		// going down to the leafs
		//$grape->html_output->content.= "<p>going down to the leafs of ou_id $ou_id</p>";
		$child_ous = grape_get_child_organization_units($ou_id);
		//$grape->html_output->content.= "<pre>".print_r($child_ous,true)."</pre>";
		foreach($child_ous as $child_ou){
			$new_capability = grape_get_biggest_capability_within_ou($child_ou->ou_id,false,$capability);
			//$grape->html_output->content.= "<p>Old capability: ".$capability." Checking ".$child_ou->ou_id.": ".$new_capability."</p>";
			$capability = grape_get_bigger_capability($new_capability,$capability);
		}
		return $capability;
	}
}
/**
 * @param int $ou_id
 */
function grape_get_capability_of_current_user_for_ou($ou_id){
	global $grape;
	$ou_id = intval($ou_id);
	$capability = "none";
	if(isset($grape->user) && isset($grape->user->trusted) && $grape->user->trusted == 1){
		$capability = "user";
	}
	//$grape->output->content->html.= $grape->output->dump_var("checking capability for user_id ".$grape->user->user_id." for ou ".$ou_id);
	$capability = grape_get_capability_for_ou_recursive($ou_id,$grape->user->user_id,$capability);
	//$grape->output->content->html.= $grape->output->dump_var("user_id ".$grape->user->user_id." has ".$capability." capability for ou ".$ou_id);
	return $capability;
}
/**
 *
 */
function grape_get_capability_of_current_user_for_district($district_id,$election_id){
	global $grape;
	$capability = "none";
	$district_id = intval($district_id);
	$election_id = intval($election_id);
	$sql = "SELECT `organization_unit_id`
			FROM `grape_x_elections_electoral_districts`
			WHERE `electoral_district_id` = $district_id
			AND `election_id` = $election_id";
	$grape->db->query($sql);
	if($grape->db->num_rows == 1){
		$db_results = $grape->db->get_results();
		$organization_unit_id = $db_results[0]->organization_unit_id;
		$capability = grape_get_capability_of_current_user_for_ou($organization_unit_id);
	}
	return $capability;
}
/**
 * @param int $ou_id
 * @param int $user_id
 * @param string $capability ; values: admin, editor, user, none
 */
function grape_get_capability_for_ou_recursive($ou_id,$user_id,$capability="none"){
	global $grape;
	$ou_id = intval($ou_id);
	$user_id = intval($user_id);
	//$grape->output->content->html.= $grape->output->dump_var("recursion temp capability '$capability' for ou $ou_id");
	// get capability for this ou
	$new_capability = grape_get_capability_for_ou($ou_id,$user_id);
	// compare with existing capability
	$capability = grape_get_bigger_capability($new_capability,$capability);
	//$grape->output->content->html.= $grape->output->dump_var("new capability is '$capability' for ou $ou_id");
	// go up one step
	$parent_ou_id = grape_get_parent_organization_unit($ou_id);
	if($parent_ou_id!==false){
		// get capability for parent ou
		$parent_capability = grape_get_capability_for_ou_recursive($parent_ou_id,$user_id,$capability);
		// compare with existing capability
		$capability = grape_get_bigger_capability($parent_capability,$capability);
	}
	return $capability;
}
/**
 * @param int $ou_id
 * @param int $user_id
 * @return string Capability
 */
function grape_get_capability_for_ou($ou_id,$user_id){
	global $grape;
	$ou_id = intval($ou_id);
	$user_id = intval($user_id);
	$capability = "none";
	$sql = "SELECT *
			FROM `grape_capabilities`
			WHERE `user_id` = $user_id
			AND `context_id` = $ou_id
			AND `context` = 'ou'";
	$grape->db->query($sql);
	if($grape->db->num_rows == 1){
		$db_results = $grape->db->get_results();
		$capability = $db_results[0]->power;
	}
	return $capability;
}
/**
 *
 */
function grape_get_bigger_capability($capa1,$capa2){
	if($capa1 == "admin" || $capa2 == "admin"){
		return "admin";
	}
	elseif($capa1 == "editor" || $capa2 == "editor"){
		return "editor";
	}
	elseif($capa1 == "user" || $capa2 == "user"){
		return "user";
	}
	else{
		return "none";
	}
}
/**
 * @todo Implement $region_id; 
 * @todo replace region_id
 * */
function get_users($exclude,$region_id=false){
	$sql = "SELECT *
			FROM `gruene`.`users`
			".(($exclude)?"WHERE `username` <> '".preg_replace('/[^a-zA-Z]+/', "", $exclude)."' ":"")."
			ORDER BY `name`";
	//echo $sql;
	$result = db_select($sql);
	return($result);
}
/**
 * 
 */
function grape_get_user_by_id($user_id){
	global $grape;
	$user_id = intval($user_id);
	$user = false;
	$sql = "SELECT 	`grape_users`.*,
					`grape_organization_units`.`name` AS ou_name,
					`grape_communes`.`name` AS commune_name
			FROM `grape_users`
			LEFT JOIN `grape_organization_units` ON `grape_organization_units`.`ou_id` = `grape_users`.`ou_id`
			LEFT JOIN `grape_communes` ON `grape_communes`.`commune_id` = `grape_users`.`commune_id`
			WHERE `grape_users`.`user_id` = $user_id";
	$grape->db->query($sql);
	if($grape->db->num_rows == 1){
		$db_results = $grape->db->get_results();
		$user = $db_results[0];
	}
	return $user;
}
/**
 * @param int $child_id
 * @return int or false
 */
function grape_get_parent_organization_unit($child_id){
	global $grape;
	$child_id = intval($child_id);
	$sql = "SELECT `parent_ou_id`
			FROM `grape_organization_units`
			WHERE `ou_id` = $child_id
			AND `parent_ou_id` IS NOT NULL";
	$grape->db->query($sql);
	if($grape->db->num_rows == 1){
		$db_results = $grape->db->get_results();
		return $db_results[0]->parent_ou_id;
	}
	else{
		return false;
	}
}
/**
 * @param int $parent_id
 * @return array
 */
function grape_get_child_organization_units($parent_id){
	global $grape;
	$parent_id = intval($parent_id);
	$sql = "SELECT `ou_id`
			FROM `grape_organization_units`
			WHERE `parent_ou_id` = $parent_id";
	//$grape->html_output->content.= "<p>$sql</p>";
	$grape->db->query($sql);
	if($grape->db->num_rows > 0){
		$db_results = $grape->db->get_results();
		return $db_results;
	}
	else{
		return array();
	}
}
/**
 * 
 */
function grape_get_communes($parent_id=0){
	global $grape;
	/*$parent_id = intval($parent_id);
	if($parent_id==0){
		$sql = "SELECT *
				FROM `grape_organization_units`
				WHERE `parent_ou_id` IS NULL
				ORDER BY `name`";
	}
	else{
		$sql = "SELECT *
				FROM `grape_organization_units`
				WHERE `parent_ou_id` = $parent_id
				ORDER BY `name`";
	}*/
	$sql = "SELECT * FROM `grape_communes` ORDER BY `name`";
	$grape->db->query($sql);
	$db_results = $grape->db->get_results();
	return $db_results;
	/*
	if($grape->db->num_rows > 0){
		$db_results = $grape->db->get_results();
		$return_stuff = array();
		foreach($db_results as $result){
			array_push($return_stuff,$result);
			$subresults = grape_get_organization_units($result->ou_id);
			if($subresults){
				foreach($subresults as $subresult){
					array_push($return_stuff,$subresult);
				}
			}
		}
	}
	return $return_stuff;
	*/
}
/**
 * 
 */
function grape_get_organization_units($parent_id=0){
	global $grape;
	$return_stuff = false;
	$parent_id = intval($parent_id);
	if($parent_id==0){
		$sql = "SELECT `grape_organization_units`.*,
				`grape_organization_unit_types`.`name` AS type
				FROM `grape_organization_units`
				LEFT JOIN `grape_organization_unit_types` ON `grape_organization_unit_types`.`ou_type_id` = `grape_organization_units`.`ou_type_id`
				WHERE `grape_organization_units`.`parent_ou_id` IS NULL
				ORDER BY `grape_organization_units`.`name`";
	}
	else{
		$sql = "SELECT `grape_organization_units`.*,
				`grape_organization_unit_types`.`name` AS type
				FROM `grape_organization_units`
				LEFT JOIN `grape_organization_unit_types` ON `grape_organization_unit_types`.`ou_type_id` = `grape_organization_units`.`ou_type_id`
				WHERE `grape_organization_units`.`parent_ou_id` = $parent_id
				ORDER BY `grape_organization_units`.`name`";
	}
	$grape->db->query($sql);
	if($grape->db->num_rows > 0){
		$db_results = $grape->db->get_results();
		$return_stuff = array();
		foreach($db_results as $result){
			array_push($return_stuff,$result);
			$subresults = grape_get_organization_units($result->ou_id);
			if($subresults){
				foreach($subresults as $subresult){
					array_push($return_stuff,$subresult);
				}
			}
		}
	}
	return $return_stuff;
}
/**
 * Build a HTML edit form for user data
 * @return string
 */
function grape_userdata_edit(){
	global $grape;
	$user = $grape->user;
	if(!$grape->user){
		$html .= '<p>Hallo '.$grape->auth->username.',<br/>bevor es losgeht, brauchen wir noch ein/zwei Auskünfte von '.((SALUTATION=="Sie")?"Ihnen":"Dir").'</p>';
	}
	else{
		$html .=  '<p>Hier können '.((SALUTATION=="Sie")?"können Sie Ihre":"kannst Du Deine").' persönlichen Daten bearbeiten.</p>';
	}
	//$html.= "<pre>".print_r(grape_get_organization_units(),true)."</pre>";
	//$html.= $grape->output->dump_var($grape->user);
	$html.= '
				<form method="post">
					<h2>'.((SALUTATION=="Sie")?"Ihre":"Deine").' Daten</h2>
					<input type="hidden" name="job" value="userdata_save"/>
					<div class="row">
					  <div class="col-md-6 mb-3">
						<label for="first_name">'.((SALUTATION=="Sie")?"Ihr":"Dein").' Vorname</label>
						<input type="text" class="form-control" id="first_name" name="first_name" placeholder="" value="'.($user?$user->name:"").'" required=""/>
						<div class="invalid-feedback">
						  Valid first name is required.
						</div>
					  </div>
					  <div class="col-md-6 mb-3">
						<label for="last_name">'.((SALUTATION=="Sie")?"Ihr":"Dein").' Nachname</label>
						<input type="text" class="form-control" id="last_name" name="last_name" placeholder="" value="'.($user?$user->last_name:"").'" required="">
						<div class="invalid-feedback">
						  Valid last name is required.
						</div>
					  </div>
					</div>
					<div class="form-group">
						<label for="email">'.((SALUTATION=="Sie")?"Ihre":"Deine").' E-Mail-Adresse</label>
						<input type="email" class="form-control" id="email" name="email" placeholder="'.((SALUTATION=="Sie")?"Ihre":"Deine").' E-Mail-Adresse" value="'.($user?$user->email:"").'"/>
					</div>
					<!--<h4>'.((SALUTATION=="Sie")?"Ihr":"Dein").' Geschlecht</h4>
					<div class="d-block my-3">
					  <div class="custom-control custom-radio">
						<input id="female" name="gender" type="radio" class="custom-control-input" value="female" '.($grape->user&&$grape->user->gender=="female"?"checked=\"\"":"").' required="">
						<label class="custom-control-label" for="female">Frau</label>
					  </div>
					  <div class="custom-control custom-radio">
						<input id="male" name="gender" type="radio" class="custom-control-input" value="male" '.($grape->user&&$grape->user->gender=="male"?"checked=\"\"":"").' required="">
						<label class="custom-control-label" for="male">Mann</label>
					  </div>
					  <div class="custom-control custom-radio">
						<input id="divers" name="gender" type="radio" class="custom-control-input" value="divers" '.($grape->user&&$grape->user->gender=="divers"?"checked=\"\"":"").' required="">
						<label class="custom-control-label" for="divers">divers</label>
					  </div>
					</div>-->
					<h4>'.((SALUTATION=="Sie")?"Ihre":"Deine").' Gruppe</h4>
					<label for="organization_unit_id">Wähle'.((SALUTATION=="Sie")?"n Sie Ihre":" Deine").' Gruppe:</label>
					<select name="organization_unit_id" id="organization_unit_id">';
					$html.= grape_organization_unit_select(grape_get_organization_units(),$user->ou_id,"none");
					$html.= '
					</select>
					<h4>'.((SALUTATION=="Sie")?"Ihre":"Deine").' Kommune</h4>
					<label for="commune_id">Wähle'.((SALUTATION=="Sie")?"n Sie Ihre":" Deine").' Kommune:</label>
					<select name="commune_id" id="commune_id">';
					$html.= grape_commune_select(grape_get_communes(),$user->commune_id,"none");
					$html.= '
					</select>
					<div class="btn-group btn-block special" role="group">
						<a href="#" onclick="load_content();" class="btn btn-secondary" role="button">Abbrechen</a>
						<button class="btn btn-primary">Speichern</button>
					</div>
				</form>
		';
	return $html;
}
/**
 * Build a HTML select for organization units
 * @param array $organization_units
 * @param int $selected_ou_id
 * @param string $needed_capability
 * @return string HTML optgroups and options
 */
function grape_organization_unit_select($organization_units,$selected_ou_id,$needed_capability="user"){
	global $grape;
	$html = '';
	$last_ou_type_id = 1;
	//print_r($organization_units);
	//$grape->html_output->content.= $needed_capability;
	foreach($organization_units as $item){
		//$grape->html_output->content.= grape_get_capability_of_current_user_for_ou($item->ou_id);
		if(grape_user_has_capability(grape_get_capability_of_current_user_for_ou($item->ou_id),$needed_capability)){
			if($item->ou_type_id == 3) {
				//if($last_ou_type_id > $item->ou_type_id) $html.= '</optgroup>';
				//$html.= '<optgroup label="'.$item->name.'">';
				$html.= '<option value="'.$item->ou_id.'"'.(($item->ou_id==$selected_ou_id)?' selected="selected"':'').'>'.$item->name.' ('.$item->type.')</option>';
			}
			elseif($item->ou_type_id > 3){
				$html.= '<option value="'.$item->ou_id.'"'.(($item->ou_id==$selected_ou_id)?' selected="selected"':'').'>&nbsp;&nbsp;&nbsp;'.$item->name.' ('.$item->type.')</option>';
			}
		}
	}
	return $html;
}
/**
 * Build a HTML select for organization units
 * @param array $organization_units
 * @param int $selected_ou_id
 * @param string $needed_capability
 * @return string HTML optgroups and options
 */
function grape_commune_select($communes,$selected_commune_id){
	global $grape;
	$html = '';
	$last_ou_type_id = 1;
	//print_r($organization_units);
	//$grape->html_output->content.= $needed_capability;
	foreach($communes as $item){
		//$grape->html_output->content.= grape_get_capability_of_current_user_for_ou($item->ou_id);
		//if(grape_user_has_capability(grape_get_capability_of_current_user_for_ou($item->ou_id),$needed_capability)){
			//if($item->ou_type_id == 3) {
			//	if($last_ou_type_id > $item->ou_type_id) $html.= '</optgroup>';
			//	$html.= '<optgroup label="'.$item->name.'">';
			//}
			//elseif($item->ou_type_id == 4){
				$html.= '<option value="'.$item->commune_id.'"'.(($item->commune_id==$selected_commune_id)?' selected="selected"':'').'>'.$item->name.'</option>';
			//}
		//}
	}
	return $html;
}
/**
 * Wrapper to be triggered from auth.php
 */
function grape_userdata_save_from_auth($username=false,$auth_id=false,$trusted=false){
	global $grape;
	if($username&&$auth_id&&$trusted){
		$grape->user->username = $username;
		$grape->user->auth_id  = $auth_id;
		$grape->user->trusted = $trusted;
		$return = grape_userdata_save();
		grape_accept_terms(intval($_REQUEST["term_id"]));
		return $return;
	}
	else{
		return false;
	}
}
/**
 * Read user data from request to $grape->user and save to database
 * @return bool True on success
 */
function grape_userdata_save(){
	global $grape;
	//print_r($_REQUEST);
	$name = preg_replace('/[^a-zA-Z\Ä\Ö\Ü\ä\ö\ü\ß\é\ \-]+/', "", $_REQUEST["first_name"]);
	$last_name = preg_replace('/[^a-zA-Z\Ä\Ö\Ü\ä\ö\ü\ß\é\ \-]+/', "", $_REQUEST["last_name"]);
	$gender = preg_replace('/[^a-z]+/', "", $_REQUEST["gender"]);
	$organization_unit_id = intval($_REQUEST["organization_unit_id"]);
	$commune_id = intval($_REQUEST["commune_id"]);
	$email = $_REQUEST["email"];
	if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		echo "error";
		return false;
	}
	else{
		$grape->user->name = $name;
		$grape->user->last_name = $last_name;
		$grape->user->gender = $gender;
		$grape->user->ou_id = $organization_unit_id;
		$grape->user->commune_id = $commune_id;
		$grape->user->email = $email;
		
		$sql = "INSERT INTO `grape_users`
				(
					`username`,
					`auth_id`,
					`name`,
					`last_name`,
					`email`,
					`gender`,
					`ou_id`,
					`commune_id`,
					`trusted`,
					`first_login`
				)
				VALUES
				(
					'".$grape->user->username."',
					 ".$grape->user->auth_id.",
					'".$grape->user->name."',
					'".$grape->user->last_name."',
					'".$grape->user->email."',
					'".$grape->user->gender."',
					 ".$grape->user->ou_id.",
					 ".$grape->user->commune_id.",
					 ".$grape->user->trusted.",
					 NOW()
				)
				ON DUPLICATE KEY UPDATE
					`name` = '".$grape->user->name."',
					`last_name` = '".$grape->user->last_name."',
					`email`='".$grape->user->email."',
					`gender` = '".$grape->user->gender."',
					`ou_id` = ".$grape->user->ou_id.",
					`commune_id` = ".$grape->user->commune_id.";";
		//$grape->output->content->html.= $grape->output->dump_var($sql);
		$grape->db->query($sql);
		$grape->user->user_id = $grape->db->insert_id;
		return true;
	}
}
/**
 *
 */
function grape_get_users_by_ou($ou_id){
	global $grape;
	$sql = "SELECT * FROM `grape_users` WHERE `ou_id` = $ou_id";
	//$grape->output->content->html.= $grape->output->dump_var($sql);
	$grape->db->query($sql);
	if($grape->db->num_rows > 0){
		$db_results = $grape->db->get_results();
		return $db_results;
	}
	else{
		return array();
	}
}
/**
 *
 */
function grape_users_online(){
	global $grape;
	$sql = "SELECT COUNT(`user_id`) AS users_online FROM `grape_users` WHERE `last_login` > NOW() - INTERVAL 30 MINUTE";
	$grape->db->query($sql);
	$db_results = $grape->db->get_results();
	$users_online = $db_results[0]->users_online;
	return $users_online;
}
?>