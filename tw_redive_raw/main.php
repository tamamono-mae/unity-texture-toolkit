<?php
chdir(__DIR__);
//require_once '../UnityBundle.php';
require_once 'resource_fetch.php';
//require_once 'diff_parse.php';
print("root\n");

if (!file_exists('last_version')) {
  $last_version = array('TruthVersion'=>0,'hash'=>'');
} else {
  $last_version = json_decode(file_get_contents('last_version'), true);
}
$logFile = fopen('redive.log', 'a');
function _log($s) {
  global $logFile;
  fwrite($logFile, date('[m/d H:i] ').$s."\n");
  echo $s."\n";
}
global $last_version;
function execQuery($db, $query) {
	//print("execQuery\n");
  $returnVal = [];
  /*if ($stmt = $db->prepare($query)) {
    $result = $stmt->execute();
    if ($result->numColumns()) {
      $returnVal = $result->fetchArray(SQLITE3_ASSOC);
    }
  }*/
  if (!$db) {
    throw new Exception('Invalid db handle');
  }
  $result = $db->query($query);
  if ($result === false) {
    throw new Exception('Failed executing query: '. $query);
  }
  $returnVal = $result->fetchAll(PDO::FETCH_ASSOC);
  return $returnVal;
}

function encodeValue($value) {
	//print("encodeValue\n");
  $arr = [];
  foreach ($value as $key=>$val) {
    $arr[] = '/*'.$key.'*/' . (is_numeric($val) ? $val : ('"'.str_replace('"','\\"',$val).'"'));
  }
  return implode(", ", $arr);
}

function main() {
	print("main\n");


chdir(__DIR__);

//check app ver at 00:00
$appver = file_exists('appver') ? file_get_contents('appver') : '1.1.4';
$itunesid = 1390473317;
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL=>'https://itunes.apple.com/lookup?id='.$itunesid.'&lang=zh_tw&country=tw&rnd='.rand(10000000,99999999),
  CURLOPT_HEADER=>0,
  CURLOPT_RETURNTRANSFER=>1,
  CURLOPT_SSL_VERIFYPEER=>false
));
$appinfo = curl_exec($curl);
curl_close($curl);
if ($appinfo !== false) {
  $appinfo = json_decode($appinfo, true);
  if (!empty($appinfo['results'][0]['version'])) {
    $prevappver = $appver;
    $appver = $appinfo['results'][0]['version'];

    if (version_compare($prevappver,$appver, '<')) {
      file_put_contents('appver', $appver);
      _log('new game version: '. $appver);

      $data = json_encode(array(
        'game'=>'redive',
        'ver'=>$appver,
        'link'=>'https://itunes.apple.com/tw/app/id'.$itunesid,
        'desc'=>$appinfo['results'][0]['releaseNotes']
      ));
	  /*
      $header = [
        'X-GITHUB-EVENT: app_update',
        'X-HUB-SIGNATURE: sha1='.hash_hmac('sha1', $data, file_get_contents(__DIR__.'/../webhook_secret'), false)
      ];
      $curl = curl_init();
      curl_setopt_array($curl, array(
        CURLOPT_URL=>'https://redive.estertion.win/masterdb_subscription/webhook.php',
        CURLOPT_HEADER=>0,
        CURLOPT_RETURNTRANSFER=>1,
        CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_HTTPHEADER=>$header,
        CURLOPT_POST=>1,
        CURLOPT_POSTFIELDS=>$data
      ));
      curl_exec($curl);
      curl_close($curl);
		*/
      // fetch bundle manifest
      $curl = curl_init();
      curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HEADER=>0,
        CURLOPT_SSL_VERIFYPEER=>false
      ));
      curl_setopt($curl, CURLOPT_URL, "https://img-pc.so-net.tw/dl/Bundles/${appver}/Jpn/AssetBundles/iOS/manifest/bdl_assetmanifest");
      $manifest = curl_exec($curl);
      file_put_contents('data/+manifest_bundle.txt', $manifest);
      /*
      chdir('data');
      exec('git add +manifest_bundle.txt');
      exec('git commit -m "bundle manifest v'.$appver.'"');
      chdir(__DIR__);
      */
    }
  }
}

