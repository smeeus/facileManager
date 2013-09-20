<?php

/**
 * Processes main page
 *
 * @author		Jon LaBass
 * @version		$Id:$
 * @copyright	2013
 *
 */

$map = (isset($_GET['map'])) ? strtolower($_GET['map']) : 'forward';

$page_name = 'Zones';
$page_name_sub = ($map == 'forward') ? 'Forward' : 'Reverse';

$record_type = (isset($_GET['record_type'])) ? strtoupper($_GET['record_type']) : 'A';
//$domain_id = (isset($_GET['domain_id'])) ? $_GET['domain_id'] : header('Location: /');

include(ABSPATH . 'fm-modules/fmDNS/classes/class_records.php');

if (!$allowed_to_manage_records || empty($_POST)) {
	header('Location: /');
}
if (in_array($record_type, $__FM_CONFIG['records']['require_zone_rights']) && !$allowed_to_manage_zones) header('Location: /');

printHeader();
@printMenu($page_name, $page_name_sub);

extract($_POST);
$html_out_create = $html_out_update = null;
foreach($_POST as $name => $value) {
	if (strtolower($name) == 'update') $html_out_update = buildReturnUpdate($domain_id, $record_type, $value);
	if (strtolower($name) == 'create') $html_out_create = buildReturnCreate($domain_id, $record_type, $value);
}

$header = $fm_dns_records->getHeader(strtoupper($record_type));

echo <<<HTML
<div id="body_container">
	<h2>Record Validation</h2>
	<form method="POST" action="zone-recordswrite">
	<input type="hidden" name="domain_id" value="$domain_id">
	<input type="hidden" name="record_type" value="$record_type">
	<input type="hidden" name="map" value="$map">
	<table class="display_results">
		<thead>
			<tr>
				$header
			</tr>
		</thead>
		<tbody>
			$html_out_update
			$html_out_create
		</tbody>
	</table>
	<p><input type="submit" name="submit" value="Submit" class="button" />
	<input type="reset" value="Back" onClick="history.go(-1)" class="button" /></p>
</form>
</div>
HTML;

printFooter();



