<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function callAPI($method, $url, $data){
   $curl = curl_init();
   curl_setopt($curl, CURLOPT_UNIX_SOCKET_PATH, "/var/run/docker.sock");
   curl_setopt($curl, CURLOPT_VERBOSE, true);
   switch ($method){
      case "POST":
         curl_setopt($curl, CURLOPT_POST, 1);
         if ($data)
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
         break;
      case "PUT":
         curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
         if ($data)
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
         break;
      default:
         if ($data)
            $url = sprintf("%s?%s", $url, http_build_query($data));
   }
   // OPTIONS:
   curl_setopt($curl, CURLOPT_URL, $url);
   curl_setopt($curl, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json'
   ));
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
   // EXECUTE:
   $result = curl_exec($curl);
   if(!$result){die("Connection Failure");}
   curl_close($curl);
   return $result;
}
$get_data = callAPI("GET","http://localhost/v1.40/containers/json", false);
$response = json_decode($get_data, true);
foreach ($response as $value){
  $name = ltrim($value["Names"][0], '/');
  #var_dump($value);print("<br>");
  if($value["Image"] == "storjlabs/storagenode:latest"){
    foreach ($value["Ports"] as $portarr){
	    if($portarr["PrivatePort"] == 14002){
		    $port = $portarr["PublicPort"];
	    }
    }
    print("<a href='http://".$_ENV["DOCKER_HOST_HOSTNAME"].":".$port."'>".$name."</a>");
    print(" ".$value["State"]." ".$value["Status"]."<br>");
  }
  if($value["Image"] == "grafana/grafana"){
    print("<a href='http://".$_ENV["DOCKER_HOST_HOSTNAME"].":".$value["Ports"][0]["PublicPort"]."'>".$name."</a>");
    print(" ".$value["State"]." ".$value["Status"]."<br>");
  }
  if($value["Image"] == "prom/prometheus"){
    print("<a href='http://".$_ENV["DOCKER_HOST_HOSTNAME"].":".$value["Ports"][0]["PublicPort"]."/targets?search='>".$name."</a>");
    print(" ".$value["State"]." ".$value["Status"]."<br>");
  }
}
