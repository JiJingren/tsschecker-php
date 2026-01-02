<?php
require_once __DIR__ . '/Utils.php';

class TssRequest {
    private $req; // Plist 节点
    private $buildManifest; // Plist 节点
    private $buildIdentity; // Plist 节点
    private $generator = 0;
    private $basebandEnabled = true;

    const TSS_CLIENT_VERSION_STRING = "libauthinstall-698.0.5";
    const TSS_MAX_TRIES = 5;

    public function __construct($buildManifest, $variant = "", $isBuildIdentity = false) {
        $this->buildManifest = $isBuildIdentity ? null : Plist::copy($buildManifest);
        $this->buildIdentity = $isBuildIdentity ? Plist::copy($buildManifest) : null;
        $this->req = Plist::new_dict();
        
        $this->setStandardValues();
    }

    private function setStandardValues() {
        Plist::dict_set_item($this->req, "@Locality", Plist::new_string("en_US"));
        
        $os = PHP_OS_FAMILY;
        $platform = "unknown";
        if ($os === "Windows") $platform = "windows";
        elseif ($os === "Darwin") $platform = "mac";
        elseif ($os === "Linux") $platform = "linux";
        
        Plist::dict_set_item($this->req, "@HostPlatformInfo", Plist::new_string($platform));
        Plist::dict_set_item($this->req, "@VersionInfo", Plist::new_string(self::TSS_CLIENT_VERSION_STRING));
        
        $guid = $this->generate_guid();
        if ($guid) {
            Plist::dict_set_item($this->req, "@UUID", Plist::new_string($guid));
        }
        Plist::dict_set_item($this->req, "ApProductionMode", Plist::new_bool(true));
    }

    private function generate_guid() {
        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
            mt_rand(0, 65535), mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(16384, 20479), mt_rand(32768, 49151),
            mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }

    public function setDeviceVals($cpid, $bdid) {
        Plist::dict_set_item($this->req, "ApChipID", Plist::new_uint($cpid));
        Plist::dict_set_item($this->req, "ApBoardID", Plist::new_uint($bdid));
    }

    public function setEcid($ecid) {
        Plist::dict_set_item($this->req, "ApECID", Plist::new_uint($ecid));
    }

    public function setNonceGenerator($generator) {
        $this->generator = $generator;
        $cpidNode = Plist::dict_get_item($this->req, "ApChipID");
        if (!$cpidNode) return;
        $cpid = $cpidNode->value;

        $nonceType = $this->nonceTypeForCPID($cpid);
        if ($nonceType !== 'none') {
            // 将 64 位整数打包为二进制字符串（小端序）
            $data = pack('P', $generator); 
            
            if ($nonceType === 'sha1') {
                $hash = sha1($data, true);
            } else {
                $hash = hash('sha384', $data, true);
            }
            Plist::dict_set_item($this->req, "ApNonce", Plist::new_data($hash));
        }
    }
    
    private function nonceTypeForCPID($cpid) {
        // CPID 列表
        if (in_array($cpid, [0x8900, 0x8720])) return 'none';
        if (in_array($cpid, [0x8920, 0x8922, 0x8930, 0x8940, 0x8942, 0x8945, 0x8947, 0x8950, 0x8955, 0x8960, 0x7000, 0x7001, 0x7002, 0x8000, 0x8003])) return 'sha1';
        return 'sha384';
    }

    public function setBuildIdentity($identity) {
        $this->buildIdentity = $identity;
    }

    public function setBasebandEnabled($enabled) {
        $this->basebandEnabled = $enabled;
    }

    private function isBasebandComponent($name) {
        return $name === "BasebandFirmware";
    }