$isWin = DIRECTORY_SEPARATOR === '\\';
$cmdPrepend = $isWin ? '' : 'wine ';
$cmdAppend = $isWin ? '' : ' >/dev/null 2>&1';
//check TruthVersion
/*
$game_start_header = [
  'Host: app.priconne-redive.jp',
  'User-Agent: princessconnectredive/39 CFNetwork/758.4.3 Darwin/15.5.0',
  'PARAM: 527336ff17818cd82e482d5c2cbdea2bc859b316',
  'REGION_CODE: ',
  'BATTLE_LOGIC_VERSION: 2',
  'PLATFORM_OS_VERSION: iPhone OS 9.3.2',
  'Proxy-Connection: keep-alive',
  'DEVICE_ID: BFCC3361-7BCE-4706-A44C-DDF8252669BB',
  'KEYCHAIN: 577247511',
  'GRAPHICS_DEVICE_NAME: Apple A9 GPU',
  'SHORT_UDID: 000973A123;453A538?687=244:861<473>161;683111718423523554437738453547512',
  'DEVICE_NAME: iPhone8,4',
  'BUNDLE_VER: ',
  'LOCALE: Jpn',
  'IP_ADDRESS: 192.168.0.110',
  'SID: bc41c108715c98f0cae62f6f94a990c2',
  'X-Unity-Version: 2017.1.2p2',
  'PLATFORM: 1',
  'Connection: keep-alive',
  'Accept-Language: en-us',
  'APP_VER: '.$appver,
  'RES_VER: 10002700',
  'Accept: *\/*',
  'Content-Type: application/x-www-form-urlencoded',
  'Accept-Encoding: gzip, deflate',
  'DEVICE: 1'
];
global $curl;
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://app.priconne-redive.jp/check/game_start',
  CURLOPT_HTTPHEADER=>$game_start_header,
  CURLOPT_HEADER=>false,
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_CONNECTTIMEOUT=>3,
  CURLOPT_SSL_VERIFYPEER=>false,
  CURLOPT_POST=>true,
  CURLOPT_POSTFIELDS=>'wN4AAjnMd5CaTfLGxSL+rUQzafWYMcXIhaUZxKbsOCuR64ldQuDc0mGuXMU72S2nYcOBLpJjWXNeoj59TV2mXcIKl/YXMxtsHHuKKdBCIOujxxJHW79q3jQ3F2LRg8iDTN2EGo+1NLCHqyDD8kt4iYT47D5gJa3HM0d4+n6X/U0/6hB+3utmirBPHRZJ5hVZZaSXbuzefQSbgrQ=',
  CURLOPT_PROXY=>file_get_contents('currentproxy.txt'),
  CURLOPT_HTTPPROXYTUNNEL=>true,
//  CURLOPT_PROXY=>'vultr.biliplus.com:87',
//  CURLOPT_PROXYTYPE=>CURLPROXY_SOCKS5
));
$response = curl_exec($curl);
$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);
if ($code == 500 || $code == 0 || ($code == 200 && strlen($response) == 0)) {
  _log('proxy failed: '. $code);
  if (autoProxy()) {
    return main();
  } else {
    exit;
  }
}
if ($response === false) {
  _log('error fetching TruthVersion');
  return;
}
$response = base64_decode($response);
file_put_contents('resp.data', $response);
system($cmdPrepend.'Coneshell_call.exe -unpack-edcadba12a674a089107d8065a031742 resp.data resp.json'.$cmdAppend);
unlink('resp.data');
if (!file_exists('resp.json')) {
  _log('Unpack response failed');
  return;
}
$response = json_decode(file_get_contents('resp.json'), true);
unlink('resp.json');

//print_r($response);
//exit;
if (!isset($response['data_headers']['required_res_ver'])) {
  if (isset($response['data_headers']['result_code']) && $response['data_headers']['result_code'] == 101) {
    // maintenance, wait for 00:30/30:30
    $now = time();
    $until = $now - $now % 1800 + 1830;
    $wait = $until - $now;
    if ($wait > 600) {
      _log("maintaining, exit");
      return;
    }
    _log("maintaining, wait for ${wait} secs now");
    sleep($wait);
    return main();
  }
  _log('invalid response: '. json_encode($response));
  return;
}
$TruthVersion = $response['data_headers']['required_res_ver'];
*/

