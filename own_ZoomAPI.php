<?php
function getZoom(string $url) : string
{
    
    require_once '/var/www/html/app/moodle/own_ZoomAPIToken.php';
    $token = generateTokenOAuth();

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "authorization: Bearer $token",
            "content-type: application/json"
        ),
    ));

    $response = curl_exec($curl);
    if(curl_error($curl)){
        $response = curl_error($curl);
    }
    curl_close($curl);
    return $response;
}

function patchZoom(string $url, string $data) : string
{
    
    require_once '/var/www/html/app/moodle/own_ZoomAPIToken.php';
    $token = generateTokenOAuth();

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "PATCH",
        CURLOPT_HTTPHEADER => array(
            "authorization: Bearer $token",
            "content-type: application/json"
        ),
        CURLOPT_POSTFIELDS => $data
    ));

    $response = curl_exec($curl);
    if(curl_error($curl)){
        $response = curl_error($curl);
    }
    curl_close($curl);
    return $response;
}