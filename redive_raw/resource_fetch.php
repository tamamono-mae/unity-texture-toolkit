<?php

require_once '../UnityAsset.php';

function parseManifest($manifest) {
  $manifest = new MemoryStream($manifest);
  $list=[];
  while (!empty($line = $manifest->line)) {
    list($name, $hash, $stage, $size) = explode(',', $line);
    $list[$name] = [
      'hash' =>$hash,
      'size' =>$size
    ];
  }
  unset($manifest);
  return $list;
}
function parseRcloneMD5($shellMsg) {
  $list=[];
  foreach ($shellMsg as $line){
    list($hash, $name) = explode('  ', $line);
    $list[$name] = $hash;
  }
  return $list;
}
$cacheHashDb = new PDO('sqlite:'.__DIR__.'/cacheHash.db');
$chkHashStmt = $cacheHashDb->prepare('SELECT hash FROM cacheHash WHERE res=? AND version=? ORDER BY version DESC');
function shouldUpdate($name, $hash, $version) {
  global $chkHashStmt;
  $chkHashStmt->execute([$name,$version]);
  $row = $chkHashStmt->fetch();
  return !(!empty($row) && $row['hash'] == $hash);
}
$setHashStmt = $cacheHashDb->prepare('REPLACE INTO cacheHash (res,hash,version) VALUES (?,?,?)');
function setHashCached($name, $hash, $version) {
  global $setHashStmt;
  $setHashStmt->execute([$name, $hash, $version]);
}

define('RESOURCE_PATH_PREFIX', 'gs:pcr-raw/');
define('SOURCE_PATH_PREFIX', 'prd:');

function checkAndUpdateResource($TruthVersion) {
  global $resourceToExport;
  global $curl;
  chdir(__DIR__);
  curl_setopt_array($curl, array(
    CURLOPT_URL=>'https://prd-priconne-redive.akamaized.net/dl/Resources/'.$TruthVersion.'/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest',
    CURLOPT_CONNECTTIMEOUT=>5,
    CURLOPT_ENCODING=>'gzip',
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HEADER=>0,
    CURLOPT_FILETIME=>true,
    CURLOPT_SSL_VERIFYPEER=>false
  ));
  $manifest = curl_exec($curl);
  exec("rclone copy ".SOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest ".RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/AssetBundles/iOS/manifest/");
  $manifest = parseManifest($manifest);
}

function checkAndUpdateManifest($TruthVersion){
  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL=>'https://prd-priconne-redive.akamaized.net/dl/Resources/'.$TruthVersion.'/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest',
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HEADER=>0,
    CURLOPT_SSL_VERIFYPEER=>false
  ));
  exec("rclone md5sum ".RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest",$dstHashList);
  list($dstHash) = explode('  ', $dstHashList[0]);
  if(shouldUpdate('manifest/manifest_assetmanifest', $dstHash, $TruthVersion)){
    exec("rclone copy ".SOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest ".RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/AssetBundles/iOS/manifest/",$copyMsg);
    exec("rclone md5sum ".RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest",$dstHashList);
    list($dstHash) = explode('  ', $dstHashList[0]);
    if(strlen($copyMsg[0]) == 0){
      echo "Copied manifest/manifest_assetmanifest, hash: ${dstHash}\n";
      setHashCached("manifest/manifest_assetmanifest", $dstHash, $TruthVersion);
    }else{
      echo "Copy fail, name: manifest/manifest_assetmanifest\n${shellMsg}\n";
    }
  }
  unset($dstHashList);
  // fetch all manifest & save
  $manifest = curl_exec($curl);

  //file_put_contents('data/+manifest_manifest.txt', $manifest);
  exec("rclone md5sum ".RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/AssetBundles/iOS/manifest",$dstHashList);
  $hashList = parseRcloneMD5($dstHashList);
  //echo $hashList["consttext_assetmanifest"];
  foreach (explode("\n", trim($manifest)) as $line) {
    list($manifestName,$srcHash) = explode(',', $line);
    if(shouldUpdate($manifestName, $hashList[substr($manifestName, 9)], $TruthVersion)){
      exec("rclone copy ".SOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/AssetBundles/iOS/".$manifestName." ".RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/AssetBundles/iOS/manifest/",$copyMsg);
      exec("rclone md5sum ".RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/AssetBundles/iOS/".$manifestName,$dstHashList);
      list($dstHash) = explode('  ', $dstHashList[0]);
      if((strlen($copyMsg[0]) == 0) && ($dstHash == $srcHash)){
        echo "Copied ${manifestName}, hash: ${dstHash}\n";
      }else{
        echo "Copy fail, name: ${manifestName} hash: $srcHash\n";
        continue;
      }

      setHashCached($manifestName, $srcHash, $TruthVersion);
    }
    unset($dstHashList);
  }

  exec("rclone md5sum ".RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Sound/manifest/sound2manifest",$dstHashList);
  list($dstHash) = explode('  ', $dstHashList[0]);
  if(shouldUpdate('manifest/sound2manifest', $dstHash, $TruthVersion)){
    exec("rclone copy ".SOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Sound/manifest/sound2manifest ".RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Sound/manifest/",$copyMsg);
    exec("rclone md5sum ".RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Sound/manifest/sound2manifest",$dstHashList);
    list($dstHash) = explode('  ', $dstHashList[0]);
    if(strlen($copyMsg[0]) == 0){
      echo "Copied manifest/sound2manifest, hash: ${dstHash}\n";
      setHashCached("manifest/sound2manifest", $dstHash, $TruthVersion);
    }else{
      echo "Copy fail, name: manifest/sound2manifest\n${shellMsg}\n";
    }
  }
  unset($dstHashList);

  exec("rclone md5sum ".RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Movie/PC/High/manifest/moviemanifest",$dstHashList);
  list($dstHash) = explode('  ', $dstHashList[0]);
  if(shouldUpdate('High/manifest/moviemanifest', $dstHash, $TruthVersion)){
    exec("rclone copy ".SOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Movie/PC/High/manifest/moviemanifest ".RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Movie/PC/High/manifest/",$copyMsg);
    exec("rclone md5sum ".RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Movie/PC/High/manifest/moviemanifest",$dstHashList);
    list($dstHash) = explode('  ', $dstHashList[0]);
    if(strlen($copyMsg[0]) == 0){
      echo "Copied High/manifest/moviemanifest, hash: ${dstHash}\n";
      setHashCached("High/manifest/moviemanifest", $dstHash, $TruthVersion);
    }else{
      echo "Copy fail, name: High/manifest/moviemanifest\n${shellMsg}\n";
    }
  }
  unset($dstHashList);
  exec("rclone md5sum ".RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Movie/PC/Low/manifest/moviemanifest",$dstHashList);
  list($dstHash) = explode('  ', $dstHashList[0]);
  if(shouldUpdate('Low/manifest/moviemanifest', $dstHash, $TruthVersion)){
    exec("rclone copy ".SOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Movie/PC/Low/manifest/moviemanifest ".RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Movie/PC/Low/manifest/",$copyMsg);
    exec("rclone md5sum ".RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Movie/PC/Low/manifest/moviemanifest",$dstHashList);
    list($dstHash) = explode('  ', $dstHashList[0]);
    if(strlen($copyMsg[0]) == 0){
      echo "Copied Low/manifest/moviemanifest, hash: ${dstHash}\n";
      setHashCached("Low/manifest/moviemanifest", $dstHash, $TruthVersion);
    }else{
      echo "Copy fail, name: Low/manifest/moviemanifest\n${shellMsg}\n";
    }
  }
  unset($dstHashList);

}