//if (file_exists('stop_cron')) return;

// guess latest res_ver
if (!file_exists('last_version')) {
  $last_version = array('TruthVersion'=>0,'hash'=>'');
} else {
  $last_version = json_decode(file_get_contents('last_version'), true);
}
global $curl;
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_HEADER=>0,
  CURLOPT_SSL_VERIFYPEER=>false
));
$TruthVersion = $last_version['TruthVersion'];
$current_ver = $TruthVersion|0;
print("current_ver=".$current_ver."\n");
for ($i=1; $i<=500; $i++) {
  $guess = str_pad($current_ver + $i * 1,8,'0',STR_PAD_LEFT);
  //print("guess=".$guess."\n");
  curl_setopt($curl, CURLOPT_URL, 'https://img-pc.so-net.tw/dl/Resources/'.$guess.'/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest');
  curl_exec($curl);
  $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  if ($code == 200) {
    $TruthVersion = $guess.'';
	print("TruthVersion=".$TruthVersion."\n");
    break;
  }
}
if ($TruthVersion == $last_version['TruthVersion']) {
  for ($i=0; $i<=20; $i++) {
    $guess = str_pad(round($current_ver,-3,PHP_ROUND_HALF_DOWN) + 1000 + $i * 1,8,'0',STR_PAD_LEFT);
    //print("guess=".$guess."\n");
    curl_setopt($curl, CURLOPT_URL, 'https://img-pc.so-net.tw/dl/Resources/'.$guess.'/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest');
    curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($code == 200) {
      $TruthVersion = $guess.'';
  	print("TruthVersion=".$TruthVersion."\n");
      break;
    }
  }
}

curl_close($curl);
if ($TruthVersion == $last_version['TruthVersion']) {
  _log('no update found');
  return;
}
$last_version['TruthVersion'] = $TruthVersion;
_log("TruthVersion: ${TruthVersion}");
file_put_contents('data/TruthVersion.txt', $TruthVersion."\n");

//$TruthVersion = '00006000';
$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL=>'https://img-pc.so-net.tw/dl/Resources/'.$TruthVersion.'/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest',
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_HEADER=>0,
  CURLOPT_SSL_VERIFYPEER=>false
));
//$manifest = file_get_contents('history/'.$TruthVersion);

// fetch all manifest & save
$manifest = curl_exec($curl);
file_put_contents('data/+manifest_manifest.txt', $manifest);
foreach (explode("\n", trim($manifest)) as $line) {
  list($manifestName) = explode(',', $line);
  if ($manifestName == 'manifest/soundmanifest') {
    continue;
  } else {
    curl_setopt($curl, CURLOPT_URL, 'https://img-pc.so-net.tw/dl/Resources/'.$TruthVersion.'/Jpn/AssetBundles/iOS/'.$manifestName);
    $manifest = curl_exec($curl);
    file_put_contents('data/+manifest_'.substr($manifestName, 9, -14).'.txt', $manifest);
  }
}
curl_setopt($curl, CURLOPT_URL, 'https://img-pc.so-net.tw/dl/Resources/'.$TruthVersion.'/Jpn/Sound/manifest/sound2manifest');
$manifest = curl_exec($curl);
file_put_contents('data/+manifest_sound.txt', $manifest);
curl_setopt($curl, CURLOPT_URL, 'https://img-pc.so-net.tw/dl/Resources/'.$TruthVersion.'/Jpn/Movie/PC/High/manifest/moviemanifest');
$manifest = curl_exec($curl);
file_put_contents('data/+manifest_movie.txt', $manifest);
curl_setopt($curl, CURLOPT_URL, 'https://img-pc.so-net.tw/dl/Resources/'.$TruthVersion.'/Jpn/Movie/PC/Low/manifest/moviemanifest');
$manifest = curl_exec($curl);
file_put_contents('data/+manifest_movie_low.txt', $manifest);

