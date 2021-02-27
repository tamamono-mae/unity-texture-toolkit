<?php
chdir(__DIR__);
print("root\n");

//Check new redive (JP) version
if (!file_exists('./redive/last_version')) {
  $last_version = array('TruthVersion'=>0,'hash'=>'');
} else {
  $last_version = json_decode(file_get_contents('./redive/last_version'), true);
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
for ($i=1; $i<=20; $i++) {
  $guess = $current_ver + $i * 10;
  print("guess=".$guess."\n");
  curl_setopt($curl, CURLOPT_URL, 'http://prd-priconne-redive.akamaized.net/dl/Resources/'.$guess.'/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest');
  curl_exec($curl);
  $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  if ($code == 200) {
    $TruthVersion = $guess.'';
  print("TruthVersion=".$TruthVersion."\n");
    break;
  }
}

curl_close($curl);
if ($TruthVersion == $last_version['TruthVersion']) {
  print("no update found\n");
  //return;
}
else{
  print("update found\n");
  chdir('redive');
  exec('php main.php 2>&1 | tee shell-logs/$(date +"%FT-%H-%M-%S").txt');
  chdir(__DIR__);
}

//Check new tw_redive (TW) version
if (!file_exists('./tw_redive/last_version')) {
  $last_version = array('TruthVersion'=>0,'hash'=>'');
} else {
  $last_version = json_decode(file_get_contents('./tw_redive/last_version'), true);
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
for ($i=1; $i<=20; $i++) {
  $guess = str_pad($current_ver + $i * 1,8,'0',STR_PAD_LEFT);
  print("guess=".$guess."\n");
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
    $guess = str_pad(round($current_ver,3,PHP_ROUND_HALF_DOWN) + 1000 + $i * 1,8,'0',STR_PAD_LEFT);
    print("guess=".$guess."\n");
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
  print("no update found\n");
  //return;
}
else{
  print("update found\n");
  chdir('tw_redive');
  exec('php main.php 2>&1 | tee shell-logs/$(date +"%FT-%H-%M-%S").txt');
  chdir(__DIR__);
  chdir('redive100');
  exec('php main.php 2>&1 | tee shell-logs/$(date +"%FT-%H-%M-%S").txt');
  chdir(__DIR__);
  chdir('redive101');
  exec('php main.php 2>&1 | tee shell-logs/$(date +"%FT-%H-%M-%S").txt');
  chdir(__DIR__);
}