function buildReturnUpdate($domain_id, $record_type, $value) {

	global $__FM_CONFIG;

	$ErrorCode = '<font color="red"><b>**</b></font>';
	$sql_records = buildSQLRecords($record_type, $domain_id);
	$changes = compareValues($value, $sql_records);
	if (count($changes)) {
		foreach ($changes as $i => $array) {
			$changes[$i] = array_merge($sql_records[$i], $changes[$i]);
		}
	} else {
		return false;
	}
	$input_return = array();

//	echo "<pre>";
//	print_r($sql_records);
//	print_r($changes);
//	exit;
	$HTMLOut = NULL;
	$domain = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
	foreach ($changes as $i => $data) {
		$name_error = $value_error = $ttl_error = $priority_error = null;
		$number_error = null;
		$append_error = null;

		if (isset($data['Delete'])) {
			$action = 'Delete';

//			echo "Delete $record_type $i\n";
			$HTMLOut.= buildInputReturn('update', $record_type, $i ,'record_status', 'deleted');

		} else {
			$y = 0;
			$action = '&nbsp;';
			foreach ($data as $key => $val) {
				$val = trim($val);
//				echo "$record_type $i =>  $key => $val<br>";

				if ($key == 'record_name' && $record_type != 'PTR') {
					if (!verifyName($val, true, $record_type)) {
						if ($val != '@') {
							$name_error = '<font color="red"><i>Invalid Name</i></font> ';
							$input_return_error[$i] = 1;
						}
					} elseif (!$val) {
						$val = '@';
					} else {
						if ($record_type == 'CNAME') {
							if (!nameCheck($domain_id, $val)) {
								$name_error = '<font color="red"><i>Duplicate Name</i></font> ';
								$input_return_error[$i] = 1;
							}
						}
					}
				}

				if ($key == 'record_ttl') {
					if (!empty($data[$key]) && verifyNumber($data[$key]) === false) {
						$ttl_error = '<font color="red"><i>Invalid</i></font> ';
						$input_return_error[$i] = 1;
					} else $ttl_error = null;
				}

				if ($record_type == 'CNAME') {
					if ($key == 'record_append') {
						if (!verifyCNAME($val, $_POST['update'][$i]['record_value'])) {
							$value_error = '<font color="red"><i>Invalid</i></font> ';
							$input_return_error[$i] = 1;
						}
					}

					if ($key == 'record_value') {
						if (!verifyCNAME($_POST['update'][$i]['record_append'], $val)) {
							$value_error = '<font color="red"><i>Invalid Value</i></font> ';
							$input_return_error[$i] = 1;
						}
					}
				}

				if ($record_type == 'A') {
					if ($key == 'record_value') {
						if (verifyIPAddress($val) === false) {
							$value_error = '<font color="red"><i>Invalid IP</i></font> ';
							$input_return_error[$i] = 1;
						}
					}
				}

				if ($record_type == 'MX') {
					if ($_POST['update'][$i]['record_priority'] == "") {
						$priority_error = '<font color="red"><i>Invalid</i></font> ';
						$input_return_error[$i] = 1;
					}
					if ($key == 'record_value') {
						if (!verifyCNAME($_POST['update'][$i]['record_append'], $val)) {
							$value_error = '<font color="red"><i>Invalid Value</i></font> ';
							$input_return_error[$i] = 1;
						}
					}
				}

				if ($record_type == 'PTR') {
					if ($key == 'record_name') {
						if (verifyIPAddress(buildFullIPAddress($data['record_name'], $domain)) === false) {
							$name_error = '<font color="red"><i>Invalid</i></font> ';
							$input_return_error[$i] = 1;
						}
					}

					if ($key == 'record_value') {
						if (!verifyCNAME('no', $val)) {
							$value_error = '<font color="red"><i>Invalid Value</i></font> ';
							$input_return_error[$i] = 1;
						}
					}
				}

				if ($record_type == 'NS') {
					if ($key == 'record_append') {
						if (!verifyCNAME($val, $_POST['update'][$i]['record_value'])) {
							$value_error = '<font color="red"><i>Invalid</i></font> ';
							$input_return_error[$i] = 1;
						}
					}

					if ($key == 'record_value') {
						if (!verifyCNAME($_POST['update'][$i]['record_append'], $val)) {
							$value_error = '<font color="red"><i>Invalid Value</i></font> ';
							$input_return_error[$i] = 1;
						}
					}
				}
				
				if ($record_type == 'SOA') {
					if (in_array($key, array('domain_id', 'soa_status'))) continue;
					if ($key == 'soa_email_address') {
						$val = strpos($val, '@') ? str_replace('@', '.', rtrim($val, '.') . '.') : $val ;
						$data[$key] = $val;
					}
					if ($key == 'soa_append') {
						if (!verifyCNAME($val, $_POST['update'][$i]['soa_master_server'])) {
							$value_error = '<font color="red"><i>Invalid</i></font> ';
							$input_return_error[$i] = 1;
						}
					} else {
						if ($key == 'soa_serial_no' && !$val) {
							continue;
						} elseif (in_array($key, array('soa_master_server', 'soa_email_address'))) {
							if (!verifyCNAME($_POST['update'][$i]['soa_append'], $val, false)) {
								$value_error = '<font color="red"><i>Invalid Value</i></font> ';
								$input_return_error[$i] = 1;
							}
						} else {
							if (!verifyNAME($val, false)) {
								$value_error = '<font color="red"><i>Invalid Value</i></font> ';
								$input_return_error[$i] = 1;
							}
						}
					}
				}

				if (!isset($input_return_error[$i]) || !$input_return_error[$i]) {
					if ($key == 'soa_serial_no' && !$val) continue;
					$input_return[$i][$y]= buildInputReturn('update', $record_type, $i, $key, $val);
					$action = 'Update';
				} else {
					$action = 'None';
				}
				$y++;
			}
		}

		$img = ($name_error || $value_error || $priority_error || $ttl_error || $append_error) ? $__FM_CONFIG['icons']['fail'] : $__FM_CONFIG['icons']['ok'];
		$value[$i]['record_name'] = (!$value[$i]['record_name'] && $record_type != 'PTR') ? '@' : $value[$i]['record_name'];
		if ($record_type == 'MX') {
			$HTMLOut.= "<tr><td>$action</td><td>$name_error {$value[$i]['record_name']}</td><td>$ttl_error {$value[$i]['record_ttl']}</td><td>{$value[$i]['record_class']}</td><td>$value_error {$value[$i]['record_value']}</td><td>$priority_error {$value[$i]['record_priority']}</td><td>{$value[$i]['record_comment']}</td><td style=\"text-align: center;\">{$value[$i]['record_append']}</td><td style=\"text-align: center;\">{$value[$i]['record_status']}</td><td style=\"text-align: center;\">$img</td></tr>\n";
		} elseif ($record_type == 'SRV') {
			$HTMLOut.= "<tr><td>$action</td><td>$name_error {$value[$i]['record_name']}</td><td>$ttl_error {$value[$i]['record_ttl']}</td><td>{$value[$i]['record_class']}</td><td>$value_error {$value[$i]['record_value']}</td><td>$number_error {$value[$i]['record_priority']}</td><td>$number_error {$value[$i]['record_weight']}</td><td>$number_error {$value[$i]['record_port']}</td><td>{$value[$i]['record_comment']}</td><td style=\"text-align: center;\">$append_error {$value[$i]['record_append']}</td><td style=\"text-align: center;\">{$value[$i]['record_status']}</td><td style=\"text-align: center;\">$img</td></tr>\n";
		} elseif ($record_type == 'CNAME' || $record_type == 'NS') {
			$HTMLOut.= "<tr><td>$action</td><td>$name_error {$value[$i]['record_name']}</td><td>$ttl_error {$value[$i]['record_ttl']}</td><td>{$value[$i]['record_class']}</td><td>$value_error {$value[$i]['record_value']}</td><td>{$value[$i]['record_comment']}</td><td style=\"text-align: center;\">{$value[$i]['record_append']}</td><td style=\"text-align: center;\">{$value[$i]['record_status']}</td><td style=\"text-align: center;\">$img</td></tr>\n";
		} elseif ($record_type == 'SOA') {
			foreach ($data as $name => $value) {
				if (($name == 'soa_serial_no' && !$value) || in_array($name, array('domain_id', 'soa_status'))) continue;
				$HTMLOut.= "<tr><td>$action</td><td>$name_error $name</td><td>$value_error $value</td><td></td><td style=\"text-align: center;\">$img</td></tr>\n";
			}
		} else {
			$name = ($record_type == 'PTR') ? $value[$i]['record_name'] . '.' . $domain : $value[$i]['record_name'];
			$HTMLOut.= "<tr><td>$action</td><td>$name_error $name</td><td>$ttl_error {$value[$i]['record_ttl']}</td><td>{$value[$i]['record_class']}</td><td>$value_error {$value[$i]['record_value']}</td><td>{$value[$i]['record_comment']}</td><td style=\"text-align: center;\">{$value[$i]['record_status']}</td><td style=\"text-align: center;\">$img</td></tr>\n";
		}

	}
	foreach ($input_return as $key => $val) {
		if (!isset($input_return_error[$key])) {
			for ($i=0; $i<count($input_return[$key]); $i++)
				$HTMLOut.= $input_return[$key][$i];
		}
	}
	return $HTMLOut;
}

