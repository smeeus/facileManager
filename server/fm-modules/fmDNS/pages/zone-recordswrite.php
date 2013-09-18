<?php

/**
 *
 * @author Tim Rowland
 * @version $Id: dump.php,v 1.3 2005/11/23 17:11:04 tim Exp $
 * @copyright 2005
 *
 **/

/*
echo '<pre>';
print_r($_POST);
echo '</pre>';
exit;
*/

require_once('fm-init.php');

if (array_key_exists('cancel', $_POST)) {
	header('Location: ' . $__FM_CONFIG['menu']['Admin']['Tools']);
	exit;
}

include(ABSPATH . 'fm-modules/fmDNS/classes/class_records.php');

extract($_POST);
if (in_array($record_type, $__FM_CONFIG['records']['require_zone_rights']) && !$allowed_to_manage_zones) header('Location: /');

if (isset($update) && is_array($update)) {
	foreach ($update as $id => $data) {
		/** Auto-detect IPv4 vs IPv6 A records */
		if ($record_type == 'A' && strrpos($data['record_value'], ':')) $record_type = 'AAAA';
		elseif ($record_type == 'AAAA' && !strrpos($data['record_value'], ':')) $record_type = 'A';

		$fm_dns_records->update($domain_id, $id, $record_type, $data);
	}
}

if (isset($create) && is_array($create)) {
	$record_count = 0;
	foreach ($create as $new_id => $data) {
		if (!isset($data['record_skip'])) {
			if (isset($import_records)) $record_type = $data['record_type'];
			
			/** Auto-detect IPv4 vs IPv6 A records */
			if ($record_type == 'A' && strrpos($data['record_value'], ':')) $record_type = 'AAAA';
			elseif ($record_type == 'AAAA' && !strrpos($data['record_value'], ':')) $record_type = 'A';
			
			if (!isset($record_type)) $record_type = null;
			if (!isset($data['record_comment']) || strtolower($data['record_comment']) == 'none') $data['record_comment'] = null;
			
			/** Remove double quotes */
			if (isset($data['record_value'])) $data['record_value'] = str_replace('"', '', $data['record_value']);
			
			$fm_dns_records->add($domain_id, $record_type, $data);
			
			/** Are we auto-creating a PTR record? */
			if ($record_type == 'A' && isset($data['PTR'])) {
				$domain = '.' . TrimFullStop(getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name')) . '.';
				if ($data['record_name'][0] == '@') {
					$data['record_name'] = null;
					$domain = substr($domain, 1);
				}
				
				/** Get reverse zone */
				if (!strrpos($data['record_value'], ':')) {
					$rev_domain = TrimFullStop(getNameFromID($data['PTR'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name'));
					$domain_pieces = array_reverse(explode('.', $rev_domain));
					$domain_parts = count($domain_pieces);
					
					$subnet_ips = null;
					for ($i=2; $i<$domain_parts; $i++) {
						$subnet_ips .= $domain_pieces[$i] . '.';
					}
					$record_octets = array_reverse(explode('.', str_replace($subnet_ips, '', $data['record_value'])));
					$temp_record_value = null;
					for ($j=0; $j<count($record_octets); $j++) {
						$temp_record_value .= $record_octets[$j] . '.';
					}
					$data['record_value'] = rtrim($temp_record_value, '.');
				} else break;
								
				$array = array(
						'record_name' => $data['record_value'],
						'record_value' => $data['record_name'] . $domain,
						'record_comment' => $data['record_comment'],
						'record_status' => $data['record_status'],
						);
						
				$fm_dns_records->add($data['PTR'], 'PTR', $array);
			}
			$record_count++;
		}
	}
	
	if (isset($import_records)) {
		$domain_name = getNameFromID($_POST['domain_id'], 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
		addLogEntry("Imported $record_count records from '$import_file' into $domain_name.");
	}
}

if (isset($record_type) && !isset($import_records)) {
	if ($record_type == 'AAAA') $record_type = 'A';
	header('Location: zone-records.php?map=' . $map . '&domain_id=' . $domain_id . '&record_type=' . $record_type);
} else header('Location: zone-records.php?map=' . $map . '&domain_id=' . $domain_id);

?>
