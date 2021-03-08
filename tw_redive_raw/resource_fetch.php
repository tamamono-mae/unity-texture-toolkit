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
function mkpath($dirpath){
  if(!is_dir($dirpath))
    exec("mkdir -p ${dirpath}");
}

$cacheHashDb = new PDO('sqlite:'.__DIR__.'/cacheHash.db');
function shouldUpdate($name, $hash, $OS) {
  global $cacheHashDb;
  $chkHashStmt = $cacheHashDb->prepare("SELECT hash FROM cacheHash${OS} WHERE res=? ORDER BY version DESC");
  $chkHashStmt->execute([$name]);
  $row = $chkHashStmt->fetch();
  return !(!empty($row) && $row['hash'] == $hash);
}
function setHashCached($name, $hash, $version, $OS) {
  global $cacheHashDb;
  $setHashStmt = $cacheHashDb->prepare("REPLACE INTO cacheHash${OS} (res,hash,version) VALUES (?,?,?)");
  $setHashStmt->execute([$name, $hash, $version]);
}

function shouldUpdateManifest($name, $hash, $version, $OS) {
  global $cacheHashDb;
  $chkHashStmtm = $cacheHashDb->prepare("SELECT hash FROM manifestHash${OS} WHERE res=? AND version=? ORDER BY version DESC");
  $chkHashStmtm->execute([$name,$version]);
  $row = $chkHashStmtm->fetch();
  return !(!empty($row) && $row['hash'] == $hash);
}
function setHashCachedManifest($name, $hash, $version, $OS) {
  global $cacheHashDb;
  $setHashStmtm = $cacheHashDb->prepare("REPLACE INTO manifestHash${OS} (res,hash,version) VALUES (?,?,?)");
  $setHashStmtm->execute([$name, $hash, $version]);
}

define('RESOURCE_PATH_PREFIX', 'storage/');
define('SOURCE_PATH_PREFIX', "https://img-pc.so-net.tw/");

function checkSubResource($manifest, $appendsrc, $appenddst , $version, $OS) {
  global $curl;
  foreach ($manifest as $name => $info) {
    if (shouldUpdate($name, $info['hash'], $OS)) {
      curl_setopt_array($curl, array(
        CURLOPT_URL=>SOURCE_PATH_PREFIX.$appendsrc.substr($info['hash'],0,2).'/'.$info['hash'],
      ));
      $bundleData = curl_exec($curl);
      $remoteTime = curl_getinfo($curl, CURLINFO_FILETIME);
      $remoteTime = time();
      if (md5($bundleData) != $info['hash']) {
        _log('download failed  '.$name);
        continue;
      }
      $savePath = RESOURCE_PATH_PREFIX.$appenddst.substr($info['hash'],0,2).'/'.$info['hash'];
      mkpath(dirname($savePath));
      file_put_contents($savePath, $bundleData);
      touch($savePath, $remoteTime);
      //^^^ Because we cannot get filetime from server!
      _log("Copied: ".$name.", hash: ".$info['hash']);
      unset($bundleData);
      setHashCached($name, $info['hash'], $version, $OS);
    }
  }
}