function buildReturnCreate($domain_id, $record_type, $value) {
	
	global $__FM_CONFIG;

	$value_tmp = $value;
	$ErrorCode = '<font color="red"><b>*Invalid*</b></font>';
	$HTMLOut = $SOAHTMLOut = NULL;
	$input_return = array();
//	echo "<pre>";
//	print_r($value);
//	echo "</pre>";
	$domain = getNameFromID($domain_id, 'fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', 'domain_', 'domain_id', 'domain_name');
	foreach ($value as $i => $data) {
		$name_error = null;
		$value_error = null;
		$number_error = null;
		$append_error = null;
		$ttl_error = null;
		$action = '&nbsp;';
//		echo "{$value_tmp[$i]['Name']}*{$value_tmp[$i]['record_value']}<br>";
		if (!empty($value_tmp[$i]['record_value']) || (!isset($value_tmp[$i]['record_value']) && $record_type == 'SOA')) {
			$y = 0;
			foreach ($data as $key => $val) {
//				echo "Create($y) $record_type $i =>  $key => $val<br>";
				
				if (!isset($_POST['create'][$i]['record_append'])) $_POST['create'][$i]['record_append'] = 'no';
				if (!isset($value[$i]['record_append'])) $value[$i]['record_append'] = 'no';

				if ($key == 'record_name' && $record_type != 'PTR') {
					if (!verifyName($val, true, $record_type)) {
						$name_error = '<font color="red"><i>Invalid Name</i></font> ';
						$input_return_error[$i] = 1;
					} elseif (!$val) {
						$val = '@';
					}
				}
				
				if ($key == 'record_ttl') {
					if (!empty($data[$key]) && verifyNumber($data[$key]) === false) {
						$ttl_error = '<font color="red"><i>Invalid</i></font> ';
						$input_return_error[$i] = 1;
					} else $ttl_error = null;
				}

				if ($key == 'record_priority') {
					if (verifyNumber($data[$key]) === false) {
						$priority_error = '<font color="red"><i>Invalid</i></font> ';
						$input_return_error[$i] = 1;
					} else $priority_error = null;
				}

				if ($record_type == 'CNAME') {
					if (!isset($_POST['create'][$i]['record_append'])) {
						$append_error = '<font color="red"><i>Invalid</i></font> ';
						$input_return_error[$i] = 1;
					}

					if ($key == 'record_append') {
						if (!verifyCNAME($val, $_POST['create'][$i]['record_value'])) {
							$value_error = '<font color="red"><i>Invalid</i></font> ';
							$input_return_error[$i] = 1;
						}
					}

					if ($key == 'record_value') {
						if (!verifyCNAME($_POST['create'][$i]['record_append'], $val)) {
							$value_error = '<font color="red"><i>Invalid Value</i></font> ';
							$input_return_error[$i] = 1;
						}
					}
				}

				if ($record_type == 'A') {
					if ($key == 'record_value') {
						if (verifyIPAddress($val) === false) {
							$value_error = '<font color="red"><i>Invalid IP</i></font> ';
							$input_return_error[$i] = 1;
						}
					}
					if ($key == 'PTR') {
						$valid = checkPTRZone($data['record_value']);
						if (!$valid) {
							$value_error = '<font color="red"><i>Reverse zone does not exist.</i></font> ';
							$input_return_error[$i] = 1;
						} else $val = $valid;
					}
				}

				if ($record_type == 'MX') {
					if (!isset($_POST['create'][$i]['record_append'])) {
						$append_error = '<font color="red"><i>Invalid</i></font> ';
						$input_return_error[$i] = 1;
					}

					if ($key == 'record_priority') {
						if (verifyNumber($data['record_priority']) === false) {
							$priority_error = '<font color="red"><i>Invalid</i></font> ';
							$input_return_error[$i] = 1;
						}
					}

					if ($key == 'record_value') {
						if (!verifyCNAME($_POST['create'][$i]['record_append'], $val)) {
							$value_error = '<font color="red"><i>Invalid Value</i></font> ';
							$input_return_error[$i] = 1;
						}
					}
				}

				if ($record_type == 'PTR') {
					if ($key == 'record_name') {
						if (verifyIPAddress(buildFullIPAddress($data['record_name'], $domain)) === false) {
							$name_error = '<font color="red"><i>Invalid Record</i></font> ';
							$input_return_error[$i] = 1;
						}
					}

					if ($key == 'record_value') {
						if (!verifyCNAME('no', $val)) {
							$value_error = '<font color="red"><i>Invalid Value</i></font> ';
							$input_return_error[$i] = 1;
						}
					}
				}

				if ($record_type == 'NS') {
					if ($key == 'record_append') {
						if (!verifyCNAME($val, $_POST['create'][$i]['record_value'])) {
							$value_error = '<font color="red"><i>Invalid</i></font> ';
							$input_return_error[$i] = 1;
						}
					}

					if ($key == 'record_value') {
						if (!verifyCNAME($_POST['create'][$i]['record_append'], $val)) {
							$value_error = '<font color="red"><i>Invalid Value</i></font> ';
							$input_return_error[$i] = 1;
						}
					}
				}
				
				if ($record_type == 'SRV') {
					if (!isset($_POST['create'][$i]['record_append'])) {
						$append_error = '<font color="red"><i>Invalid</i></font> ';
						$input_return_error[$i] = 1;
					}

					if ($key == 'record_weight') {
						if (verifyNumber($data['record_weight']) === false) {
							$number_error = '<font color="red"><i>Invalid</i></font> ';
							$input_return_error[$i] = 1;
						}
					}

					if ($key == 'record_port') {
						if (verifyNumber($data['record_port']) === false) {
							$number_error = '<font color="red"><i>Invalid</i></font> ';
							$input_return_error[$i] = 1;
						}
					}

					if ($key == 'record_value') {
						if (!verifyCNAME($_POST['create'][$i]['record_append'], $val)) {
							$value_error = '<font color="red"><i>Invalid Value</i></font> ';
							$input_return_error[$i] = 1;
						}
					}
				}
				
				if ($record_type == 'SOA') {
					if ($key == 'soa_email_address') {
						$val = strpos($val, '@') ? str_replace('@', '.', rtrim($val, '.') . '.') : $val ;
						$data[$key] = $val;
					}
					if ($key == 'soa_append') {
						if (!verifyCNAME($val, $_POST['create'][$i]['soa_master_server'])) {
							$value_error = '<font color="red"><i>Invalid</i></font> ';
							$input_return_error[$i] = 1;
						}
					} else {
						if ($key == 'soa_serial_no' && !$val) {
							continue;
						} elseif (in_array($key, array('soa_master_server', 'soa_email_address'))) {
							if (!verifyCNAME($_POST['create'][$i]['soa_append'], $val, false)) {
								$value_error = '<font color="red"><i>Invalid Value</i></font> ';
								$input_return_error[$i] = 1;
							}
						} else {
							if (!verifyNAME($val, false)) {
								$value_error = '<font color="red"><i>Invalid Value</i></font> ';
								$input_return_error[$i] = 1;
							}
						}
					}
					
					$action = (!isset($input_return_error[$i]) || !$input_return_error[$i]) ? 'Create' : 'None';
					$img = ($name_error || $value_error) ? $__FM_CONFIG['icons']['fail'] : $__FM_CONFIG['icons']['ok'];
					$SOAHTMLOut.= "<tr><td>$action</td><td>$name_error $key</td><td>$value_error $val</td><td></td><td style=\"text-align: center;\">$img</td></tr>\n";
				}
				
				if (!isset($input_return_error[$i]) || !$input_return_error[$i]) {
					$input_return[$i][$y] = buildInputReturn('create', $record_type, $i, $key, $val);
					$action = 'Create';
				} else {
					$action = 'None';
				}
				$y++;
			}

			$img = ($name_error || $value_error || $number_error || $ttl_error || $append_error) ? $__FM_CONFIG['icons']['fail'] : $__FM_CONFIG['icons']['ok'];
			$value[$i]['record_name'] = (empty($value[$i]['record_name']) && $record_type != 'PTR') ? '@' : $value[$i]['record_name'];
			if ($record_type == 'MX') {
				$HTMLOut.= "<tr><td>$action</td><td>$name_error {$value[$i]['record_name']}</td><td>$ttl_error {$value[$i]['record_ttl']}</td><td>{$value[$i]['record_class']}</td><td>$value_error {$value[$i]['record_value']}</td><td>$priority_error {$value[$i]['record_priority']}</td><td>{$value[$i]['record_comment']}</td><td style=\"text-align: center;\">$append_error {$value[$i]['record_append']}</td><td style=\"text-align: center;\">{$value[$i]['record_status']}</td><td style=\"text-align: center;\">$img</td></tr>\n";
			} elseif ($record_type == 'SRV') {
				$HTMLOut.= "<tr><td>$action</td><td>$name_error {$value[$i]['record_name']}</td><td>$ttl_error {$value[$i]['record_ttl']}</td><td>{$value[$i]['record_class']}</td><td>$value_error {$value[$i]['record_value']}</td><td>$priority_error {$value[$i]['record_priority']}</td><td>$number_error {$value[$i]['record_weight']}</td><td>$number_error {$value[$i]['record_port']}</td><td>{$value[$i]['record_comment']}</td><td style=\"text-align: center;\">$append_error {$value[$i]['record_append']}</td><td style=\"text-align: center;\">{$value[$i]['record_status']}</td><td style=\"text-align: center;\">$img</td></tr>\n";
			} elseif ($record_type == 'CNAME' || $record_type == 'NS') {
				$HTMLOut.= "<tr><td>$action</td><td>$name_error {$value[$i]['record_name']}</td><td>$ttl_error {$value[$i]['record_ttl']}</td><td>{$value[$i]['record_class']}</td><td>$value_error {$value[$i]['record_value']}</td><td>{$value[$i]['record_comment']}</td><td style=\"text-align: center;\"> $append_error {$value[$i]['record_append']}</td><td style=\"text-align: center;\">{$value[$i]['record_status']}</td><td style=\"text-align: center;\">$img</td></tr>\n";
			} elseif ($record_type == 'SOA') {
				$HTMLOut.= $SOAHTMLOut;
			} else {
				$name = ($record_type == 'PTR') ? $value[$i]['record_name'] . '.' . $domain : $value[$i]['record_name'];
				$HTMLOut.= "<tr><td>$action</td><td>$name_error $name</td><td>$ttl_error {$value[$i]['record_ttl']}</td><td>{$value[$i]['record_class']}</td><td>$value_error {$value[$i]['record_value']}</td><td>{$value[$i]['record_comment']}</td><td style=\"text-align: center;\">{$value[$i]['record_status']}</td><td style=\"text-align: center;\">$img</td></tr>\n";
			}
		}
	}

	foreach ($input_return as $key => $val) {
		if (!isset($input_return_error[$key])) {
			for ($i=0; $i<count($input_return[$key]); $i++)
				$HTMLOut.= $input_return[$key][$i];
		}
	}
	return $HTMLOut;
}