$manifest = file_get_contents('data/+manifest_masterdata.txt');
$manifest = array_map(function ($i){ return explode(',', $i); }, explode("\n", $manifest));
echo "cdb check\n";
foreach ($manifest as $entry) {
  if ($entry[0] === 'a/masterdata_master.unity3d') { $manifest = $entry; break; }
}
echo "cdb ok\n";
if ($manifest[0] !== 'a/masterdata_master.unity3d') {
  _log('masterdata_master.unity3d not found');
  //file_put_contents('stop_cron', '');
  file_put_contents('last_version', json_encode($last_version));
  chdir('data');
  exec('git add TruthVersion.txt +manifest_*.txt');
  do_commit($TruthVersion, NULL, ' (no master db)');
  checkAndUpdateResource($TruthVersion);
  return;
}
$bundleHash = $manifest[1];
$bundleSize = $manifest[3]|0;
if ($last_version['hash'] == $bundleHash) {
  _log("Same hash as last version ${bundleHash}");
  file_put_contents('last_version', json_encode($last_version));
  /*
  chdir('data');
  exec('git add TruthVersion.txt +manifest_*.txt');
  do_commit($TruthVersion);
  */
  return;
}
$last_version['hash'] = $bundleHash;
//download bundle
_log("downloading cdb for TruthVersion ${TruthVersion}, hash: ${bundleHash}, size: ${bundleSize}");
$bundleFileName = "master_${TruthVersion}.unity3d";
curl_setopt_array($curl, array(
  CURLOPT_URL=>'https://img-pc.so-net.tw/dl/pool/AssetBundles/'.substr($bundleHash,0,2).'/'.$bundleHash,
  CURLOPT_RETURNTRANSFER=>true
));
$bundle = curl_exec($curl);
//curl_close($curl);
$downloadedSize = strlen($bundle);
$downloadedHash = md5($bundle);
if ($downloadedSize != $bundleSize || $downloadedHash != $bundleHash) {
  _log("download failed, received hash: ${downloadedHash}, received size: ${downloadedSize}");
  return;
}

//extract db
_log('extracting bundle');
$bundle = new MemoryStream($bundle);
$assetsList = extractBundle($bundle);
unset($bundle);

$asset = new AssetFile($assetsList[0]);
foreach ($asset->preloadTable as &$item) {
  if ($item->typeString == 'TextAsset') {
    $item = new TextAsset($item, true);
    if($item->name === 'master') {
      file_put_contents('redive.db', $item->data);
      //file_put_contents('redive.db.br', brotli_compress($item->data, 9));
      break;
    }
  }
}

$asset->__desctruct();
unset($asset);
unlink($assetsList[0]);
rename('redive.db', "${TruthVersion}_redive.db");
$dbData = file_get_contents("${TruthVersion}_redive.db");
exec("p7zip ${TruthVersion}_redive.db");
exec("mv ${TruthVersion}_redive.db.7z mdb/${TruthVersion}_redive.7z");

checkAndUpdateManifest($TruthVersion,$appver);
checkAndUpdateResource($TruthVersion,$appver);
file_put_contents('last_version', json_encode($last_version));

}

main();
