<?php
// Errors
error_reporting(0);

// Facebook code
// Requires Facebook PHP SDK
require '../src/facebook.php';
$config = array(
'appId'  => 'APP_ID',
'secret' => 'APP_SECRET',);
$facebook = new Facebook($config);
$user = $facebook->getUser();

// Check if no perms
if($user) {
  $errorresult = json_decode(file_get_contents('https://graph.facebook.com/me/permissions?access_token='.$facebook->getAccessToken()), true);
  if(!array_key_exists('read_mailbox', $errorresult['data'][0]))
    header('Location: '.$facebook->getLoginUrl(array('scope' => 'read_mailbox')));
}

// Default functions
function loopAndFind($arr, $ind, $sea){
  $return = false;
  for($i = 0; $i < count($arr); $i++)
    if($arr[$i][$ind] == $sea)
      $return = $i;
  return $return;
}
function fixem($a, $b){
  if ($a["message_count"] == $b["message_count"])
    return 0;
  return ($a["message_count"] < $b["message_count"]) ? -1 : 1;
}
?>
<html>
<head> 
<title>Messages Counter</title>
<link rel="icon" type="image/png" href="https://fbstatic-a.akamaihd.net/rsrc.php/yl/r/H3nktOa7ZMg.ico">
<style type="text/css">
* {
  text-decoration:none;
  font-family: Helvetica, Arial, sans-serif;
}
h1 {
  margin-bottom: 0px;
}
.graphlink {
  color: blue;
  cursor: pointer;
}
.graphselected {
  color: black;
  font-weight: bold;
  cursor: inherit;
}
</style>
<script type="text/javascript" src="//www.google.com/jsapi"></script>
<script type="text/javascript">
  google.load('visualization', '1', {packages: ['corechart']});
</script>
<script type="text/javascript">
    function togglevis(id, two, three) {
        var e = document.getElementById(id);
        var ebutt = document.getElementById(id + 'butt');
        document.getElementById(two).style.display = 'none';
        document.getElementById(three).style.display = 'none';
        document.getElementById(two + 'butt').className = 'graphlink';
        document.getElementById(three + 'butt').className = 'graphlink';
        if (e.style.display == 'block') {
            e.style.display = 'none';
            ebutt.className = 'graphlink';
        } else {
            e.style.display = 'block';
            ebutt.className = 'graphselected';
        }
    }