function buildInputReturn($action, $record_type, $i, $Var1, $Var2) {

	return "<input type='hidden' name='{$action}[$i][$Var1]' value='$Var2'>\n";
}

function verifyName($record_name, $allow_null = true, $record_type = null) {
	if (!$allow_null && !strlen($record_name)) return false;
	
	if (preg_match("([_\.\!@#\$&\*\+\=\|/:;,'\"�%^\(\)])", $record_name) == false) {
		return true;
	} elseif ($record_name == '@') {
		return true;
	} else {
		if (($record_type == 'TXT' || $record_type == 'SRV') && ereg("([\!@#\$&\*\+\=\|/:;,'\"�%^\(\)])", $record_name) == false) {
			return true;
		}
		return false;
	}
}

function verifyCNAME($Append, $Var2, $allow_null=true) {
	if (!$allow_null && !strlen($Var2)) return false;
	
	if (preg_match("([_\!#\$&\*\+\=\|/:;,'\"�%^\(\)])", $Var2) == false) {
		if ($Append == "yes") {
			if (strstr($Var2, ".") == false) {
				return true;
			} else {
				return false;
			}
		} else {
			if ($Var2 == '@') return true;
			if (substr($Var2, -1, 1) == ".") {
				return true;
			} else {
				return false;
			}
		}
	} else {
		return false;
	}
}

