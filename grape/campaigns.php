<?php
/**
 *
 */

// no direct call
if(!defined('GRAPE')) die('Direct access not permitted');

/**
 * 
 */
function grape_active_campaigns_by_user(){
	global $grape;
	if($grape->user){
		$campaigns = array();
		$ou_id = $grape->user->ou_id;
		$grape->user->campaigns = grape_active_campaigns_by_user_recursion($campaigns,$grape->user->ou_id);
	}
}
/**
 *
 */
function grape_get_all_campaigns(){
	global $grape;
	if($grape->user){
		$grape->user->all_campaigns = grape_all_campaigns();
	}
}
/**
 *
 */
function grape_campaign_by_campaign_id($campaign_id,$active=true){
	global $grape;
	$campaign_id = intval($campaign_id);
	$result = false;
	if($active){
		foreach($grape->user->campaigns as $campaign){
			if($campaign->campaign_id == $campaign_id){
				$result = $campaign;
			}
		}
	}
	else{
		foreach($grape->user->all_campaigns as $campaign){
			if($campaign->campaign_id == $campaign_id){
				$result = $campaign;
			}
		}
	}
	return $result;
}
/**
 * @param array $campaigns
 * @param int $ou_id
 */
function grape_active_campaigns_by_user_recursion($campaigns,$ou_id){
	global $grape;
	$ou_id = intval($ou_id);
	$sql = "SELECT `grape_campaigns`.*, `grape_x_campaigns_organization_units`.`ou_id`, `grape_elections`.`name` AS election_name
			FROM `grape_x_campaigns_organization_units`
			LEFT JOIN `grape_campaigns` ON `grape_campaigns`.`campaign_id` = `grape_x_campaigns_organization_units`.`campaign_id`
			LEFT JOIN `grape_elections` ON `grape_elections`.`election_id` = `grape_campaigns`.`election_id`
			WHERE `grape_x_campaigns_organization_units`.`ou_id` = $ou_id
			AND `grape_x_campaigns_organization_units`.`visible` = 1
			AND `grape_campaigns`.`begin` < NOW()
			AND `grape_campaigns`.`end` > NOW()
			AND `grape_campaigns`.`visible` = 1";
	//$grape->output->content->html.= $grape->output->dump_var($sql);
	$grape->db->query($sql);
	if($grape->db->num_rows > 0){
		$db_results = $grape->db->get_results();
		foreach($db_results as $result){
			$result = grape_get_modules_by_campaign($result);
			array_push($campaigns,$result);
		}
	}
	$ou_id = grape_get_parent_organization_unit($ou_id);
	if($ou_id){
		$campaigns = grape_active_campaigns_by_user_recursion($campaigns,$ou_id);
	}
	return $campaigns;		
}
/**
 *
 */
function grape_all_campaigns(){
	global $grape;
	$campaigns = array();
	$sql = "SELECT `grape_campaigns`.*, `grape_x_campaigns_organization_units`.`ou_id`, `grape_elections`.`name` AS election_name
			FROM `grape_x_campaigns_organization_units`
			LEFT JOIN `grape_campaigns` ON `grape_campaigns`.`campaign_id` = `grape_x_campaigns_organization_units`.`campaign_id`
			LEFT JOIN `grape_elections` ON `grape_elections`.`election_id` = `grape_campaigns`.`election_id`";
	//$grape->output->content->html.= $grape->output->dump_var($sql);
	$grape->db->query($sql);
	if($grape->db->num_rows > 0){
		$db_results = $grape->db->get_results();
		foreach($db_results as $result){
			$result = grape_get_modules_by_campaign($result);
			array_push($campaigns,$result);
		}
	}
	return $campaigns;		
}
/**
 *
 */
function grape_get_modules_by_campaign($campaign){
	global $grape;
	$campaign->modules = array();
	$sql = "SELECT `grape_modules`.*
			FROM `grape_x_campaigns_modules`
			LEFT JOIN `grape_modules` ON `grape_modules`.`module_id` = `grape_x_campaigns_modules`.`module_id`
			WHERE `grape_x_campaigns_modules`.`campaign_id` = ".$campaign->campaign_id."
			AND `grape_x_campaigns_modules`.`visible` = 1";
	$grape->db->query($sql);
	if($grape->db->num_rows > 0){
		$campaign->modules = $grape->db->get_results();
	}
	return $campaign;
}
/**
 *
 */
function grape_get_election($election_id){
	
}
?>