</script>
<meta property="og:title" content="Messages Counter" />
<meta property="og:site_name" content="Messages Counter"/>
<meta property="fb:admins" content="1434685963"/>
<meta property="og:type" content="website"/>
<meta property="og:url" content="http://zach.ie/fb/messages/" />
<meta property="og:image" content="http://zach.ie/fb/fb.png" />
<meta name="viewport" content="width=480; initial-scale=0.6666; maximum-scale=1.0; minimum-scale=0.6666" />
<meta property="og:description" content="Sort Facebook mailbox threads by message count, and display some interesting graphs and statistics about messaging habits." />
</head>
<body>
<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_US/all.js#xfbml=1&appId=361103244003981";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
<h1><img src="http://zach.ie/fb/messages/messages.png" style="vertical-align:middle;height:50px;width:50px;" alt="App"/> Messages Counter</h1>
<i>Looking for the Friends Counter? Click <a href="/fb/friends/" target="_blank" title="Friends Counter">here</a>.</i><br />
<?php
try {

  if($user) {
      $currentuser = $facebook->api('/me');
      $access_token = $facebook->getAccessToken();
  }
  // Show if not logged in
  if(!$user){
    echo "<br /><b>App information:</b><br />";
    echo "This application connects to Facebook and asks for information on the threads in your mailbox.<br />Facebook requires this application to request <i>basic and read_mailbox permissions</i> from the user.<br />";
    echo "After you authenticate, messaging statistics and threads sorted by message count will be displayed.<br />";
      
  }

  // We have a user ID, so probably a logged in user.
  // If not, we'll get an exception, which we handle below.
  if($user) {
    //Show user photo ?>
    <h2>Hey, <img src="https://graph.facebook.com/<?php echo $currentuser['id']; ?>/picture" style="width: 35px; height: 35px; vertical-align: middle;" /> <?php echo $currentuser['name']; ?>! <br />
    </h2>
    <?php 
    // FQL Query
    $fqlquerycount = 'https://graph.facebook.com/'
    . 'fql?q=SELECT+name,+unread_count,+total_count+FROM+mailbox_folder+WHERE+folder_id+=+0+and+viewer_id+=+me()'
    . '&access_token=' . $access_token;
      $resultcount = file_get_contents($fqlquerycount);
      $resultcounttoadd = $resultcount;
      $resultcount = json_decode($resultcount, true);  
    
    // FQL Query
    $folder = $resultcount['data'][0]['name'];
  
    // Query Facebook in chunks of 20
    $fql = 'https://graph.facebook.com/fql?q={';
    for($i = 0; $i < $resultcount['data'][0]['total_count']; $i = $i + 20) {
      $fql = $fql.'"query'.($i/20).'":"SELECT+message_count,updated_time,unread,thread_id,recipients+FROM+thread+WHERE+folder_id+=+\'0\'+LIMIT+20+OFFSET+'.$i.'",';
    }
    $fql = substr($fql, 0, -1).'}&access_token=' . $access_token;

    // Get results
    $result = file_get_contents($fql);
    $resulttoadd = $result;
    $result = json_decode($result, true);
    
    for($i = 0; $i <= floor($resultcount['data'][0]['total_count']/20); $i++){
      for($j = 0; $j < 20; $j++){
        $resultarray[$j + ($i * 20)] = $result['data'][$i]['fql_result_set'][$j];
      }
    }
  
    // Sort by time if param given, else by count
    if($_GET['sort'] == "time"){
      $reversed = $resultarray;
    } else  {
      usort($resultarray, "fixem");
      $reversed = array_reverse($resultarray);
    }
  
    // Unset the resultarray
    unset($resultarray);
    
    // Remove instances of self
    for($j = 0; $j < count($reversed); $j++)
      for($i = 0; $i < count($reversed[$j]['recipients']); $i++)
      if($currentuser['id']==$reversed[$j]['recipients'][$i])
        array_splice($reversed[$j]['recipients'], $i, 1);
    
    // Remove all group messages     
    if($_GET['group'] == '0'){  
      $reversedcopy = $reversed;
      for($j = 0; $j < count($reversedcopy); $j++)
        if(count($reversedcopy[$j]['recipients']) !== 1)
        unset($reversed[$j]);
      unset($reversedcopy);
    }
    
    // Fix indexes after unsetting
    $reversed = array_values($reversed);
    
    // Participants Variables
    $allparticipants = array();
    $partcounter = 0;
    
    // Construct array of all participants
    for($i = 0; $i < count($result['data']); $i++){
      for($j = 0; $j < count($result['data'][$i]['fql_result_set']); $j++){
        for($k = 0; $k < 2; $k++){
          if(!loopAndFind($allparticipants, 'user_id', $result['data'][$i]['fql_result_set'][$j]['recipients'][$k])){
            $allparticipants[$partcounter]['user_id'] = $result['data'][$i]['fql_result_set'][$j]['recipients'][$k];
            $partcounter++;
          }
        }
      }
    }
    
    // Get Participants sexes from facebook into resultarray[]
    $fqlquerypart = 'https://graph.facebook.com/fql?q={';  
    for($i = 0; $i < count($allparticipants); $i++){
      $fqlquerypart = $fqlquerypart .'"query'.$i.'":"SELECT+name,sex,uid+FROM+user+WHERE+uid+=+\''.$allparticipants[$i]['user_id'].'\'",';
    }  
    $fqlquerypart = substr($fqlquerypart , 0, -1).'}';
    $resultpart = file_get_contents($fqlquerypart);
    $resultparttoadd = $resultpart;
    $resultpart = json_decode($resultpart, true);
    
    // Add resultarray[] data  into allparticipants[] array
    for($i = 0; $i < count($resultpart['data']); $i++){
      $allparticipants[loopAndFind($allparticipants, 'user_id', $resultpart['data'][$i]['fql_result_set'][0]['uid'])]['name'] = $resultpart['data'][$i]['fql_result_set'][0]['name'];
      $allparticipants[loopAndFind($allparticipants, 'user_id', $resultpart['data'][$i]['fql_result_set'][0]['uid'])]['sex'] = $resultpart['data'][$i]['fql_result_set'][0]['sex'];
    }  
    
    // Counter variables
    $count = 0; 
    $totalcount = 0;  
    $unreadcount = 0;  
    $females = 0;
    $males = 0;
    $others = 0;
    
    // Iterate through threads and add to males, females, other, totalcount, unreadcount
    foreach($reversed as $thread){
      if(count($thread['recipients']) == 1){
        $sex = $allparticipants[loopAndFind($allparticipants, 'user_id', $thread['recipients'][0])]['sex'];
        if($sex == "male"){
          $males = $males + $thread['message_count'];
        } else if($sex == "female"){
          $females = $females + $thread['message_count'];
        } else {
          $others = $others + $thread['message_count'];
        }
      }
      $unreadcount += $thread['unread'];
      $totalcount += $thread['message_count'];
    }
    ?>  
    <script type="text/javascript">
          // Draw the Top Friends Chart first
        google.setOnLoadCallback(drawTopFriends);

        // Top Friends Chart Function
        function drawTopFriends() {
        togglevis('topfriendspiechart', 'genderpiechart', 'messagetypepiechart');
        if(document.getElementById('topfriendspiechart').className == 'notdrawn'){
          var data = google.visualization.arrayToDataTable([
            ['User', 'Messages'],
            <?php
            $topfnum = 0;
            for($j = 0; $j < count($reversed) && $topfnum < 15; $j++)
              if(count($reversed[$j]['recipients']) <= 1){
                $partname = $allparticipants[loopAndFind($allparticipants, 'user_id', $reversed[$j]['recipients'][0])]['name'];
                echo "['".addslashes(!$partname?"Facebook User":$partname)."', ".$reversed[$j]['message_count']."],"; 
                $topfnum++;
                $topfriendsmcount += $reversed[$j]['message_count'];
              }
            echo "['Other', ".(($males + $females + $others) - $topfriendsmcount)."]"; 
            ?>]);
          var formatter = new google.visualization.NumberFormat({pattern: '###,###'});
          formatter.format(data, 1);       
          new google.visualization.PieChart(document.getElementById('topfriendspiechart')).draw(data, {title:"Top <?php echo $topfnum; ?> Facebook Friends", slices: {0: {offset: 0.2}, <?php echo $topfnum; ?>: {color: '#C0C0C0'}}, is3D: true, chartArea:{left:10,top:20,width:"100%",height:"100%"}});
          document.getElementById('topfriendspiechart').className = 'drawn';
        }
        }

        // Gender Chart Function
        function drawGender() {
        togglevis('genderpiechart', 'topfriendspiechart', 'messagetypepiechart');
        if(document.getElementById('genderpiechart').className == 'notdrawn'){
          var data = google.visualization.arrayToDataTable([
            ['Gender', 'Messages'],
            <?php 
            if($males != 0) 
              echo "['Male', ".$males."],";    
            if($females != 0) 
              echo "['Female', ".$females."],";  
            if($others != 0) 
              echo "['Other', ".$others."],";  
            ?>]);
          var formatter = new google.visualization.NumberFormat({pattern: '###,###'});
          formatter.format(data, 1);       
          new google.visualization.PieChart(document.getElementById('genderpiechart')).draw(data, {title:"Messages by Gender", is3D: true, chartArea:{left:10,top:20,width:"100%",height:"100%"}, slices: {0: {offset: 0.2}}});
          document.getElementById('genderpiechart').className = 'drawn';
        }
        }

        // Message Type Chart Function
        function drawMessageType() {
        togglevis('messagetypepiechart', 'genderpiechart', 'topfriendspiechart');
        if(document.getElementById('messagetypepiechart').className == 'notdrawn'){
          var data = google.visualization.arrayToDataTable([
            ['Type', 'Messages'],
            ['Personal', <?php echo $males + $females + $others; ?>],
            <?php if(!($_GET['group'] == '0'))
                echo "['Group', ".($totalcount - ($males + $females + $others))."]";
            ?>]);
          var formatter = new google.visualization.NumberFormat({pattern: '###,###'});
          formatter.format(data, 1);       
          new google.visualization.PieChart(document.getElementById('messagetypepiechart')).draw(data, {title:"Messages By Type", is3D: true, chartArea:{left:10,top:20,width:"100%",height:"100%"}, slices: {0: {offset: 0.2}}});
          document.getElementById('messagetypepiechart').className = 'drawn';
        }
        }
      </script>
    <?php
    // Info box
    echo "<b>App information:</b><br />";
    echo "This application is currently displaying".($_GET['group'] == '0'?" only your personal":" both your personal and group")." messaging statistics sorted by ".($_GET['sort'] == 'time'?"most recent":"message count").".<br />";
    echo "You can change what is shown with the options below. The FQL queries used are in the footer for reference.<br />";
    echo "<br /><b>App Options (<a href=\"".$facebook->getLogoutUrl(array('next' => 'http://zach.ie/fb/?logout='.$currentuser['first_name']))."\" onclick=\"FB.logout();\">logout</a>):</b><br />Sort threads by: ";
    echo $_GET['sort'] != 'time'?"<b>message count</b> - ":"<a href=\"?sort=count".(!empty($_GET['group']) ? "&group=".$_GET['group'] : "")."\">message count</a> - ";
    echo $_GET['sort'] == 'time'?"<b>most recent</b>":"<a href=\"?sort=time".(!empty($_GET['group']) ? "&group=".$_GET['group'] : "")."\">most recent</a>";
    echo "<br />Message type: ";
    echo $_GET['group'] != '0'?"<b>all messages</b> - ":"<a href=\"?group=1".(!empty($_GET['sort']) ? "&sort=".$_GET['sort'] : "")."\">all messages</a> - ";
    echo $_GET['group'] == '0'?"<b>personal only</b>":"<a href=\"?group=0".(!empty($_GET['sort']) ? "&sort=".$_GET['sort'] : "")."\">personal only</a>";
    echo "<br />Graphs: <span onclick=\"drawTopFriends();\" id=\"topfriendspiechartbutt\" class=\"graphselected\">top friends</span> - ";
    echo "<span onclick=\"drawGender();\" id=\"genderpiechartbutt\" class=\"graphlink\">gender</span> - ";
    echo "<span onclick=\"drawMessageType();\" id=\"messagetypepiechartbutt\" class=\"graphlink\">message type</span>";
    echo "<br /><br />";

    // User output
    echo "<b>Messaging Statistics:</b><br />";
    echo "You have <b>".number_format($resultcount ['data'][0]['total_count'])."</b> total message threads";
    echo ($resultcount['data'][0]['unread_count']>0?" (<b>".number_format($resultcount['data'][0]['unread_count'])." unread</b>)":"")." in your ".$resultcount['data'][0]['name']."<br />";
    if(!($_GET['group'] == '0'))
      echo "You have <b>".number_format($males + $females + $others)."</b> personal and <b>".number_format($totalcount - ($males + $females + $others))."</b> group messages.<br />";
    if($males != 0)
      echo "Male: <b>".number_format($males/($males + $females + $others) * 100, 1)."% </b> (<b>".number_format($males)."</b>)";
    if($females != 0)
      echo " Female: <b>".number_format($females/($males + $females + $others) * 100, 1)."%</b> (<b>".number_format($females)."</b>)";
    if($others != 0)
      echo " Other: <b>".number_format($others/($males + $females + $others) * 100, 1)."%</b> (<b>".number_format($others)."</b>)";
    echo "<br /><br />";    
    ?>
    <div id="topfriendspiechart" style="max-width: 600px; height: 320px;display:none;" class="notdrawn"></div>
    <div id="genderpiechart" style="max-width: 600px; height: 320px;display:none;" class="notdrawn"></div>
    <div id="messagetypepiechart" style="max-width: 600px; height: 320px;display:none;" class="notdrawn"></div>
    <?php
    echo "<h3>You have <i>".number_format($totalcount)."</i> ".($_GET['group'] == '0' ? "personal" : "personal and group")." messages";
    echo " (sorted by <i>".($_GET['sort'] == 'time' ? "most recent" : "message count")."</i>)</h3>";

    $topnumber = 10;
    echo "<ol>";
    for($j = 0; $j < count($reversed) && $reversed[$j]['message_count'] > 0; $j++){
      $thread = $reversed[$j];
      $count++;
      // Begin row
      echo "<li>";
      echo "<img ".($thread['recipients'][0]?"src=\"http://graph.facebook.com/".$thread['recipients'][0]."/picture?width=24&height=24\"":"")." style=\"height:24;width:24;vertical-align: middle;\" /> ";
      echo "<a href=\"".($thread['thread_id']?"http://facebook.com/messages/?action=read&tid=id.".$thread['thread_id']."\" target=\"blank\"":"#\"");
      echo " title=\"View this conversation\"><b>".number_format($thread['message_count']), ((count($thread['recipients'])>1)?" group":"")." messages</b></a> with ";
      echo "<b><span title=\"User: ";
      for($i = 0; $i < /*count($thread['recipients'])*/1; $i++){  
        $partname = $allparticipants[loopAndFind($allparticipants, 'user_id', $thread['recipients'][$i])];
        echo $thread['recipients'][$i]."\">";
        // Facebook User if unknown, else just name
        echo ($i>0?", ":"").($partname['name']?$partname['name']:"Facebook User");  
      }
      echo "</span>";

      // Recipients
      if(count($thread['recipients']) > 1){
        echo " <span title=\"Users: ";
        for($i = 1; $i < count($thread['recipients']); $i++)
          echo $thread['recipients'][$i].($i !== count($thread['recipients']) - 1 ? ", " : "");
        echo "\">(and ".(count($thread['recipients']) - 1)." more)</span>";
      }

      // Time
      echo "</b> (<i>Last: ".gmdate("D, j M Y, H:m", $thread['updated_time'])."</i>)</li>";
    }    
    echo "</ol>";
      
    // Show FQL Queries
    echo "<hr /><br /><b>FQL Queries (<a href=\"https://developers.facebook.com/docs/technical-guides/fql\" target=\"_blank\">info</a>):</b><br />";
    echo "Query <b>inbox</b> folder: ";
    echo "<a href=\"".htmlspecialchars($fqlquerycount)."\" target=\"_blank\">SELECT name, unread_count, total_count FROM mailbox_folder</a><br />";
    echo "Query <b>".count($allparticipants)."</b> participants: ";
    echo "<a href=\"".htmlspecialchars($fqlquerypart)."\" target=\"_blank\">SELECT name, sex, uid FROM user WHERE uid = user_id</a><br />";    
    echo "Query <b>".number_format($resultcount ['data'][0]['total_count'])."</b> threads: ";
    echo "<a href=\"".htmlspecialchars($fql)."\" target=\"_blank\">SELECT message_count, updated_time, thread_id, recipients FROM thread</a><br />";  
  } else {
    // No user, so print a link for the user to login      
    // We'll use the current URL as the redirect_uri, so we don't
    // need to specify it here.
    echo "<br /><div style=\"background-color:#D99898;;padding:10px;margin:2px;border:2px solid #960E0E;max-width:500px;text-align:center;height:auto;\">";
    $login_url = $facebook->getLoginUrl( array( 'scope' => 'read_mailbox' ) );
    if(isset($_GET['error'])){
      echo '<h2><font color="red">ERROR!</font> You didn\'t accept the permissions!<br />';
    } else {
      echo '<h2>It looks like you\'re not connected yet.<br />';
    }
    echo 'Please <a href="' . $login_url . '"><u>authenticate</u></a> to continue.</h2>';
    echo "</div>";
  } 
} catch(FacebookApiException $e) {
  // If the user is logged out, you can have a 
  // user ID even though the access token is invalid.
  // In this case, we'll get an exception, so we'll
  // just ask the user to login again here.
echo "<br /><div style=\"background-color:#D99898;;padding:10px;margin:2px;border:2px solid #960E0E;max-width:500px;text-align:center;height:auto;\">";
  $login_url = $facebook->getLoginUrl( array( 'scope' => 'read_mailbox' ) );
  if(isset($_GET['error'])){
  echo '<h2><font color="red">ERROR!</font> You didn\'t accept the permissions!<br />';
  } else {
    echo '<h2>It looks like you\'re not connected yet.<br />';
  }
  echo 'Please <a href="' . $login_url . '"><u>login</u></a> to continue.</h2>';
  echo "</div><!-- ";
  error_log($e->getType());
  error_log($e->getMessage());
  echo " -->";
}   
?>
<br />
<hr />
<i>&copy; <?php echo date("Y"); ?> Zachary - <div class="fb-share-button" data-href="http://zach.ie/fb/messages/" data-type="button_count"></div>
</i>
</body>
</html>
