<?php
chdir(__DIR__);
print("root\n");
$syncPaths = json_decode(file_get_contents('../syncPaths.json'), true);

function main() {
//Check new redive (JP) version
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
print(date('[Y/m/d H:i] ')."Current_ver=".$current_ver." searching...\n");
for ($i=1; $i<=20; $i++) {
  $guess = $current_ver + $i * 10;
  //print("guess=".$guess."\n");
  curl_setopt($curl, CURLOPT_URL, 'http://prd-priconne-redive.akamaized.net/dl/Resources/'.$guess.'/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest');
  curl_exec($curl);
  $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  if ($code == 200) {
    $TruthVersion = $guess.'';
    print(date('[Y/m/d H:i] ')."TruthVersion=".$TruthVersion."\n");
    break;
  }
}

curl_close($curl);
if ($TruthVersion == $last_version['TruthVersion']) {
  print(date('[Y/m/d H:i] ')."no update found\n");
  //return;
}
else{
  print("update found\n");
  exec('php main.php 2>&1 | tee shell-logs/$(date +"%FT-%H-%M-%S").txt');
  exec('rclone copy '.$syncPaths['jpLocal'].' '.$syncPaths['jpRemote'].' -P --log-file=shell-logs/rc_$(date +"%FT-%H-%M-%S").txt');
}

}

while(1){
  if(time()/60%5 == 0) main();
  sleep(30);
}

/*
if (!file_exists('.lock')) {
  main();
}*/