function checkPTRZone($ip) {
	global $fmdb, $__FM_CONFIG;
	
	list($ip1, $ip2, $ip3, $ip4) = explode('.' , $ip);
	$zone = "'$ip3.$ip2.$ip1.in-addr.arpa', '$ip2.$ip1.in-addr.arpa', '$ip1.in-addr.arpa'";
	
	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'domains', $zone, 'domain_', 'domain_name', "OR domain_name IN ($zone)");
	if ($fmdb->num_rows) {
		$result = $fmdb->last_result;
		return $result[0]->domain_id;
	} else return false;
}

function nameCheck($domain_id, $value) {
	global $__FM_CONFIG;

	basicGet('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', $domain_id, 'record_', 'domain_id', "AND record_name='$value'");
	if ($fmdb->num_rows) {
		return false;
	} else {
		return true;
	}

}

function compareValues($value, $sql_records) {
	$changes = array();
	foreach ($value as $key => $val) {
		$diff = array_diff($value[$key], $sql_records[$key]);
		if ($diff) {
			$changes[$key] = $diff;
		}
	}

	return $changes;
}

function buildSQLRecords($record_type, $domain_id) {
	global $fmdb, $__FM_CONFIG;
	
	if ($record_type == 'SOA') {
		$soa_query = "SELECT * FROM `fm_{$__FM_CONFIG['fmDNS']['prefix']}soa` WHERE `domain_id`='$domain_id'";
		$fmdb->get_results($soa_query);
		if ($fmdb->num_rows) $result = $fmdb->last_result;
		else return null;
		
//		$sql_results[$result[0]->soa_id] = get_object_vars($result[0]);
		foreach (get_object_vars($result[0]) as $key => $val) {
			$sql_results[$result[0]->soa_id][$key] = $val;
		}
		array_shift($sql_results[$result[0]->soa_id]);
		array_shift($sql_results[$result[0]->soa_id]);
		return $sql_results;
	} else {
		if (in_array($record_type, array('A', 'AAAA'))) {
			$record_sql = "AND domain_id='$domain_id' AND record_type IN ('A', 'AAAA')";
		} else {
			$record_sql = "AND domain_id='$domain_id' AND record_type='$record_type'";
		}
		$result = basicGetList('fm_' . $__FM_CONFIG['fmDNS']['prefix'] . 'records', 'record_name', 'record_', $record_sql);
		if ($result) {
			$results = $fmdb->last_result;

			for ($i=0; $i<$result; $i++) {
				$sql_results[$results[$i]->record_id]['record_name'] = $results[$i]->record_name;
				$sql_results[$results[$i]->record_id]['record_ttl'] = $results[$i]->record_ttl;
				$sql_results[$results[$i]->record_id]['record_class'] = $results[$i]->record_class;
				$sql_results[$results[$i]->record_id]['record_value'] = $results[$i]->record_value;
				$sql_results[$results[$i]->record_id]['record_comment'] = $results[$i]->record_comment;
				$sql_results[$results[$i]->record_id]['record_status'] = $results[$i]->record_status;
				if ($results[$i]->record_append) {
					$sql_results[$results[$i]->record_id]['record_append'] = $results[$i]->record_append;
				}
				if ($results[$i]->record_priority != null) {
					$sql_results[$results[$i]->record_id]['record_priority'] = $results[$i]->record_priority;
				}
				if ($results[$i]->record_weight != null) {
					$sql_results[$results[$i]->record_id]['record_weight'] = $results[$i]->record_weight;
				}
				if ($results[$i]->record_port != null) {
					$sql_results[$results[$i]->record_id]['record_port'] = $results[$i]->record_port;
				}
			}
		}
		return $sql_results;
	}

}

