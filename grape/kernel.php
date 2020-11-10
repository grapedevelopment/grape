<?php
// no direct call
if(!defined('GRAPE')) die('Direct access not permitted');

$grape = new stdClass();
$grape->startup = microtime_float();

/* load database module */
include_once('database.php');
$grape->db = new sql_caching(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST,DB_CHARSET);
/* load settings */
include_once('settings.php');
/* load authentification module */
include_once('auth.php');
/* load misc */
include_once('misc.php');
/* load geo */
include_once('geo.php');
/* load formatting module (Wordpress) */
include_once('external-libraries/wordpress/formatting.php');
/* load theme loader module */
include_once('theme-loader.php');
/* load user module */
include_once('user.php');
/* load legal module */
include_once('legal.php');
/* load module loader module */
include_once('modules.php');
/* load module campaigns */
include_once('campaigns.php');
/* load module elections */
include_once('elections.php');
grape_active_campaigns_by_user();
grape_get_all_campaigns();
/* ping */
if(isset($_REQUEST["job"]) && $_REQUEST["job"]=="ping"){
	echo '{"online":true}';
	exit;
}
/* basics */
$grape->output->homeURL = URL;
$grape->output->title->html = $grape->settings->title->html;
$grape->output->title->json->text = $grape->settings->title->json->text;
$grape->output->title->json->url = $grape->settings->title->json->url;
if(isset($_REQUEST["mode"]) && $_REQUEST["mode"]=="noauth" && isset($_REQUEST["module"]) && grape_is_module_active($_REQUEST["module"])){
	$grape->output->title->json->url = "module=".$_REQUEST["module"]."&campaign_id=".$_REQUEST["campaign_id"];
	$grape->output->title->json->text = "".grape_get_module_by_code($_REQUEST["module"])->name;
	call_user_func($_REQUEST["module"]."_run_noauth");
}
/* if not logged in */
if(!$grape->auth->isAuthenticated){
	//$grape->output->content->html.= $grape->output->dump_var($_REQUEST);
	if(isset($_REQUEST["job"]) && $_REQUEST["job"]=="account_creation"){
		$result = $grape->auth->account_creation();
		$html = $result->html;
		if(isset($result->result)){
			$grape->output->result = $result->result;
			$grape->output->message = $result->message;
		}
		if(isset($result->emotion)){
			$grape->output->emotion = $result->emotion;
		}
	}
	else{
		//$grape->output->result = "info";
		//$grape->output->message = "Bitte authentifiziere".((SALUTATION=="Sie")?"n Sie sich":" Dich").".";
		//$grape->output->message = "Wir machen Energiewende!";
		$grape->output->content_type = "focus";
		$grape->output->add_menu_item(array("url"=>URL."?job=login","name"=>"Login"));
		$grape->output->add_menu_item(array("url"=>URL,"name"=>"Übersicht"));
		/*$html = '<h3>Solar aufs Dach!</h3>
				 <p>Welche öffentlichen Dächer in meinem Ort haben Solar-Potential?
				 Z.B. Schule, Rathaus, Schwimmbad?
				 Wir schlagen Gebäude vor - <strong>nur Sie vor Ort</strong> sehen, welche Dächer wirklich passen.</p>
				 <p>Lassen Sie uns gemeinsam diesen Schatz heben<br/>und Ihre Gemeinde anstupsen!</p>
				 <!--Jetzt den Schatz heben:
				 <p>Im Ländle haben kommunale Dächer ein enormes Potential für Solar-Anlagen. Lasst uns diesen Schatz gemeinsam heben! Mitmach-Aktion exklusiv für BUND-Mitglieder.</p>
				 <p>Mit dieser WebApp können Sie als BUND-Mitglied recherchieren, welches Potential für Solarstrom-Anlagen in Ihrer Kommune besteht und Ihre Kommune darum bitten, die geeigneten kommunalen Dächer mit Photovoltaik-Anlagen zu bestücken.</p>-->
				 <!--<p>Offizieller Start der Aktion ist der 17. August 2018.</p>-->';
		$grape->output->content->html.= $grape->output->wrap_div($html);*/
		$html = '<img src="'.URL."grape-themes/".$grape->output->dir_name.'images/logo.svg" style="height: 80px;width: auto;margin-bottom: 2em;margin-top: 1em;"/><br/>';
		$html.= '<div class="btn-group-vertical">'.$grape->auth->html_login_button().'</div>';
		/*
		//$html.= $grape->output->dump_var($grape->settings->title);
		if(isset($grape->settings->authentification)&&$grape->settings->authentification->account_creation){
			$html.= '<p>&nbsp;</p><p>'.((SALUTATION=="Sie")?"Sie sind":"Du bist").' noch nicht dabei?<br/>Hier für die <strong>Schatzsuche</strong> registrieren:</p>
					<a href="#" onclick="load_content(\'job=account_creation\');" class="btn btn-primary">BUND-BaWü-Account erstellen</a>';
		}
		$html.= '<p>&nbsp;</p>';*/
	}
	$grape->output->content->html.= $grape->output->wrap_div($html);
	

}
/* if logged in */
else{
	// Build menu
	// Get context
	$grape->output->homeURL = URL;
	//$grape->output->title->json->text = "";
	// Userdata
	if(isset($_REQUEST["job"]) && $_REQUEST["job"]=="userdata_save"){
		$grape->output->add_menu_items(grape_get_modules_menu());
		if(grape_userdata_save()){
			$grape->output->result = "success";
			$grape->output->message = "Ich habe ".((SALUTATION=="Sie")?"Ihre":"Deine")." Daten gespeichert.";
			grape_load_current_user();
			if(!grape_current_terms_accepted()){
				$current_term = grape_get_current_terms();
				$html = "Bevor es weitergeht, bitte ich ".((SALUTATION=="Sie")?"Sie":"Dich").", unser Nutzungsbedingungen zu akzeptieren.";
				$html.= "<h2>Nutzungsbedingungen</h2>";
				$html.= "<p>Stand: ".$current_term->published."</p>";
				$html.= $current_term->content;
				$html.= '<div class="btn-group btn-block special" role="group" aria-label="">
							<a href="#" class="btn btn-secondary" onclick="load_content()" role="button">Ich akzeptiere die Nutzungsbedingungen nicht.</a><br/>
							<a href="#" class="btn btn-primary" onclick="load_content(\'job=accept_terms\')" role="button">Ja, ich habe die Nutzungsbedingungen verstanden und akzeptiere sie.</a>
						</div>';
				$grape->output->content->html.= $grape->output->wrap_div($html);
			}
			else{
				grape_active_campaigns_by_user();
				grape_start_screen();
			}
		}
		else{
			$grape->output->result = "warning";
			$grape->output->message = ((SALUTATION=="Sie")?"Ihre":"Deine")." Daten waren fehlerhaft.";
			$grape->output->content->html.= $grape->output->wrap_div(grape_userdata_edit());
		}
	}
	// do we have userdata
	elseif($grape->user === false || !isset($grape->user->email) || (isset($_REQUEST["job"]) && $_REQUEST["job"]=="userdata_edit")){
		//$grape->output->add_menu_items(grape_get_modules_menu());
		$grape->output->content->html.= $grape->output->wrap_div(grape_userdata_edit());
	}
	// current terms accepted?
	elseif(!grape_current_terms_accepted()){
		if(isset($_REQUEST["job"]) && $_REQUEST["job"]=="accept_terms"){
			$grape->output->result = "success";
			$grape->output->message = "Danke, dass ".((SALUTATION=="Sie")?"Sie":"Du")." einverstanden ".((SALUTATION=="Sie")?"sind":"bist").".";
			grape_accept_current_terms();
			grape_start_screen();
		}
		else{
			$current_term = grape_get_current_terms();
			$html = "Bevor es weitergeht, bitte ich ".((SALUTATION=="Sie")?"Sie":"Dich").", unser Nutzungsbedingungen zu akzeptieren.";
			$html.= "<h2>Nutzungsbedingungen</h2>";
			$html.= "<p>Stand: ".$current_term->published."</p>";
			$html.= $current_term->content;
			$html.= '<div class="btn-group btn-block special" role="group" aria-label="">
						<a href="#" class="btn btn-secondary" onclick="load_content()" role="button">Ich akzeptiere die Nutzungsbedingungen nicht.</a><br/>
						<a href="#" class="btn btn-primary" onclick="load_content(\'job=accept_terms\')" role="button">Ja, ich habe die Nutzungsbedingungen verstanden und akzeptiere sie.</a>
					</div>';
			$grape->output->content->html.= $grape->output->wrap_div($html);
		}
	}
	// change password
	elseif(isset($_REQUEST["job"]) && $_REQUEST["job"]=="account_change_password"){
		$result = $grape->auth->account_change_password();
		if(!isset($result->html)||$result->html==""){
			grape_start_screen();
		}
		else{
			$html = $result->html;
		}
		if(isset($result->result)){
			$grape->output->result = $result->result;
			$grape->output->message = $result->message;
		}
		$grape->output->content->html.= $grape->output->wrap_div($html);
	}
	// terms
	elseif(isset($_REQUEST["job"]) && $_REQUEST["job"]=="terms"){
		$current_text = grape_get_current_terms();
		$html = "<h2>Nutzungsbedingungen</h2>";
		$html.= "<p>Stand: ".$current_text->published."</p>";
		$html.= $current_text->content;
		$grape->output->content->html.= $grape->output->wrap_div($html);
	}
	// imprint
	elseif(isset($_REQUEST["job"]) && $_REQUEST["job"]=="imprint"){
		$current_text = grape_get_current_imprint();
		$html = "<h2>Impressum</h2>";
		//$html.= "<p>Stand: ".$current_text->published."</p>";
		$html.= $current_text->content;
		$grape->output->content->html.= $grape->output->wrap_div($html);
	}
	// privacy policy
	elseif(isset($_REQUEST["job"]) && $_REQUEST["job"]=="privacy_policy"){
		$current_text = grape_get_current_privacy_policy();
		$html = "<h2>Datenschutzerklärung</h2>";
		$html.= "<p>Stand: ".$current_text->published."</p>";
		$html.= $current_text->content;
		$grape->output->content->html.= $grape->output->wrap_div($html);
	}
	// module to call?
	elseif(isset($_REQUEST["module"]) && grape_is_module_active($_REQUEST["module"])) {
		$grape->output->title->json->url = "module=".$_REQUEST["module"]."&campaign_id=".$_REQUEST["campaign_id"];
		$grape->output->title->json->text = "".grape_get_module_by_code($_REQUEST["module"])->name;
		call_user_func($_REQUEST["module"]."_run");
	}
	// not trusted
	elseif($grape->user->trusted == 0){
		grape_send_mail($grape->user->email,"fritz.mielert@bund.net","Bitte um Freischaltung",print_r($grape->user,true));
		$grape->output->result = "warning";
		$grape->output->message = ((SALUTATION=="Sie")?"Sie müssen":"Du musst")." noch freigeschaltet werden. Ich habe fritz.mielert@bund.net deshalb eine E-Mail geschickt. Er meldet sich umgehend bei ".((SALUTATION=="Sie")?"Ihnen":"Dir").".";
		$grape->output->content->html.= $grape->output->wrap_div("Bevor ".((SALUTATION=="Sie")?"Sie":"Du")." nicht freigeschaltet ".((SALUTATION=="Sie")?"wurden":"wurdest").", ".((SALUTATION=="Sie")?"sehen Sie":"siehst Du")." hier leider gar nichts.");
	}
	// Start screen
	else{
		grape_start_screen();
	}
	//$methods = $this->getMethods(ReflectionMethod::IS_PUBLIC);
	//$this->output->content->html.= "<pre>".print_r($methods,true)."</pre>";
	//$grape->output->content->html.= grape_get_biggest_capability_within_ou(1);
	//if is admin somewhere => show admin menu item
	if(grape_user_is_admin() && $_REQUEST["module"] != "admin"){
		$grape->output->add_menu_item(array("url"=>URL."?module=admin","name"=>"Adminübersicht"));
	}
	$grape->output->add_menu_item(array("url"=>URL."?job=userdata_edit","name"=>"Meine Daten"));
	$grape->output->add_menu_item(array("url"=>URL."?job=account_change_password","name"=>"Passwort ändern"));
	$grape->output->add_menu_item(array("url"=>URL."?job=logout","name"=>"Logout"));
	$grape->output->add_menu_item(array("url"=>URL."?job=terms","name"=>"Nutzungsbedingungen"));
	$grape->output->add_menu_item(array("url"=>URL."?job=privacy_policy","name"=>"Datenschutzerklärung"));
	$grape->output->add_menu_item(array("url"=>URL."?job=imprint","name"=>"Impressum"));
	$grape->output->add_menu_item(array("url"=>URL,"name"=>"Übersicht"));
}
//$grape->output->content->html.= $grape->output->dump_var($grape->auth->SAML->getAttributes());
//$grape->output->content->html.= $grape->output->dump_var($grape->user);
if(OUTPUT=="HTML"){
	$grape->output->html_out();
}
// AJAX Output
elseif(OUTPUT=="AJAX"){
	$grape->output->ajax_out();
}
/**
 * Start screen renders carts of available campaigns
 */
