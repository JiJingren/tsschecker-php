<?php

class FirmwareAPI {
    const API_BASE_URL = "https://api.ipsw.me/v4";

    public static function getFirmwareUrl($device, $version, $build = null) {
        $url = self::API_BASE_URL . "/device/" . $device . "?type=ipsw";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "TSSChecker/1.0");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$response) {
            return null;
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['firmwares'])) {
            return null;
        }

        foreach ($data['firmwares'] as $fw) {
            if ($build) {
                if (strcasecmp($fw['buildid'], $build) === 0) {
                    return $fw['url'];
                }
            } else {
                if ($fw['version'] === $version) {
                    return $fw['url'];
                }
            }
        }

        return null;
    }
}