/**
function BuildSOAPreview($domain_id) {
//	$SQLRecords = buildSQLRecords('SOA',$domain_id);
	$domain_info = DomainInformation($domain_id);
	$DomainName = $domain_info->DomainName;
	$DomainNameTrim = TrimFullStop($DomainName);
	
	$select = "select * from {$GLOBALS['DNSDB']}.{$GLOBALS['TBLSOA']} where domain_id='$domain_id' limit 1";
	$data = mysql_query($select,$GLOBALS['DCOCON']) or die("Invalid query: " . mysql_error() . "\n");
	if ($data && mysql_num_rows($data)) {
		$results = mysql_fetch_object($data);
		$SOAID = $results->SOAID;
		@mysql_free_result($data);
		
		extract($_POST['UpdateSOA'][$SOAID]);
	} else extract($_POST['CreateSOA'][0]);
	
	$email = ($Append == 'yes' && $domain_info->ZoneType == "forward") ? "$EmailAddress.$DomainNameTrim." : $EmailAddress ;
	$master = ($Append == 'yes' && $domain_info->ZoneType == "forward") ? "$MasterServer.$DomainNameTrim." : $MasterServer ;
	$SerialNo = ($SerialNo) ? $SerialNo : 'AutoGen';
	
	if ($domain_info->ZoneType == "forward") {
		$DomainName = "$DomainNameTrim.";
	}elseif ($domain_info->ZoneType == "reverse") {
		list($ip1, $ip2, $ip3) = split ("\." , $DomainNameTrim);
		if ($ip3!=NULL)
			$DomainName = "$ip3.$ip2.$ip1.in-addr.arpa.";
		else
			$DomainName = "$ip2.$ip1.in-addr.arpa.";
	}
	
	echo <<<HTML
<br /><b>Preview:</b>
<textarea class="tabby file-contents" rows="15" cols="80" wrap="off" disabled>
\$TTL = $TTL
$DomainName	IN	SOA	$master $email (
				$SerialNo	 ; serial
				$Refresh	 ; refresh
				$Retry	 ; retry
				$Expire	 ; expire
				$TTL	 ; ttl
				)
</textarea>
HTML;
}
*/

?>