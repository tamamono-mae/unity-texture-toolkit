<?php
chdir(__DIR__);
print("root\n");

function main() {
  //Check new tw_redive (TW) version
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
  print(date('[Y/m/d H:i] ')."Current ver=".str_pad($current_ver,8,'0',STR_PAD_LEFT)." searching...\n");
  for ($i=1; $i<=500; $i++) {
    $guess = str_pad($current_ver + $i * 1,8,'0',STR_PAD_LEFT);
    //print("guess=".$guess."\n");
    curl_setopt($curl, CURLOPT_URL, 'https://img-pc.so-net.tw/dl/Resources/'.$guess.'/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest');
    curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($code == 200) {
      $TruthVersion = $guess.'';
  	print(date('[Y/m/d H:i] ')."TruthVersion=".$TruthVersion."\n");
      break;
    }
  }
  if ($TruthVersion == $last_version['TruthVersion']) {
    for ($i=0; $i<=20; $i++) {
      $guess = str_pad(round($current_ver,3,PHP_ROUND_HALF_DOWN) + 1000 + $i * 1,8,'0',STR_PAD_LEFT);
      //print("guess=".$guess."\n");
      curl_setopt($curl, CURLOPT_URL, 'https://img-pc.so-net.tw/dl/Resources/'.$guess.'/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest');
      curl_exec($curl);
      $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      if ($code == 200) {
        $TruthVersion = $guess.'';
    	print(date('[Y/m/d H:i] ')."TruthVersion=".$TruthVersion."\n");
        break;
      }
    }
  }

  curl_close($curl);
  if ($TruthVersion == $last_version['TruthVersion']) {
    print(date('[Y/m/d H:i] ')."no update found\n");
    //return;
  }
  else{
    print("update found\n");
    //chdir('tw_redive');
    exec('php main.php 2>&1 | tee shell-logs/$(date +"%FT-%H-%M-%S").txt');

  }
}
while(1){
  if(time()/60%10 == 0) main();
  sleep(30);
}

/*
if (!file_exists('.lock')) {
  main();
}*/