    public function addDefaultAPTagsToRequest($skipOptional = false) {
         if (!$this->buildIdentity) return;
         
         $pIdentity = $this->buildIdentity;
         
         // 安全域
         $secDomainNode = Plist::dict_get_item($pIdentity, "ApSecurityDomain");
         if ($secDomainNode) {
             $val = $secDomainNode->value;
             // 处理16进制字符串
             if (is_string($val) && stripos($val, '0x') === 0) {
                 $val = hexdec($val);
             }
             Plist::dict_set_item($this->req, "ApSecurityDomain", Plist::new_uint($val));
         }
         
         // 安全模式
         Plist::dict_set_item($this->req, "ApSecurityMode", Plist::new_bool(true));
         
         // 检查 PartialDigest 区分 Img4 和 Img3
         $manifest = Plist::dict_get_item($pIdentity, "Manifest");
         $hasPartialDigest = false;
         if ($manifest) {
             foreach ($manifest->children as $component) {
                 if (Plist::dict_get_item($component, "PartialDigest")) {
                     $hasPartialDigest = true;
                     break;
                 }
             }
         }
         
         // 检查 Info 中的 RequiresUIDMode
         $requiresUIDMode = false;
         $info = Plist::dict_get_item($pIdentity, "Info");
         if ($info) {
             $reqUid = Plist::dict_get_item($info, "RequiresUIDMode");
             if ($reqUid && $reqUid->value === true) $requiresUIDMode = true;
         }
         
         if ($requiresUIDMode) {
              Plist::dict_set_item($this->req, "UID_MODE", Plist::new_bool(false));
         }
         
         if (!$hasPartialDigest) {
             // Img4
             Plist::dict_set_item($this->req, "@ApImg4Ticket", Plist::new_bool(true));
             // Img4 需要 SEP nonce
             if (!Plist::dict_get_item($this->req, "SepNonce")) {
                 $this->setRandomSEPNonce();
             }
         } else {
             // Img3
             Plist::dict_set_item($this->req, "@APTicket", Plist::new_bool(true));
         }
    }

    public function addAllAPComponentsToRequest() {
        if (!$this->buildIdentity) return;
        
        $manifest = Plist::dict_get_item($this->buildIdentity, "Manifest");
        if (!$manifest) return;

        $uniqueBuildID = Plist::dict_get_item($this->buildIdentity, "UniqueBuildID");
        if ($uniqueBuildID) {
             Plist::dict_set_item($this->req, "UniqueBuildID", Plist::copy($uniqueBuildID));
        }

        foreach ($manifest->children as $key => $component) {
            if (!$this->basebandEnabled && $this->isBasebandComponent($key)) {
                continue;
            }

            $pKey = Plist::new_dict();
            foreach ($component->children as $k => $v) {
                if ($v->type !== Plist::PLIST_DICT) {
                    Plist::dict_set_item($pKey, $k, Plist::copy($v));
                }
            }
            
            $hasDigest = Plist::dict_get_item($pKey, "Digest") !== null;
            $isTrusted = false;
            $trustedNode = Plist::dict_get_item($pKey, "Trusted");
            if ($trustedNode && $trustedNode->value === true) $isTrusted = true;

            if ($hasDigest || $isTrusted) {
                Plist::dict_set_item($this->req, $key, $pKey);
            }
        }
        
    }
    
    public function setRandomSEPNonce() {
        // 20 字节随机数
        $bytes = random_bytes(20);
        Plist::dict_set_item($this->req, "SepNonce", Plist::new_data($bytes));
    }
    
    public function setApNonce($nonceData) {
        Plist::dict_set_item($this->req, "ApNonce", Plist::new_data($nonceData));
    }

    public function getTSSResponse() {
        $xml = Plist::to_xml($this->req);
        return $this->sendRequest($xml);
    }

    private function sendRequest($xml) {
        $urls = [
            "https://gs.apple.com/TSS/controller?action=2",
            "http://gs.apple.com/TSS/controller?action=2"
        ];
        
        foreach ($urls as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/xml']);
            curl_setopt($ch, CURLOPT_USERAGENT, "TSSChecker/1.0");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 为了在 Windows 上更易使用
            
            $result = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            
            if ($err) echo "CURL Error: $err\n";
            // echo "Response: $result\n";

            if ($code == 200 && $result) {
                if (preg_match('/REQUEST_STRING=(.*)/s', $result, $matches)) {
                    $plistXml = $matches[1];
                    return Plist::from_xml($plistXml);
                } else {
                    $dict = Plist::new_dict();
                    $parts = explode('&', $result);
                    foreach ($parts as $part) {
                        $kv = explode('=', $part, 2);
                        if (count($kv) == 2) {
                            Plist::dict_set_item($dict, $kv[0], Plist::new_string($kv[1]));
                        }
                    }
                    return $dict;
                }
            }
        }
        return null;
    }
}