function checkAndUpdateResource($TruthVersion,$appver) {
  global $resourceToExport;
  global $curl;
  chdir(__DIR__);

  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL=>SOURCE_PATH_PREFIX.'dl/Resources/'.$TruthVersion.'/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest',
    CURLOPT_CONNECTTIMEOUT=>5,
    CURLOPT_ENCODING=>'gzip',
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HEADER=>0,
    CURLOPT_FILETIME=>true,
    CURLOPT_SSL_VERIFYPEER=>false
  ));
  
  foreach(array("iOS","Android","Windows") as $OS){
    curl_setopt($curl, CURLOPT_URL, SOURCE_PATH_PREFIX.'dl/Resources/'.$TruthVersion.'/Jpn/AssetBundles/'.$OS.'/manifest/manifest_assetmanifest');
    $manifest = curl_exec($curl);
    foreach (explode("\n", trim($manifest)) as $line) {
      list($manifestName,$srcHash) = explode(',', $line);
	    if ($manifestName == 'manifest/soundmanifest')
        continue;
      curl_setopt($curl, CURLOPT_URL, SOURCE_PATH_PREFIX.'dl/Resources/'.$TruthVersion.'/Jpn/AssetBundles/'.$OS.'/'.$manifestName);
      $submanifest = curl_exec($curl);
      if (md5($submanifest) != $srcHash) {
        _log('download failed  '.$name);
        continue;
      }
      $submanifest = parseManifest($submanifest);
      checkSubResource($submanifest, 'dl/pool/AssetBundles/', 'dl/pool/AssetBundles/', $TruthVersion, $OS);

    }
    unset($submanifest);

    curl_setopt($curl, CURLOPT_URL, SOURCE_PATH_PREFIX."dl/Bundles/${appver}/Jpn/AssetBundles/".$OS."/manifest/bdl_assetmanifest");
    $manifest = curl_exec($curl);
    $manifest = parseManifest($manifest);
    checkSubResource($manifest, 'dl/pool/AssetBundles/', 'dl/pool/AssetBundles/', $appver, $OS);
    unset($manifest,$line);

  }
  unset($OS);

  $OS = "Sound";
  curl_setopt($curl, CURLOPT_URL, SOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Sound/manifest/sound2manifest");
  $manifest = curl_exec($curl);
  $manifest = parseManifest($manifest);
  checkSubResource($manifest, 'dl/pool/Sound/', 'dl/pool/Sound/', $TruthVersion, $OS);
  unset($manifest);
  $OS = "Movie";
  curl_setopt($curl, CURLOPT_URL, SOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Movie/PC/High/manifest/moviemanifest");
  $manifest = curl_exec($curl);
  $manifest = parseManifest($manifest);
  checkSubResource($manifest, 'dl/pool/Movie/', 'dl/pool/Movie/', $TruthVersion, $OS);
  unset($manifest);
  $OS = "MovieLite";
  curl_setopt($curl, CURLOPT_URL, SOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Movie/PC/Low/manifest/moviemanifest");
  $manifest = curl_exec($curl);
  $manifest = parseManifest($manifest);
  checkSubResource($manifest, 'dl/pool/Movie/', 'dl/pool/Movie_lite/', $TruthVersion, $OS);
  unset($manifest);


}

function checkAndUpdateManifest($TruthVersion,$appver){
  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL=> SOURCE_PATH_PREFIX.'dl/Resources/'.$TruthVersion.'/Jpn/AssetBundles/iOS/manifest/manifest_assetmanifest',
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HEADER=>0,
    CURLOPT_SSL_VERIFYPEER=>false
  ));

  foreach(array("iOS","Android","Windows") as $OS){
    curl_setopt($curl, CURLOPT_URL, SOURCE_PATH_PREFIX.'dl/Resources/'.$TruthVersion.'/Jpn/AssetBundles/'.$OS.'/manifest/manifest_assetmanifest');
    $manifest = curl_exec($curl);
    if(shouldUpdateManifest('manifest/manifest_assetmanifest', md5($manifest), $TruthVersion, $OS)){
      $savePath = RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/AssetBundles/".$OS."/manifest/manifest_assetmanifest";
      mkpath(dirname($savePath));
      file_put_contents($savePath, $manifest);
      touch($savePath, time());
      //^^^ Because we cannot get filetime from server!
      _log("Copied: manifest/manifest_assetmanifest, hash: ".md5($manifest));
      setHashCachedManifest("manifest/manifest_assetmanifest", md5($manifest), $TruthVersion, $OS);
    }

    $savePath = RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/AssetBundles/".$OS."/";
    mkpath($savePath."manifest");
    foreach (explode("\n", trim($manifest)) as $line) {
      list($manifestName,$srcHash) = explode(',', $line);
      if(shouldUpdateManifest($manifestName, $srcHash, $TruthVersion, $OS) || md5_file($savePath.$manifestName) != $srcHash){
        curl_setopt($curl, CURLOPT_URL, SOURCE_PATH_PREFIX.'dl/Resources/'.$TruthVersion.'/Jpn/AssetBundles/'.$OS.'/'.$manifestName);
        $manifest = curl_exec($curl);
        file_put_contents($savePath.$manifestName, $manifest);
        touch($savePath.$manifestName, time());
        if(md5_file($savePath.$manifestName) == $srcHash){
          _log("Copied: ${manifestName}, hash: ${srcHash}");
        }else{
          _log("Fail: ${manifestName}, hash: ${srcHash}");
          continue;
        }
        setHashCachedManifest($manifestName, $srcHash, $TruthVersion, $OS);
      }
    }

    curl_setopt($curl, CURLOPT_URL, SOURCE_PATH_PREFIX."dl/Bundles/${appver}/Jpn/AssetBundles/".$OS."/manifest/bdl_assetmanifest");
    $manifest = curl_exec($curl);
    if(shouldUpdateManifest('manifest/bdl_assetmanifest', md5($manifest), $appver, $OS)){
      $savePath = RESOURCE_PATH_PREFIX."dl/Bundles/${appver}/Jpn/AssetBundles/".$OS."/manifest/bdl_assetmanifest";
      mkpath(dirname($savePath));
      file_put_contents($savePath, $manifest);
      touch($savePath, time());
      _log("Copied: manifest/bdl_assetmanifest, hash: ".md5($manifest));
      setHashCachedManifest("manifest/bdl_assetmanifest", md5($manifest), $appver, $OS);
    }

    unset($manifest,$line);
  }

  unset($OS);
  $OS = "";
  curl_setopt($curl, CURLOPT_URL, SOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Sound/manifest/sound2manifest");
  $manifest = curl_exec($curl);
  if(shouldUpdateManifest('manifest/sound2manifest', md5($manifest), $TruthVersion, $OS)){
    $savePath = RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Sound/manifest/sound2manifest";
    mkpath(dirname($savePath));
    file_put_contents($savePath, $manifest);
    touch($savePath, time());
    _log("Copied: manifest/sound2manifest, hash: ".md5($manifest));
    setHashCachedManifest("manifest/sound2manifest", md5($manifest), $TruthVersion, $OS);
  }

  curl_setopt($curl, CURLOPT_URL, SOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Movie/PC/High/manifest/moviemanifest");
  $manifest = curl_exec($curl);
  if(shouldUpdateManifest('High/manifest/moviemanifest', md5($manifest), $TruthVersion, $OS)){
    $savePath = RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Movie/PC/High/manifest/moviemanifest";
    mkpath(dirname($savePath));
    file_put_contents($savePath, $manifest);
    touch($savePath, time());
    _log("Copied: High/manifest/moviemanifest, hash: ".md5($manifest));
    setHashCachedManifest("High/manifest/moviemanifest", md5($manifest), $TruthVersion, $OS);
  }

  curl_setopt($curl, CURLOPT_URL, SOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Movie/PC/Low/manifest/moviemanifest");
  $manifest = curl_exec($curl);
  if(shouldUpdateManifest('Low/manifest/moviemanifest', md5($manifest), $TruthVersion, $OS)){
    $savePath = RESOURCE_PATH_PREFIX."dl/Resources/".$TruthVersion."/Jpn/Movie/PC/Low/manifest/moviemanifest";
    mkpath(dirname($savePath));
    file_put_contents($savePath, $manifest);
    touch($savePath, time());
    _log("Copied: Low/manifest/moviemanifest, hash: ".md5($manifest));
    setHashCachedManifest("Low/manifest/moviemanifest", md5($manifest), $TruthVersion, $OS);
  }
}
