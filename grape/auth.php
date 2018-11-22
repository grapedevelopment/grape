<?php
/**
 * Management of authentification
 * uses SAML
 */

// no direct call
if(!defined('GRAPE')) die('Direct access not permitted');

$grape->auth = new auth($grape->settings->auth_methods);

class auth{
	var $auth_id = false;
	var $isAuthenticated = false;
	var $username = false;
	var $SAML = false;
	var $auth_methods = false;
	var $db = false;
	/**
	 *
	 */
	function __construct($auth_methods){
		$this->auth_methods = $auth_methods;
		$this->db = $this->database();
		require_once(SAML_PATH);
		$auth = $this->get_login($this->auth_methods);
		if(isset($_REQUEST["job"]) && $_REQUEST["job"] == "logout"){
			$_REQUEST["job"] = "login";
			$auth->SAML->logout(str_replace("index.php","",$_SERVER["SCRIPT_NAME"]));
			$message = "Logout erfolgreich";
		}
		if($auth){
			$this->auth_id = $auth->auth_id;
			$this->SAML = $auth->SAML;
		}
		if($this->SAML!==false) {
			$this->isAuthenticated = true;
			$this->username = $this->get_username_by_auth_id($this->auth_id);
		}
		else{
			if(isset($_REQUEST["job"])&&$_REQUEST["job"]=="login"&&isset($_REQUEST["auth_id"])){
				$this->auth_id = intval($_REQUEST["auth_id"]);
				$auth_method = $this->get_auth_method_by_auth_id($this->auth_id);
				$this->SAML = new SimpleSAML_Auth_Simple($auth_method->code);
				$this->SAML->requireAuth(array(
				   'ReturnTo' => str_replace("index.php","",$_SERVER["SCRIPT_NAME"]),
				   'KeepPost' => FALSE,
				));
				$_REQUEST["job"] = "start";
			}
		}
		//echo "<pre>".print_r($this,true)."</pre>";
	}
	/**
	 *
	 */
	function database(){
		return new sql_caching(AUTH_DB_USER,AUTH_DB_PASSWORD,AUTH_DB_NAME,AUTH_DB_HOST,AUTH_DB_CHARSET);
	}
	/**
	 * @param int $auth_id
	 * @return obj
	 */
	function get_auth_method_by_auth_id($auth_id){
		foreach($this->auth_methods as $method){
			if($method->auth_id == $auth_id ){
				return $method;
			}
		}
		return false;
	}
	/**
	 *
	 */
	public function html_login_button($auth_method=false){
		if($auth_method===false){
			$result = "";
			foreach($this->auth_methods as $auth_method){
				if($auth_method->active == 1){
					$result.= $this->html_login_button($auth_method);
				}
			}
			return $result;
		}
		else{
			return
			'<a href="?job=login&auth_id='.$auth_method->auth_id.'" class="btn btn-primary">Login via '.$auth_method->name.'</a>';
		}
	}
	/**
	 * @param int $auth_id
	 */
	function get_username_by_auth_id($auth_id){
		return $this->SAML->getAttributes()[$this->get_auth_method_by_auth_id($auth_id)->username_in_saml][0];
	}
	/**
	 * Test whether there is an active login
	 * @param object $auth_methods
	 * @return bool or object
	 */
	function get_login($auth_methods){
		for($i=0;$i<count($auth_methods);$i++){
			//echo $domains[$i];
			if($auth_methods[$i]->active == 1){
				$result = $this->test_login($auth_methods[$i]->code);
				if($result!==false){
					$result_wrapper = new stdClass();
					$result_wrapper->auth_id = $auth_methods[$i]->auth_id;
					$result_wrapper->SAML = $result;
					return $result_wrapper;
				}
			}
		}
		return false;
	}
	/**
	 * Test login
	 * @param string $domain
	 * @return bool or object
	 */
	function test_login($domain){
		$authentification = new SimpleSAML_Auth_Simple($domain);
		if($authentification->isAuthenticated()) {
			//echo "$domain is auth";
			return $authentification;
		}
		else {
			//echo "$domain is not auth";
			return false;
		}
	}
	/**
	 *
	 */
	function account_creation(){
		$result = false;
		switch ($_REQUEST["subjob"]){
			case "test":
				$result = $this->account_creation_test();
				echo json_encode($result);
				exit;
			case "create_account":
				$test = $this->account_creation_test();
				//$result = print_r($test,true);
				//$result.= print_r($_REQUEST,true);
				if($test->email===true && $test->password===true && $test->terms_accepted === true && $test->membership === true){
					$result = $this->account_creation_save();
				}
				else{
					$result = $this->account_creation_form();
				}
				break;
			case "confirm":
				$result = $this->account_creation_confirm($_REQUEST["hash"]);
				break;
			default:
				$result = $this->account_creation_form();
				break;
		}
		return $result;
	}
	/**
	 *
	 */
	function account_creation_form(){
		$return = new stdClass();
		$return->html = '<form id="account_creation_form">
					<h2>'.((SALUTATION=="Sie")?"Ihre":"Deine").' Daten</h2>
					<input type="hidden" name="job" value="account_creation"/>
					<input type="hidden" name="subjob" value="create_account"/>
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
					<div class="form-group">
					  <label for="password">Ihr Passwort</label>
					  <input type="password" class="form-control" id="password" name="password" aria-describedby="passwordHelp" placeholder="Wählen Sie Ihr Passwort" onchange="account_creation_form_validation(false);">
					  <small id="passwordHelp" class="form-text">Ihr Passwort muss Ziffer, Klein- und Großbuchstaben enthalten. Andere Zeichen sind nicht erlaubt.</small>
					  <div class="invalid-feedback" id="invalid-password">
					  </div>
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
					$return->html.= grape_organization_unit_select(grape_get_organization_units(),$user->ou_id,"none");
					$return->html.= '
					</select>
					<h4>'.((SALUTATION=="Sie")?"Ihre":"Deine").' Kommune</h4>
					<label for="commune_id">Wähle'.((SALUTATION=="Sie")?"n Sie Ihre":" Deine").' Kommune:</label>
					<select name="commune_id" id="commune_id">';
					$return->html.= grape_commune_select(grape_get_communes(),$user->commune_id,"none");
					$return->html.= '
					</select>
					<h4>'.((SALUTATION=="Sie")?"Ihre":"Deine").' BUND-Mitgliedschaft</h4>
					<div class="form-group form-check">
					  <input type="checkbox" name="membership" class="form-check-input" id="membership" onchange="account_creation_form_validation(false);">
					  <label class="form-check-label" for="membership">Ja, ich bin Mitglied oder Fördermitglied des BUND Baden-Württemberg</label>
					  <div class="invalid-feedback" id="invalid-membership">
					  </div>
					</div>
					<h4>Nutzungsbedingungen</h4>
					<div class="form-group form-check">
					  <input type="checkbox" name="terms_accepted" class="form-check-input" id="terms_accepted" onchange="account_creation_form_validation(false);">
					  <label class="form-check-label" for="terms_accepted">Ja, ich habe die Nutzungsbedingungen verstanden und akzeptiere sie.</label>
					  <div class="invalid-feedback" id="invalid-terms_accepted">
					  </div>
					</div>
					<input type="hidden" name="term_id" value="'.grape_get_current_terms()->term_id.'"/>
					<div>
					<a href="#" class="btn btn-secondary" onclick="toggle_terms()" id="terms_show">Nutzungsbedingungen einblenden</a>
					<a href="#" class="btn btn-secondary" onclick="toggle_terms()" id="terms_remove" style="display:none;">Nutzungsbedingungen ausblenden</a>
					</div>
					<div id="terms" style="display:none;">
					'.grape_get_current_terms()->content.'
					</div>
					<a href="#" class="btn btn-primary disabled" id="account_creation_send_button" onclick="account_creation_form_validation(true);" style="pointer-events: auto;">Account erstellen</a>
				</form>
				<script>
function toggle_terms(){
	$("#terms").toggle();
	$("#terms_show").toggle();
	$("#terms_remove").toggle();
}
function account_creation_form_validation(send){
	payload = {"membership":$(\'#membership\').prop(\'checked\'),"terms_accepted":$(\'#terms_accepted\').prop(\'checked\'),"email":$(\'#email\').prop(\'value\'),"password":$(\'#password\').prop(\'value\')};
	console.log(payload);
	var data = {};
	var jqxhr = $.post("'.URL.'ajax.php?job=account_creation&subjob=test", payload, function(data, status, xhr) {
        data = $.parseJSON( data );
		console.log(data);
		if(data.email===true&&data.password===true&&data.terms_accepted===true){
			$(".invalid-feedback").hide();
			$("#account_creation_send_button").removeClass("disabled");
			if(send){
				console.log("sending...");
				$("#account_creation_form").submit();
			}
		}
		else{
			$("#account_creation_send_button").addClass("disabled");
			if(data.email!==true){
				$("#invalid-email").html(data.email);
				$("#invalid-email").show();
			}
			else{
				$("#invalid-email").hide();
			}
			if(data.password!==true){
				$("#invalid-password").html(data.password);
				$("#invalid-password").show();
			}
			else{
				$("#invalid-password").hide();
			}
			if(data.terms_accepted!==true){
				$("#invalid-terms_accepted").html(data.terms_accepted);
				$("#invalid-terms_accepted").show();
			}
			else{
				$("#invalid-terms_accepted").hide();
			}			
			if(data.membership!==true){
				$("#invalid-membership").html(data.membership);
				$("#invalid-membership").show();
			}
			else{
				$("#invalid-membership").hide();
			}			
		}
		return data;
	})
	.fail(function() {
		bootstrap_alert.warning("danger","<strong>Fehler!</strong> Ich habe keine Verbindung zum Server.");
		$("#loader_wrapper").hide();
	});
}
function account_creation_send_button(){
	console.log("send clicked");
	var data = account_creation_form_validation();
	console.log("data:");
	console.log(data);
	console.log(":data");
	if(data.email===true&&data.password===true&&data.terms_accepted===true){
		console.log("sending...");
		$("#account_creation_form").submit();
	}
}
				</script>
				';
		$return->emotion = "curious";
		return $return;
	}
	/**
	 *
	 */
	function account_creation_save(){
		$return = new stdClass();
		// create simplesaml account
		$sql = "INSERT INTO `users`
				(`username`,`password`,`salt`,`hash`)
				VALUES ('".$_REQUEST["email"]."','',SHA2(".rand(0,63756).",256),SHA2(".rand(37,475452).",256));";
		$this->db->query($sql);
		//mail("fritz.mielert@bund.net","SQL 1",$sql);
		$sql = "UPDATE `users` SET `password` = SHA2(CONCAT(`salt`,'".$_REQUEST["password"]."'),512) WHERE `username` = '".$_REQUEST["email"]."';";
		$this->db->query($sql);
		//mail("fritz.mielert@bund.net","SQL 2",$sql);
		$sql = "SELECT `hash` FROM `users` WHERE `username` = '".$_REQUEST["email"]."';";
		$this->db->query($sql);
		//mail("fritz.mielert@bund.net","SQL 3",$sql);
		$result = $this->db->get_results();
		$hash = $result[0]->hash;
		// send email
		//mail($_REQUEST["email"],"=?UTF-8?B?".base64_encode("Bitte Account bestätigen")."?=","Guten Tag,\n\nmit Ihrer E-Mail-Adresse wurde soeben auf ".URL." ein Account erstellt. Bitte bestätigen Sie dies durch einen Klick auf folgenden Link:\n".URL."?job=account_creation&subjob=confirm&hash=$hash\n\nHerzlichen Dank & beste Grüße\n\nIhr BUND Baden-Württemberg");
		$subject = "Bitte Account bestätigen";
		$body = "<p>Guten Tag,</p>
		<p>mit Ihrer E-Mail-Adresse wurde soeben auf ".URL." ein Account erstellt.<br/>Bitte bestätigen Sie dies durch einen Klick auf folgenden Link:<br/><a href=\"".URL."?job=account_creation&subjob=confirm&hash=$hash\">Bestätigungslink</a></p><p>Herzlichen Dank & beste Grüße<br/>Ihr BUND Baden-Württemberg</p>";
		grape_send_mail(false,$_REQUEST["email"],$subject,$body);
		$return->html.= "Ich habe Ihnen an ".$_REQUEST["email"]." eine E-Mail geschickt.<br/>Bitte klicken Sie auf den darin enthaltenen Link, um Ihre Adresse zu bestätigen.";
		// create grape user
		grape_userdata_save_from_auth($_REQUEST["email"],5,1);
		// return message
		return $return;
	}
	/**
	 *
	 */
	function account_creation_confirm($hash){
		$return = new stdClass();
		$hash = str_replace(array("`",'"',"'","\\"),"",$hash);
		$sql = "UPDATE `users` SET `hash` = NULL WHERE `hash` = '".$hash."';";
		$this->db->query($sql);
		$return->html = '<a href="?job=login&auth_id=5">Jetzt einloggen!</a>';
		$return->result = "success";
		$return->message = "Account bestätigt";
		return $return;
	}
	/**
	 *
	 */
	function account_change_password(){
		$result = false;
		switch ($_REQUEST["subjob"]){
			case "test":
				$result = $this->account_creation_test();
				echo json_encode($result);
				exit;
			case "save":
				$test = $this->account_creation_test();
				//$result = print_r($test,true);
				//$result.= print_r($_REQUEST,true);
				if($test->password===true){
					$result = $this->account_change_password_save();
					//echo "ok";
					//exit;
				}
				else{
					$result = $this->account_change_password_form();
				}
				break;
			default:
				$result = $this->account_change_password_form();
				break;
		}
		return $result;
	}
	/**
	 *
	 */
	function account_change_password_form(){
		$return = new stdClass();
		$return->html = '<form id="account_change_password_form">
					<h2>Passwort ändern</h2>
					<input type="hidden" name="job" value="account_change_password"/>
					<input type="hidden" name="subjob" value="save"/>
					<div class="form-group">
					  <label for="password">Ihr neues Passwort</label>
					  <input type="password" class="form-control" id="password" name="password" aria-describedby="passwordHelp" placeholder="Wählen Sie Ihr Passwort" onchange="account_change_password_form_validation(false);">
					  <small id="passwordHelp" class="form-text">Ihr Passwort muss Ziffer, Klein- und Großbuchstaben enthalten. Andere Zeichen sind nicht erlaubt.</small>
					  <div class="invalid-feedback" id="invalid-password">
					  </div>
					</div>
					<a href="#" class="btn btn-primary disabled" id="account_change_password_send_button" onclick="account_change_password_form_validation(true);" style="pointer-events: auto;">Passwort ändern</a>
				</form>
				<script>
function account_change_password_form_validation(send){
	payload = {"password":$(\'#password\').prop(\'value\')};
	console.log(payload);
	var data = {};
	var jqxhr = $.post("'.URL.'ajax.php?job=account_change_password&subjob=test", payload, function(data, status, xhr) {
        data = $.parseJSON( data );
		console.log(data);
		if(data.password===true){
			$(".invalid-feedback").hide();
			$("#account_change_password_send_button").removeClass("disabled");
			if(send){
				console.log("sending...");
				$("#account_change_password_form").submit();
			}
		}
		else{
			$("#account_change_password_send_button").addClass("disabled");
			if(data.password!==true){
				$("#invalid-password").html(data.password);
				$("#invalid-password").show();
			}
			else{
				$("#invalid-password").hide();
			}
		}
		return data;
	})
	.fail(function() {
		bootstrap_alert.warning("danger","<strong>Fehler!</strong> Ich habe keine Verbindung zum Server.");
		$("#loader_wrapper").hide();
	});
}
				</script>
				';
		return $return;
	}
	/**
	 *
	 */
	function account_change_password_save(){
		global $grape;
		$return = new stdClass();
		$sql = "UPDATE `users` SET `password` = SHA2(CONCAT(`salt`,'".$_REQUEST["password"]."'),512) WHERE `username` = '".$grape->user->email."';";
		//mail("fritz.mielert@bund.net","SQL 2",$sql);
		$this->db->query($sql);
		$return->result = "success";
		$return->message = "Ihr Passwort wurde geändert.";
		// return message
		return $return;
	}
	/**
	 * check whether userdata is valid
	 * @return object
	 */
	function account_creation_test(){
		$result = new stdClass();
		$result->email = true;
		// email valid
		if(filter_var($_REQUEST["email"], FILTER_VALIDATE_EMAIL)) {
			// email exists?
			$sql = "SELECT `user_id` FROM `users` WHERE `username` = '".$_REQUEST["email"]."'";
			$this->db->query($sql);
			// pass
			if($this->db->num_rows == 0){
				$result->email = true;
			}
			else{
				$result->email = "Diese E-Mail-Adresse existiert schon...";
			}
		}
		// not valid
		else{
			$result->email = "Das ist leider keine gültige E-Mail-Adresse...";
		}
		// password
		$password_result = $this->password_strength($_REQUEST["password"]);
		if($password_result->valid === true){
			$result->password = true;
		}
		else{
			$result->password = $password_result->message;
		}
		if($_REQUEST["membership"]=="true" || $_REQUEST["membership"]=="on"){
			$result->membership = true;
		}
		else{
			$result->membership = "Diese Anwendung steht ausschließlich Mitgliedern und Fördermitgliedern des BUND Baden-Württemberg zur Verfügung.";
		}
		if($_REQUEST["terms_accepted"]=="true" || $_REQUEST["terms_accepted"]=="on"){
			$result->terms_accepted = true;
		}
		else{
			$result->terms_accepted = "Wenn Sie die Nutzungsbedingungen nicht akzeptieren, können Sie leider nicht mitmachen.";
		}
		$result->raw = $_REQUEST;
		return $result;
	}
	/**
	 * @param string $password
	 * @return object {$valid:bool,$message:string} true if valid, string with error message
	 */
	function password_strength($password){
		$result = new stdClass();
		$result->valid = false;
		$result->message = "OK";
		if(strlen($password)>=8){ // longer 7
			if(strlen($password)<=70){ // shorter 71
				if(preg_match('/\d/', $password)){ // number
					if(preg_match('/[a-z]/', $password)){ // lower case
						if(preg_match('/[A-Z]/', $password)){ // UPPER CASE
							if(!preg_match('/[^a-zA-Z\d]/', $password)){ // no '"`\
								$result->valid = true;
							}
							else{
								$result->message = "Ihr Passwort darf weder Umlaute noch irgendwelche Sonderzeichen enthalten.";
							}
						}
						else{
							$result->message = "Ihr Passwort muss Großbuchstaben enthalten.";
						}
					}
					else{
						$result->message = "Ihr Passwort muss Kleinbuchstaben enthalten.";
					}
				}
				else{
					$result->message = "Ihr Passwort muss Ziffern enthalten.";
				}
			}
			else{
				$result->message = "Ihr Passwort darf maximal 70 Zeichen lang sein.";
			}
		}
		else{
			$result->message = "Ihr Passwort muss mindestens acht Zeichen lang sein.";
		}
		return $result;
	}
	/**
	 * @deprecated
	 */
	function password_strength_check($password, $min_len = 8, $max_len = 70, $req_digit = true, $req_lower = true, $req_upper = true, $req_symbol = true) {
		// Build regex string depending on requirements for the password
		$regex = '/^';
		if ($req_digit) { $regex .= '(?=.*\d)'; }              // Match at least 1 digit
		if ($req_lower) { $regex .= '(?=.*[a-z])'; }           // Match at least 1 lowercase letter
		if ($req_upper) { $regex .= '(?=.*[A-Z])'; }           // Match at least 1 uppercase letter
		if ($req_symbol) { $regex .= '(?=.*[^a-zA-Z\d])'; }    // Match at least 1 character that is none of the above
		$regex .= '.{' . $min_len . ',' . $max_len . '}$/';
	
		if(preg_match($regex, $password)) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
}
?>