function grape_start_screen(){
	global $grape;
	//$grape->output->content->html.= $grape->output->dump_var($_REQUEST);
	//$grape->output->content->html.= $grape->output->dump_var($grape->user->campaigns);
	//$grape->output->content->html.= $grape->output->dump_var(grape_get_current_term());
	$html = "";
	//$grape->output->add_menu_items(grape_get_modules_menu());
	//$grape->output->content->html.= "<pre>Settings: ".print_r($grape->settings,true)."</pre>";
	//$grape->output->content->html.= $grape->output->dump_var($grape->user->campaigns);
	// Campaign Buttons
	foreach($grape->user->campaigns as $campaing){
		$capability_for_this_campaign = grape_get_biggest_capability_within_ou($campaing->ou_id);
		foreach($campaing->modules as $module){
			//$grape->output->content->html.= "<p>".$module->name." Needed capability: ".$module->capability." User capability: ".$capability_for_this_campaign."</p>";
			if(grape_user_has_capability($capability_for_this_campaign,$module->capability)){
				$campaignbutton = new stdClass();
				$campaignbutton->url = "?module=".$module->code."&campaign_id=".$campaing->campaign_id;
				$campaignbutton->name = $campaing->name;
				$campaignbutton->message = $campaing->message;
				if(isset($campaing->background)){
					$campaignbutton->background = $campaing->background;
				}
				else{
					$campaignbutton->background = false;
				}
				$campaignbutton->image = $campaing->image;
				$grape->output->add_campaign_button($campaignbutton);
				//$html.= $campaignbutton->url;
			}/*
			else{
				$campaignbutton = new stdClass();
				$campaignbutton->url = "?module=admin&module_context=".$module->code."&campaign_id=".$campaing->campaign_id."&job=ask_for_capability_form&return=".urlencode($_SERVER["QUERY_STRING"]);
				$campaignbutton->url_type ="";
				$campaignbutton->name = $module->name;
				$campaignbutton->message = $campaing->name."<br/>Fordere Rechte an, um mitzumachen!";
				$campaignbutton->background = "#bbb";
				$grape->output->add_campaign_button($campaignbutton);
			}*/
		}
	}
	$html.= "<h3>Hallo ".$grape->user->name.((SALUTATION=="Sie")?" ".$grape->user->last_name:"")."!</h3><p>Herzlich Willkommen auf ".((SALUTATION=="Sie")?"Ihrer":" Deiner")." Kampagnenplattform.</p>";
	$html.= "<p>Im ".$grape->user->ou_type_name." ".$grape->user->ou_name." ";
	if(count($grape->output->campaignitems) == 0){
		$html.= "laufen momentan keine Kampagnen.</p>";
	}
	else{
		if(count($grape->output->campaignitems) == 1){
			$html.= "läuft momentan eine Kampagne. Mach mit!</p>";
		}
		else{
			//$grape->output->content->html.= $grape->output->wrap_div(get_current_term());
			//$grape->output->content->html.= $grape->output->dump_var($grape->user);
			$html.= "laufen momentan ".count($grape->output->campaignitems)." Kampagnen. Mach mit!</p>";
		}
		$campaign_buttons = $grape->output->build_campaign_buttons();
	}
	$grape->output->content->html.= $grape->output->wrap_div($html);
	$grape->output->content->html.= $campaign_buttons;
	//$grape->output->content->html.= '<p style="clear: both;">&nbsp;</p><pre>'.print_r($grape->user,true)."</pre>";
	//$grape->output->content->html.= "<pre>".print_r($grape->user,true)."</pre>";
	//$grape->output->content->html.= "<pre>".print_r($grape->settings->modules,true)."</pre>";
}
/**
 *
 */
function microtime_float(){
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}
?>