<?php
// user_list.php - AJAX handler for user list
include_once"../nocash.hnt";
$_REQUEST=array_intersect_key($_GET,['userid'=>true]);
require_once '../reqloginajax.hnt';
if (!aqaryUserManagementAllowed($link,'sees')) { http_response_code(403); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>false,'message'=>'ليس لديك صلاحية عرض المستخدمين.'],JSON_UNESCAPED_UNICODE); exit; }
include_once"../connectdb.hnt";

header('Content-Type: application/json; charset=utf-8');

// Get single user by ID
if(isset($_GET['userid'])) {
	$userid = intval($_GET['userid']);
	
	$sql = "SELECT u.userid, u.username, u.password, u.name, u.groupid, g.groupname 
			FROM user u 
			LEFT JOIN usergroups g ON u.groupid = g.groupid 
			WHERE u.userid = {$userid}";
	
	$result = mysql_query($sql, $link);
	
	if($result && mysql_num_rows($result) > 0) {
		$user = mysql_fetch_array($result);
		echo json_encode([
			'success' => true,
			'user' => [
				'userid' => $user['userid'],
				'username' => $user['username'],
				'password' => '',
				'name' => $user['name'],
				'groupid' => $user['groupid'],
				'groupname' => $user['groupname']
			]
		]);
	} else {
		echo json_encode([
			'success' => false,
			'message' => 'المستخدم غير موجود'
		]);
	}
	exit();
}

// Get all users
$sql = "SELECT u.userid, u.username, u.name, u.groupid, g.groupname, g.description 
		FROM user u 
		LEFT JOIN usergroups g ON u.groupid = g.groupid 
		ORDER BY u.userid ASC";

$result = mysql_query($sql, $link);

if(!$result) {
	echo json_encode([
		'success' => false,
		'message' => 'فشل في تحميل المستخدمين: ' . mysql_error($link)
	]);
	exit();
}

$users = array();
while($row = mysql_fetch_array($result)) {
	$users[] = [
		'userid' => $row['userid'],
		'username' => $row['username'],
		'name' => $row['name'],
		'groupid' => $row['groupid'],
		'groupname' => $row['groupname'] ? $row['groupname'] : 'غير محدد',
		'description' => $row['description']
	];
}

echo json_encode([
	'success' => true,
	'users' => $users,
	'count' => count($users)
]);
?>
