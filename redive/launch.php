<?php
chdir(__DIR__);
print("root\n");

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
  //chdir('redive');
  exec('php main.php 2>&1 | tee shell-logs/$(date +"%FT-%H-%M-%S").txt');
  //chdir(__DIR__);
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
